<?php

declare(strict_types=1);

namespace App\Services\Payments;

/** Thrown when online payments are not configured or the provider cannot be reached. */
final class PaymentsUnavailable extends \RuntimeException
{
}
