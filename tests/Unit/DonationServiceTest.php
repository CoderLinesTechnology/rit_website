<?php

declare(strict_types=1);


use App\Core\Database;
use App\Models\Donation;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\DonationService;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\NullGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentsUnavailable;
use App\Services\Payments\VerificationResult;
use App\Services\Payments\WebhookEvent;

/** In-process stand-in for a hosted-checkout provider. */
final class DonationFakeGateway implements PaymentGateway
{
    public int $verifyCalls = 0;
    public ?VerificationResult $result = null;
    public bool $verifyUnavailable = false;
    public string $checkoutUrl = 'https://pay.example/checkout/abc';

    public function name(): string
    {
        return 'faketest';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function initialize(array $donation, string $callbackUrl): CheckoutSession
    {
        return new CheckoutSession($this->checkoutUrl . '?cb=' . rawurlencode($callbackUrl), 'prov-' . $donation['reference']);
    }

    public function verify(array $donation): VerificationResult
    {
        $this->verifyCalls++;
        if ($this->verifyUnavailable) {
            throw new PaymentsUnavailable('provider down');
        }
        return $this->result ?? new VerificationResult(VerificationResult::PENDING, 0, 'GHS');
    }

    public function parseWebhook(string $rawBody, array $headers): ?WebhookEvent
    {
        if (($headers['x-signature'] ?? '') !== 'valid-signature') {
            return null;
        }
        $data = json_decode($rawBody, true);
        return is_array($data) ? new WebhookEvent((string) $data['id'], (string) $data['type'], $data['ref'] ?? null) : null;
    }
}

/** Null when the test database is usable, otherwise the reason to skip. */
function ds_skip(): ?string
{
    static $reason = false;
    if ($reason !== false) {
        return $reason;
    }
    try {
        if (!str_contains((string) config('database.name', ''), 'test')) {
            return $reason = 'test database not configured (.env.testing DB_NAME must contain "test")';
        }
        Database::connection()->fetchValue('SELECT COUNT(*) FROM donations');
        Database::connection()->fetchValue('SELECT COUNT(*) FROM payment_events');
        return $reason = null;
    } catch (Throwable $e) {
        return $reason = 'test database or donations tables not available: ' . $e::class;
    }
}

/** Runs $fn with a fresh fake gateway and removes every donation / event it created. */
function ds_with(callable $fn): void
{
    $db = Database::connection();
    $fake = new DonationFakeGateway();
    $service = new DonationService($fake);
    $db->execute("DELETE FROM payment_events WHERE provider = 'faketest'");
    try {
        $fn($service, $fake);
    } finally {
        GatewayFactory::override(null);
        $db->execute("DELETE FROM payment_events WHERE provider = 'faketest'");
        $db->execute("DELETE FROM donations WHERE provider = 'faketest'");
    }
}

/** Starts a donation and returns its reference. */
function ds_start(DonationService $service, int $minor = 5000): string
{
    $url = $service->start(['amount_minor' => $minor, 'donor_name' => 'Test Donor', 'donor_email' => 'donor@example.org', 'is_anonymous' => false], 'https://rit.example/donate/callback');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    parse_str((string) parse_url((string) $query['cb'], PHP_URL_QUERY), $cb);
    return (string) $cb['reference'];
}

function ds_event(string $id, string $type, ?string $ref): string
{
    return json_encode(['id' => $id, 'type' => $type, 'ref' => $ref], JSON_THROW_ON_ERROR);
}

test('donations: start creates an initiated row that becomes pending and returns the provider URL', function () {
    if (($r = ds_skip()) !== null) { echo "        ($r)\n"; return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        expect_true($service->available());
        $url = $service->start(['amount_minor' => 12345, 'donor_name' => null, 'donor_email' => null, 'is_anonymous' => true], 'https://rit.example/donate/callback');
        expect_true(str_starts_with($url, 'https://pay.example/checkout/abc'), 'redirect URL comes from the gateway');
        $row = Database::connection()->fetch("SELECT * FROM donations WHERE provider = 'faketest' ORDER BY id DESC LIMIT 1");
        expect_true(preg_match('/^RIT-DON-[A-Z0-9]{8}$/', (string) $row['reference']) === 1);
        expect_same('pending', $row['status']);
        expect_same(12345, (int) $row['amount_minor']);
        expect_same('GHS', $row['currency']);
        expect_same(1, (int) $row['is_anonymous']);
        expect_same('prov-' . $row['reference'], $row['provider_reference']);
        expect_same(null, $row['verified_at'], 'starting a payment never marks it verified');
        expect_contains(rawurlencode('https://rit.example/donate/callback?reference=' . $row['reference']), $url);
    });
});

