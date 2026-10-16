<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Contract for a hosted-checkout payment provider (e.g. one supporting
 * Ghanaian cards and mobile money). The donor pays on the provider's page;
 * this application never sees card numbers, CVVs, PINs or wallet passwords.
 *
 * Rules every implementation must follow (see docs/PAYMENTS.md):
 *  - secret keys come from config('payments.*') (Env::secret), never the browser;
 *  - verify() calls the provider's official server-side API — a redirect or
 *    a webhook body alone is never proof of payment;
 *  - parseWebhook() authenticates the provider's signature with a
 *    constant-time comparison and returns null when it does not match.
 */
interface PaymentGateway
{
    /** Short provider key, also used in the webhook URL /webhooks/payments/{provider}. */
    public function name(): string;

    /** True only when every credential is present and not a placeholder. */
    public function isConfigured(): bool;

    /**
     * Creates a hosted checkout for the donation.
     *
     * @param array<string, mixed> $donation Row from the donations table.
     * @throws PaymentsUnavailable
     */
    public function initialize(array $donation, string $callbackUrl): CheckoutSession;

    /**
     * Asks the provider for the authoritative status of the donation.
     *
     * @param array<string, mixed> $donation
     * @throws PaymentsUnavailable
     */
    public function verify(array $donation): VerificationResult;

    /**
     * Authenticates and parses a webhook. Returns null when the signature is
     * missing or invalid.
     *
     * @param array<string, string> $headers Lower-case header names.
     */
    public function parseWebhook(string $rawBody, array $headers): ?WebhookEvent;
}
