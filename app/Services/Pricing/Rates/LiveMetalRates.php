<?php

namespace App\Services\Pricing\Rates;

use App\Models\MetalRateFetch;
use App\Models\MetalRateProvider as ProviderRow;
use App\Models\Setting;
use App\Notifications\MetalFeedFailing;
use App\Services\Pricing\Contracts\MetalRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Layer 2 — a live market feed, layered over the base rates.
 *
 * Switchable, and no supplier is named in this file: which feed answers, its
 * address, where the rates sit in its response and the unit it quotes in are
 * all rows and settings, so changing feed is a saved form (rule 3.3).
 *
 * The important behaviour here is what happens when it goes wrong, because
 * that is the common case over a long enough period. The business chooses:
 *
 *   base       drop to the rate table it maintains  (the default)
 *   last_known use the last rate the feed did return
 *   hold       refuse to price the metal at all
 *
 * The first two keep the shop open. The third is for a business that would
 * rather sell nothing than sell at a stale price, and it is deliberately not
 * the default — a pricing screen that stops working because somebody else's
 * server is down is usually the wrong trade.
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

        if ($match !== null) {
            [$label, $perGram] = $match;

            $this->source = $baseRate !== null && $baseRate > 0
                ? 'live feed · '.$label.' '.$this->movement($baseRate, $perGram).' the base rate'
                : 'live feed · '.$label;

            return $perGram;
        }

        return $this->whenUnavailable($metal, $baseRate, $spot === []);
    }

    public function sourceLabel(): string
    {
        return $this->source;
    }

    /**
     * Fetch failed, or quoted nothing for this metal. What now?
     *
     * A feed that answered but does not cover this metal is not a failure —
     * nobody quotes spot for brass — so that always falls back quietly to
     * the table rather than triggering the hold or the alert.
     */
    private function whenUnavailable(string $metal, ?float $baseRate, bool $feedUnavailable): ?float
    {
        if (! $feedUnavailable) {
            $this->source = 'base rate table (live feed had no rate for this metal)';

            return $baseRate;
        }

        return match ((string) Setting::get('pricing.live_rates_failure_behaviour', 'base')) {
            'last_known' => $this->lastKnown($metal, $baseRate),
            'hold' => $this->hold(),
            default => $this->fallBackToBase($baseRate),
        };
    }

    private function fallBackToBase(?float $baseRate): ?float
    {
        $this->source = 'base rate table (live feed unavailable)';

        return $baseRate;
    }

    private function lastKnown(string $metal, ?float $baseRate): ?float
    {
        $fetch = MetalRateFetch::lastSuccessful();
        $match = $fetch ? $this->matchSpot($metal, $fetch->rates ?? []) : null;

        if ($match === null) {
            return $this->fallBackToBase($baseRate);
        }

        $this->source = 'last known live rate, '.$fetch->created_at->diffForHumans();

        return $match[1];
    }

    /**
     * Refuse to price the metal.
     *
     * Returning null makes the engine report the rate as missing, which is
     * how every other unpriceable attribute behaves — the piece shows what it
     * is waiting for rather than a figure nobody should trust.
     */
    private function hold(): ?float
    {
        $this->source = 'held — the live feed is unavailable and pricing is set to hold';

        return null;
    }

    /**
     * Metal name → dollars per gram of fine metal, as the feed reports it.
     *
     * Cached for the configured interval: a dozen pieces catalogued in a
     * morning should not be a dozen calls, and spot does not move
     * meaningfully in minutes.
     *
     * @return array<string, float>
     */
    private function spotPrices(): array
    {
        $provider = $this->provider();
        $endpoint = trim((string) ($provider?->endpoint ?: Setting::get('pricing.live_rates_endpoint', '')));

        if ($endpoint === '') {
            return [];
        }

        $ttl = max(60, (int) Setting::get('pricing.live_rates_cache_seconds', 900));

        return Cache::remember('pricing.live_rates', $ttl, fn () => $this->fetch($endpoint, $provider));
    }

    /** @return array<string, float> */
    public function fetch(string $endpoint, ?ProviderRow $provider = null, bool $isTest = false): array
    {
        $startedAt = microtime(true);
        $rates = [];
        $error = null;

        try {
            $key = (string) Setting::get('pricing.live_rates_api_key', '');

            $response = Http::timeout((int) Setting::get('pricing.live_rates_timeout_seconds', 10))
                ->when($key !== '', fn ($request) => $request->withToken($key))
                ->acceptJson()
                ->get($endpoint);

            if ($response->successful()) {
                $rates = $this->readRates($response->json(), $provider);

                if ($rates === []) {
                    $error = 'The feed answered but no rates could be read from it.';
                }
            } else {
                $error = 'The feed returned status '.$response->status().'.';
            }
        } catch (\Throwable $e) {
            // A feed that is down is not an error the counter should see.
            Log::warning('Live metal rate feed unavailable: '.$e->getMessage());
            $error = $e->getMessage();
        }

        $this->record($provider?->slug, $rates, $error, (int) round((microtime(true) - $startedAt) * 1000), $isTest);

        return $error === null ? $rates : [];
    }

    /** @param array<string, float> $rates */
    private function record(?string $slug, array $rates, ?string $error, int $durationMs, bool $isTest): void
    {
        try {
            MetalRateFetch::create([
                'provider_slug' => $slug,
                'succeeded' => $error === null,
                'rates' => $rates ?: null,
                'error' => $error === null ? null : mb_substr($error, 0, 255),
                'duration_ms' => min($durationMs, 65535),
                'was_test' => $isTest,
                'created_at' => now(),
            ]);

            if ($error !== null && ! $isTest) {
                $this->alertIfPersistent();
            }
        } catch (\Throwable) {
            // Bookkeeping must never be the thing that stops a piece being priced.
        }
    }

    /**
     * Tell somebody, once, when the feed has failed enough times to matter.
     *
     * A single timeout is noise. The alert fires on the run that crosses the
     * threshold and not on every failure after it, so a feed that is down all
     * weekend produces one message rather than nine hundred.
     */
    private function alertIfPersistent(): void
    {
        $threshold = (int) Setting::get('pricing.live_rates_alert_after_failures', 3);
        $recipients = array_filter(array_map(
            'trim',
            explode(',', (string) Setting::get('pricing.live_rates_alert_recipients', '')),
        ));

        if ($threshold <= 0 || $recipients === [] || MetalRateFetch::consecutiveFailures() !== $threshold) {
            return;
        }

        Notification::route('mail', $recipients)->notify(new MetalFeedFailing($threshold));
    }

    private function provider(): ?ProviderRow
    {
        $slug = (string) Setting::get('pricing.live_rates_provider', '');

        return $slug === '' ? null : ProviderRow::query()->where('slug', $slug)->first();
    }

    /**
     * Pull rates out of whatever shape the configured feed returns.
     *
     * @return array<string, float>
     */
    private function readRates(mixed $payload, ?ProviderRow $provider): array
    {
        $path = trim((string) ($provider?->rates_path ?: Setting::get('pricing.live_rates_path', '')));

        $rates = $path === '' ? $payload : data_get($payload, $path);

        if (! is_array($rates)) {
            return [];
        }

        $perOunce = $provider
            ? $provider->quoted_per_ounce
            : (bool) Setting::get('pricing.live_rates_quoted_per_ounce', true);

        $gramsPerTroyOunce = 31.1034768;
        $covered = $this->coveredMetals();
        $out = [];

        foreach ($rates as $metal => $value) {
            $metal = strtolower((string) $metal);

            if (! is_numeric($value) || (float) $value <= 0) {
                continue;
            }

            // A feed may quote more than the business wants to price from.
            if ($covered !== [] && ! in_array($metal, $covered, true)) {
                continue;
            }

            $out[$metal] = $perOunce ? (float) $value / $gramsPerTroyOunce : (float) $value;
        }

        return $out;
    }

    /** @return string[] */
    private function coveredMetals(): array
    {
        $covered = Setting::get('pricing.live_rates_metals', []);

        return is_array($covered)
            ? array_values(array_map('strtolower', array_filter($covered)))
            : [];
    }

    /** @param array<string, float> $spot @return array{0: string, 1: float}|null */
    private function matchSpot(string $metal, array $spot): ?array
    {
        if ($spot === []) {
            return null;
        }

        $needle = strtolower(trim($metal));

        foreach ($spot as $key => $perGram) {
            if (str_contains($needle, (string) $key)) {
                return [$key, $this->applyPurity($needle, $perGram)];
            }
        }

        return null;
    }

    /**
     * A feed quotes fine metal; a piece is rarely fine metal.
     *
     * 18k gold is 75% gold by weight, so the spot price of pure gold has to
     * be taken down to the alloy actually in the piece before it means
     * anything.
     */
    private function applyPurity(string $metal, float $perGram): float
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
