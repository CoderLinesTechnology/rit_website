<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Logger;

/**
 * Resolves the configured payment gateway. Register a real provider here
 * once the Foundation has chosen one (see docs/PAYMENTS.md).
 */
final class GatewayFactory
{
    /** @var array<string, class-string<PaymentGateway>> provider key => implementation */
    private const PROVIDERS = [
        // 'example' => ExampleGateway::class,
    ];

    private static ?PaymentGateway $override = null;

    public static function make(): PaymentGateway
    {
        if (self::$override !== null) {
            return self::$override;
        }
        $provider = (string) config('payments.provider', 'none');
        if ($provider === 'none' || $provider === '') {
            return new NullGateway();
        }
        $class = self::PROVIDERS[$provider] ?? null;
        if ($class === null) {
            Logger::warning('Unknown PAYMENT_PROVIDER; online donations stay disabled.', ['provider' => mb_substr($provider, 0, 40)]);
            return new NullGateway();
        }
        $gateway = new $class();
        return $gateway->isConfigured() ? $gateway : new NullGateway();
    }

    /** The configured gateway if its key matches the webhook URL, else null. */
    public static function forWebhook(string $provider): ?PaymentGateway
    {
        $gateway = self::make();
        return $gateway->isConfigured() && hash_equals($gateway->name(), $provider) ? $gateway : null;
    }

    /** For tests only. */
    public static function override(?PaymentGateway $gateway): void
    {
        self::$override = $gateway;
    }
}
