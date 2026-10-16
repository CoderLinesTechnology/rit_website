<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Logger;
use App\Models\Donation;

/**
 * Donation flow on top of a PaymentGateway:
 *
 *  start()     create an "initiated" row, open the provider's hosted checkout;
 *  confirm()   on the donor's return, ask the provider (server-side) for the
 *              authoritative status and record it;
 *  webhook()   authenticate the provider's signature, record the event once
 *              (replays are ignored), then verify with the provider's API
 *              before changing anything.
 *
 * A status only becomes "successful" after verify() reports success for the
 * exact amount and currency we created.
 */
final class DonationService
{
    public function __construct(private readonly PaymentGateway $gateway)
    {
    }

    public static function make(): self
    {
        return new self(GatewayFactory::make());
    }

    public function available(): bool
    {
        return $this->gateway->isConfigured();
    }

    /**
     * @param array{amount_minor: int, donor_name: ?string, donor_email: ?string, is_anonymous: bool} $input
     * @return string URL of the provider's checkout page
     */
    public function start(array $input, string $callbackUrl): string
    {
        if (!$this->available()) {
            throw new PaymentsUnavailable('Online donations are not available yet.');
        }
        $min = (int) config('payments.min_amount_minor', 100);
        $max = (int) config('payments.max_amount_minor', 100_000_000);
        if ($input['amount_minor'] < $min || $input['amount_minor'] > $max) {
            throw new \InvalidArgumentException('Amount out of range.');
        }
        $reference = Donation::newReference();
        $id = Donation::create([
            'reference' => $reference,
            'provider' => $this->gateway->name(),
            'amount_minor' => $input['amount_minor'],
            'currency' => (string) config('payments.currency', 'GHS'),
            'donor_name' => $input['donor_name'],
            'donor_email' => $input['donor_email'],
            'is_anonymous' => $input['is_anonymous'] ? 1 : 0,
            'status' => 'initiated',
        ]);
        $donation = (array) Donation::find($id);
        $session = $this->gateway->initialize($donation, $callbackUrl . '?reference=' . rawurlencode($reference));
        if ($session->providerReference !== null) {
            Donation::update($id, ['provider_reference' => $session->providerReference, 'status' => 'pending']);
        } else {
            Donation::update($id, ['status' => 'pending']);
        }
        return $session->redirectUrl;
    }

    /** @return array<string, mixed>|null The donation after verification (status from our database). */
    public function confirm(string $reference): ?array
    {
        $donation = Donation::findByReference($reference);
        if ($donation === null || !$this->available() || $donation['provider'] !== $this->gateway->name()) {
            return $donation;
        }
        if (in_array($donation['status'], ['initiated', 'pending'], true)) {
            $this->verifyAndApply($donation);
        }
        return Donation::findByReference($reference);
    }

    /**
     * @param array<string, string> $headers lower-case names
     * @return int HTTP status to answer the provider with
     */
    public function webhook(string $rawBody, array $headers): int
    {
        $event = $this->gateway->parseWebhook($rawBody, $headers);
        if ($event === null) {
            Logger::warning('Rejected payment webhook with a missing or invalid signature.', ['provider' => $this->gateway->name()]);
            return 401;
        }
        $donation = $event->donationReference !== null ? Donation::findByReference($event->donationReference) : null;
        $isNew = Donation::recordEvent($donation !== null ? (int) $donation['id'] : null, $this->gateway->name(), $event->eventId, $event->eventType, true, $rawBody);
        if (!$isNew && Donation::eventProcessed($this->gateway->name(), $event->eventId)) {
            return 200; // already handled — acknowledge so the provider stops retrying
        }
        // A retry of an event we stored but could not finish is processed again.
        if ($donation !== null && !$this->verifyAndApply($donation)) {
            return 503; // verification unavailable: the provider will retry later
        }
        Donation::markEventProcessed($this->gateway->name(), $event->eventId);
        return 200;
    }

    /**
     * @param array<string, mixed> $donation
     * @return bool false when the provider could not be asked (nothing was decided)
     */
    private function verifyAndApply(array $donation): bool
    {
        try {
            $result = $this->gateway->verify($donation);
        } catch (PaymentsUnavailable $e) {
            Logger::warning('Donation verification unavailable: ' . $e->getMessage(), ['reference' => $donation['reference']]);
            return false;
        }
        $status = $result->status;
        if ($status === VerificationResult::SUCCESSFUL
            && ($result->amountMinor !== (int) $donation['amount_minor'] || strtoupper($result->currency) !== strtoupper((string) $donation['currency']))) {
            Logger::error('Donation amount/currency mismatch reported by provider; not marked successful.', ['reference' => $donation['reference']]);
            $status = VerificationResult::FAILED;
        }
        if ($status === VerificationResult::PENDING) {
            return true;
        }
        Donation::applyVerifiedStatus((int) $donation['id'], $status, $result->providerReference, $result->providerStatus);
        return true;
    }
}