test('donations: start refuses amounts outside the configured range and an unavailable gateway', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service) {
        $count = (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM donations WHERE provider = 'faketest'");
        expect_throws(InvalidArgumentException::class, fn () => $service->start(['amount_minor' => 99, 'donor_name' => null, 'donor_email' => null, 'is_anonymous' => false], 'https://rit.example/cb'));
        expect_throws(InvalidArgumentException::class, fn () => $service->start(['amount_minor' => 100_000_001, 'donor_name' => null, 'donor_email' => null, 'is_anonymous' => false], 'https://rit.example/cb'));
        expect_same($count, (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM donations WHERE provider = 'faketest'"), 'no row for refused amounts');
        $null = new DonationService(new NullGateway());
        expect_false($null->available());
        expect_throws(PaymentsUnavailable::class, fn () => $null->start(['amount_minor' => 5000, 'donor_name' => null, 'donor_email' => null, 'is_anonymous' => false], 'https://rit.example/cb'));
    });
});

test('donations: the default factory gateway is the null gateway when no provider is configured', function () {
    GatewayFactory::override(null);
    $gateway = GatewayFactory::make();
    expect_false($gateway->isConfigured());
    expect_same(null, GatewayFactory::forWebhook('none'));
    expect_same(null, GatewayFactory::forWebhook('anything'));
});

test('donations: confirm with provider success and the matching amount becomes successful and verified', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service, 5000);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'GHS', 'prov-1', 'success');
        $donation = $service->confirm($ref);
        expect_same('successful', $donation['status']);
        expect_true($donation['verified_at'] !== null, 'verified_at is set');
        expect_same('prov-1', $donation['provider_reference']);
        expect_same(1, $fake->verifyCalls);
        // Confirming again does not ask the provider again (final state).
        $service->confirm($ref);
        expect_same(1, $fake->verifyCalls);
    });
});

test('donations: an amount or currency mismatch is never successful', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service, 5000);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 4999, 'GHS');
        $donation = $service->confirm($ref);
        expect_same('failed', $donation['status']);
        expect_true($donation['status'] !== 'successful');

        $ref2 = ds_start($service, 5000);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'USD');
        expect_same('failed', $service->confirm($ref2)['status'], 'currency mismatch');
    });
});

test('donations: pending, unavailable verification and unknown references change nothing', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service);
        $fake->result = new VerificationResult(VerificationResult::PENDING, 5000, 'GHS');
        expect_same('pending', $service->confirm($ref)['status']);
        $fake->verifyUnavailable = true;
        expect_same('pending', $service->confirm($ref)['status'], 'provider outage leaves the donation pending');
        expect_same(null, $service->confirm('RIT-DON-AAAAAAAA'));
        // A donation created with another provider is never verified through this gateway.
        $other = Donation::create(['reference' => Donation::newReference(), 'provider' => 'faketest', 'amount_minor' => 100, 'currency' => 'GHS', 'status' => 'pending']);
        Database::connection()->execute("UPDATE donations SET provider = 'faketest-other' WHERE id = :i", ['i' => $other]);
        $fake->verifyUnavailable = false;
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 100, 'GHS');
        $calls = $fake->verifyCalls;
        $row = $service->confirm((string) Donation::find($other)['reference']);
        expect_same('pending', $row['status']);
        expect_same($calls, $fake->verifyCalls);
        Database::connection()->execute('DELETE FROM donations WHERE id = :i', ['i' => $other]);
    });
});

