<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use RuntimeException;

/**
 * Resolves the gateway named by payments.active_gateway at runtime.
 *
 * Never `new StripeGateway()` in a controller: with only Stripe implemented
 * today, going through the factory is what makes adding PayPal a data row plus
 * a class (rule 3.3).
 */
class PaymentGatewayFactory
{
    public static function make(?string $slug = null): PaymentGatewayInterface
    {
        $slug ??= Setting::get('payments.active_gateway', 'stripe');

        $row = PaymentGateway::query()->where('slug', $slug)->where('is_active', true)->first();

        if (! $row) {
            // Settings validation blocks this at the form; reaching it means a
            // gateway was deactivated after being selected.
            throw new RuntimeException("Payment gateway [{$slug}] is not configured or not active. Check Settings → Payments.");
        }

        if (! class_exists($row->driver_class)) {
            throw new RuntimeException("Payment gateway [{$slug}] names a driver class that does not exist: {$row->driver_class}.");
        }

        $gateway = app($row->driver_class);

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new RuntimeException("Payment gateway [{$slug}] driver must implement PaymentGatewayInterface.");
        }

        return $gateway;
    }
}
