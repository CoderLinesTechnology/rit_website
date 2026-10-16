<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Used while no payment provider has been chosen (PAYMENT_PROVIDER=none).
 * It can never start, verify or accept a payment.
 */
final class NullGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function initialize(array $donation, string $callbackUrl): CheckoutSession
    {
        throw new PaymentsUnavailable('Online donations are not available yet.');
    }

    public function verify(array $donation): VerificationResult
    {
        throw new PaymentsUnavailable('Online donations are not available yet.');
    }

    public function parseWebhook(string $rawBody, array $headers): ?WebhookEvent
    {
        return null;
    }
}
