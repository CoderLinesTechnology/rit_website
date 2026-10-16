<?php

declare(strict_types=1);

use App\Core\Env;

/*
 * No payment provider has been selected by the Foundation yet. While
 * PAYMENT_PROVIDER is "none" the donation page shows the administrator's
 * notice/offline instructions and no payment is ever initiated.
 *
 * To add a provider, implement App\Services\Payments\PaymentGateway and
 * register it in App\Services\Payments\GatewayFactory (see docs/PAYMENTS.md).
 */
return [
    'provider' => strtolower(Env::get('PAYMENT_PROVIDER', 'none') ?? 'none'),
    'public_key' => Env::secret('PAYMENT_PUBLIC_KEY'),
    'secret_key' => Env::secret('PAYMENT_SECRET_KEY'),
    'webhook_secret' => Env::secret('PAYMENT_WEBHOOK_SECRET'),
    'currency' => 'GHS',
    // Amounts in pesewas (minor units).
    'min_amount_minor' => 100,
    'max_amount_minor' => 100_000_000,
];