test('donations: a webhook with an invalid or missing signature is rejected with 401 and changes nothing', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'GHS');
        $body = ds_event('evt-bad', 'charge.success', $ref);
        expect_same(401, $service->webhook($body, ['x-signature' => 'forged']));
        expect_same(401, $service->webhook($body, []));
        expect_same('pending', Donation::findByReference($ref)['status']);
        expect_same(0, $fake->verifyCalls, 'a forged webhook never triggers verification');
        expect_same(0, (int) Database::connection()->fetchValue("SELECT COUNT(*) FROM payment_events WHERE provider = 'faketest'"));
    });
});

test('donations: a valid webhook is verified with the provider and a replayed event id is processed once', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'GHS', 'prov-9', 'success');
        $headers = ['x-signature' => 'valid-signature'];
        $body = ds_event('evt-1', 'charge.success', $ref);
        expect_same(200, $service->webhook($body, $headers));
        $row = Donation::findByReference($ref);
        expect_same('successful', $row['status']);
        expect_true($row['verified_at'] !== null);
        expect_same(1, $fake->verifyCalls);
        // Replays (provider retries) are acknowledged but not processed again.
        expect_same(200, $service->webhook($body, $headers));
        expect_same(200, $service->webhook($body, $headers));
        expect_same(1, $fake->verifyCalls, 'verify ran once for the replayed event');
        $events = Database::connection()->fetchAll("SELECT * FROM payment_events WHERE provider = 'faketest' AND event_id = 'evt-1'");
        expect_same(1, count($events));
        expect_same(1, (int) $events[0]['signature_valid']);
        expect_true($events[0]['processed_at'] !== null);
        expect_same(hash('sha256', $body), $events[0]['payload_sha256'], 'only the payload hash is stored');
    });
});

test('donations: a late failed result can never downgrade a verified success', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service);
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'GHS');
        $service->confirm($ref);
        $verified = Donation::findByReference($ref)['verified_at'];

        // A new webhook event arrives after success, and the provider (now) reports "failed".
        $fake->result = new VerificationResult(VerificationResult::FAILED, 5000, 'GHS');
        expect_same(200, $service->webhook(ds_event('evt-late', 'charge.failed', $ref), ['x-signature' => 'valid-signature']));
        $row = Donation::findByReference($ref);
        expect_same('successful', $row['status']);
        expect_same($verified, $row['verified_at']);
        // And at the model level the transition is refused outright.
        expect_false(Donation::applyVerifiedStatus((int) $row['id'], 'failed', null, 'failed'));
        expect_false(Donation::applyVerifiedStatus((int) $row['id'], 'pending', null, 'pending'));
        expect_same('successful', Donation::findByReference($ref)['status']);
    });
});

test('donations: a webhook for an unknown donation is acknowledged and changes nothing', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        expect_same(200, $service->webhook(ds_event('evt-x', 'charge.success', 'RIT-DON-NOPE2222'), ['x-signature' => 'valid-signature']));
        expect_same(200, $service->webhook(ds_event('evt-y', 'charge.success', null), ['x-signature' => 'valid-signature']));
        expect_same(0, $fake->verifyCalls);
    });
});

test('donations: a webhook whose verification is unavailable answers 503 and is processed on the retry', function () {
    if (($r = ds_skip()) !== null) { return 'skip'; }
    ds_with(function (DonationService $service, DonationFakeGateway $fake) {
        $ref = ds_start($service);
        $headers = ['x-signature' => 'valid-signature'];
        $body = ds_event('evt-retry', 'charge.success', $ref);
        $fake->verifyUnavailable = true;
        expect_same(503, $service->webhook($body, $headers), 'provider must retry');
        expect_same('pending', Donation::findByReference($ref)['status']);
        // The provider retries the same event once verification works again.
        $fake->verifyUnavailable = false;
        $fake->result = new VerificationResult(VerificationResult::SUCCESSFUL, 5000, 'GHS', 'prov-r', 'success');
        expect_same(200, $service->webhook($body, $headers));
        expect_same('successful', Donation::findByReference($ref)['status'], 'the retried event was applied');
        expect_same(200, $service->webhook($body, $headers), 'now a true duplicate');
        expect_same(2, $fake->verifyCalls);
    });
});
