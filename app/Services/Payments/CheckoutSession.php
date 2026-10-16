<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class CheckoutSession
{
    public function __construct(
        public readonly string $redirectUrl,
        public readonly ?string $providerReference = null,
    ) {
    }
}
