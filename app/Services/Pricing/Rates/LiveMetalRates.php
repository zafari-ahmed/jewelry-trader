<?php

namespace App\Services\Pricing\Rates;

use App\Models\Setting;
use App\Services\Pricing\Contracts\MetalRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Layer 2 — a live market feed, layered over the base rates.
 *
 * Switchable: on when the business wants today's spot price, off when it
 * wants the rates it set itself. No supplier is named here — the endpoint,
 * the key and the response shape are all settings, so changing feed is a
 * saved form rather than a rebuild (rule 3.3).
 *
 * It fails soft, always. A feed that is unreachable, slow or returning
 * nonsense falls back to the base table and says so in the working. Pricing a
 * piece must never depend on somebody else's uptime.
 */
class LiveMetalRates implements MetalRateProvider
{
    private string $source = 'base rate table';

    public function __construct(private SettingsMetalRates $base) {}

    public function ratePerGram(string $metal): ?float
    {
        $baseRate = $this->base->ratePerGram($metal);

        if (! Setting::get('pricing.layer.live_rates_enabled', false)) {
            $this->source = 'base rate table';

            return $baseRate;
        }

        $spot = $this->spotPrices();
        $match = $this->matchSpot($metal, $spot);

        if ($match === null) {
            $this->source = 'base rate table (live feed had no rate for this metal)';

            return $baseRate;
        }

        [$label, $perGram] = $match;

        $this->source = $baseRate !== null && $baseRate > 0
            ? 'live feed · '.$label.' '.$this->movement($baseRate, $perGram).' the base rate'
            : 'live feed · '.$label;

        return $perGram;
    }

    public function sourceLabel(): string
    {
        return $this->source;
    }

    /**
     * Metal name → dollars per gram, as the configured feed reports it.
     *
     * Cached briefly: a dozen pieces catalogued in a morning should not be a
     * dozen calls, and spot prices do not move meaningfully in minutes.
     *
     * @return array<string, float>
     */
    private function spotPrices(): array
    {
        $endpoint = trim((string) Setting::get('pricing.live_rates_endpoint', ''));

        if ($endpoint === '') {
            return [];
        }

        $ttl = max(60, (int) Setting::get('pricing.live_rates_cache_seconds', 900));

        return Cache::remember('pricing.live_rates', $ttl, function () use ($endpoint) {
            try {
                $key = (string) Setting::get('pricing.live_rates_api_key', '');

                $response = Http::timeout((int) Setting::get('pricing.live_rates_timeout_seconds', 10))
                    ->when($key !== '', fn ($request) => $request->withToken($key))
                    ->acceptJson()
                    ->get($endpoint);

                if (! $response->successful()) {
                    return [];
                }

                return $this->readRates($response->json());
            } catch (\Throwable $e) {
                // A feed that is down is not an error the counter should see.
                Log::warning('Live metal rate feed unavailable: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Pull rates out of whatever shape the configured feed returns.
     *
     * The path to the rates and the unit they are quoted in are settings, so a
     * feed quoting dollars per troy ounce and one quoting dollars per gram
     * both work without a code change.
     *
     * @return array<string, float>
     */
    private function readRates(mixed $payload): array
    {
        $path = trim((string) Setting::get('pricing.live_rates_path', ''));

        $rates = $path === '' ? $payload : data_get($payload, $path);

        if (! is_array($rates)) {
            return [];
        }

        // Troy ounces are the usual quote unit for precious metal; grams are
        // what a jeweller weighs in.
        $perOunce = (bool) Setting::get('pricing.live_rates_quoted_per_ounce', true);
        $gramsPerTroyOunce = 31.1034768;

        $out = [];

        foreach ($rates as $metal => $value) {
            if (! is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            $out[strtolower((string) $metal)] = $perOunce
                ? (float) $value / $gramsPerTroyOunce
                : (float) $value;
        }

        return $out;
    }

    /** @param array<string, float> $spot @return array{0: string, 1: float}|null */
    private function matchSpot(string $metal, array $spot): ?array
    {
        if ($spot === []) {
            return null;
        }

        $needle = strtolower(trim($metal));

        foreach ($spot as $key => $perGram) {
            if (str_contains($needle, $key)) {
                return [$key, $this->applyPurity($needle, $key, $perGram)];
            }
        }

        return null;
    }

    /**
     * A feed quotes fine metal; a piece is rarely fine metal.
     *
     * 18k gold is 75% gold by weight, so the spot price of pure gold has to be
     * taken down to the alloy actually in the piece before it means anything.
     */
    private function applyPurity(string $metal, string $spotKey, float $perGram): float
    {
        $purities = Setting::get('pricing.metal_purity_fractions', []);

        if (! is_array($purities)) {
            return $perGram;
        }

        foreach ($purities as $key => $fraction) {
            if (str_contains($metal, strtolower((string) $key)) && (float) $fraction > 0) {
                return $perGram * (float) $fraction;
            }
        }

        return $perGram;
    }

    private function movement(float $base, float $live): string
    {
        $change = ($live - $base) / $base * 100;

        if (abs($change) < 0.05) {
            return 'level with';
        }

        return number_format(abs($change), 2).'% '.($change > 0 ? 'above' : 'below');
    }
}
