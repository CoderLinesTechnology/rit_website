<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Donation;
use App\Services\FormGuard;
use App\Services\Payments\DonationService;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\PaymentsUnavailable;
use App\Services\Settings;

/** Donation page, checkout hand-off, return page and the provider webhook. */
final class DonationController extends Controller
{
    private const FORM = 'donate';
    private const PRESETS = ['50', '100', '200', '500'];

    public function index(Request $request): Response
    {
        $service = DonationService::make();
        $available = $service->available();
        return $this->view('pages/donate', [
            'available' => $available,
            'nonce' => $available ? FormGuard::nonce(self::FORM) : '',
            'presets' => self::PRESETS,
        ] + $this->meta('donate', Settings::get('donate.hero_heading', 'Support Our Work'), Settings::get('donate.hero_text'), '/donate'));
    }

    public function start(Request $request): Response
    {
        $service = DonationService::make();
        if (!$service->available()) {
            flash_message('info', 'Online donations are not available yet. Please see the other ways to support us below.');
            return redirect('/donate');
        }
        FormGuard::check($request, self::FORM);

        $v = Validator::make($request->post(), [
            'amount' => 'required|in:' . implode(',', self::PRESETS) . ',other',
            'amount_other' => 'nullable|line|max:12',
            'donor_name' => 'nullable|line|max:150',
            'donor_email' => 'nullable|email|max:190',
            'is_anonymous' => 'boolean',
            'privacy' => 'accepted',
        ], [
            'amount' => 'Donation amount',
            'amount_other' => 'Other amount',
            'donor_name' => 'Your name',
            'donor_email' => 'Email address',
            'privacy' => 'Agreement to the Privacy Policy',
        ]);

        $minor = null;
        if (!isset($v->errors()['amount'])) {
            $choice = (string) $v->validated()['amount'];
            if ($choice === 'other') {
                $minor = self::parseAmount((string) ($v->validated()['amount_other'] ?? ''));
                if ($minor === null) {
                    $v->addError('amount_other', 'Other amount must be a number such as 75 or 75.50.');
                }
            } else {
                $minor = (int) $choice * 100;
            }
            $min = (int) config('payments.min_amount_minor', 100);
            $max = (int) config('payments.max_amount_minor', 100_000_000);
            if ($minor !== null && ($minor < $min || $minor > $max)) {
                $v->addError($choice === 'other' ? 'amount_other' : 'amount', 'The donation amount must be between ' . money($min) . ' and ' . money($max) . '.');
            }
        }
        if ($v->fails()) {
            return redirect_with_errors('/donate', $v->errors(), $request->post());
        }

        $data = $v->validated();
        try {
            $url = $service->start([
                'amount_minor' => (int) $minor,
                'donor_name' => $data['donor_name'],
                'donor_email' => $data['donor_email'],
                'is_anonymous' => (bool) $data['is_anonymous'],
            ], absolute_url('/donate/callback'));
        } catch (PaymentsUnavailable) {
            flash_message('info', 'Online donations are not available right now. Please try again later.');
            return redirect('/donate');
        } catch (\Throwable $e) {
            Logger::error('Could not start a donation checkout.', ['exception' => $e::class]);
            flash_message('error', 'We could not start the payment. No money was taken. Please try again later.');
            return redirect('/donate');
        }

        // Response::redirect() only allows same-site URLs; the hosted checkout is on the provider's domain.
        // Only an https:// URL without whitespace or control characters is ever followed.
        if (preg_match('#^https://[^\s\x00-\x1F\x7F]+$#', $url) !== 1) {
            Logger::error('Payment provider returned an unusable checkout URL.');
            flash_message('error', 'We could not start the payment. No money was taken. Please try again later.');
            return redirect('/donate');
        }
        $origin = (string) parse_url($url, PHP_URL_SCHEME) . '://' . (string) parse_url($url, PHP_URL_HOST)
            . (parse_url($url, PHP_URL_PORT) !== null ? ':' . parse_url($url, PHP_URL_PORT) : '');
        // The CSP's form-action also governs where this POST may be redirected to.
        return (new Response('', 303, ['Location' => $url]))->allowFormAction($origin);
    }

    public function callback(Request $request): Response
    {
        $reference = $request->query('reference');
        if (preg_match('/^RIT-DON-[A-Z0-9]{8}$/', $reference) !== 1) {
            throw HttpException::notFound('That donation could not be found.');
        }
        $donation = DonationService::make()->confirm($reference);
        if ($donation === null) {
            throw HttpException::notFound('That donation could not be found.');
        }
        return $this->view('pages/donate-status', [
            'donation' => $donation,
            'robots' => 'noindex',
        ] + $this->meta('donate-status', 'Your donation', 'The status of your donation.'));
    }

    public function webhook(Request $request, string $provider): Response
    {
        $gateway = GatewayFactory::forWebhook($provider) ?? throw HttpException::notFound();
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (is_string($value) && str_starts_with($name, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($name, 5)))] = $value;
            } elseif ($name === 'CONTENT_TYPE' && is_string($value)) {
                $headers['content-type'] = $value;
            }
        }
        // The payload is never logged; DonationService records only its hash.
        $code = (new DonationService($gateway))->webhook($request->rawBody(), $headers);
        return Response::json(['received' => $code === 200], $code);
    }

    /** "75" / "75.5" / "75.50" to pesewas without floating point; null when not a plain amount. */
    private static function parseAmount(string $text): ?int
    {
        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $text, $m) !== 1) {
            return null;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
