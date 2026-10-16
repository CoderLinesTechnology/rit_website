<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class WebhookEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        /** Our donation reference (RIT-DON-…) carried in the provider's metadata. */
        public readonly ?string $donationReference,
    ) {
    }
}
