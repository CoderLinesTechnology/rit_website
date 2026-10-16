<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class VerificationResult
{
    public const SUCCESSFUL = 'successful';
    public const FAILED = 'failed';
    public const PENDING = 'pending';
    public const ABANDONED = 'abandoned';
    public const REFUNDED = 'refunded';

    public function __construct(
        public readonly string $status,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $providerReference = null,
        public readonly ?string $providerStatus = null,
    ) {
    }
}
