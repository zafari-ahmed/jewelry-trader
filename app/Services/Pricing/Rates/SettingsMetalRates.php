<?php

namespace App\Services\Pricing\Rates;

use App\Models\Setting;
use App\Services\Pricing\Contracts\MetalRateProvider;

/**
 * Layer 1 — the base metal rates the business maintains.
 *
 * Always available, and deliberately so: this is the fallback every other
 * layer falls back to. Rates are matched loosely, so "18K Yellow Gold" finds
 * the "18k" rate without anyone having to type the description twice.
 */
class SettingsMetalRates implements MetalRateProvider
{
    public function ratePerGram(string $metal): ?float
    {
        $rates = Setting::get('pricing.metal_rates_per_gram', []);

        if (! is_array($rates) || blank($metal)) {
            return null;
        }

        $needle = strtolower(trim($metal));

        foreach ($rates as $key => $rate) {
            if (str_contains($needle, strtolower((string) $key))) {
                return (float) $rate;
            }
        }

        return null;
    }

    public function sourceLabel(): string
    {
        return 'base rate table';
    }
}
