<?php

namespace App\Livewire\Settings;

use App\Models\MetalRateFetch;
use App\Models\MetalRateProvider;
use App\Models\Setting;
use App\Services\Pricing\CraftsmanFormula;
use App\Services\Pricing\Rates\LiveMetalRates;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * The pricing stack, top to bottom.
 *
 * The craftsman's formula is the floor and never changes; its percentages do,
 * and they live here. The layers above it — live metal rates, the multiplier
 * tables, market adjustments — each switch on and off independently, so a
 * pricing strategy can be tested without the rest of the stack moving.
 */
class Pricing extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-settings';
    }

    protected function group(): string
    {
        return 'pricing';
    }

    protected function secretKeys(): array
    {
        return ['live_rates_api_key'];
    }

    protected function rules(): array
    {
        return [
            // Ranges from the specification. A percentage outside them does not
            // describe a trade anybody runs.
            'state.formula_overhead_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'state.formula_design_percent' => ['required', 'numeric', 'min:0', 'max:50', $this->leavesSomethingToDivideBy()],
            'state.formula_wholesale_commission_percent' => ['required', 'numeric', 'min:0', 'max:30'],
            'state.formula_retail_commission_percent' => ['required', 'numeric', 'min:0', 'max:80'],
            'state.formula_rounding_increment' => ['required', 'numeric', 'min:0', 'max:100'],

            'state.insurance_multiplier' => ['required', 'numeric', 'min:1'],
            'state.suggestion_band_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'state.negotiation_floor_percent' => ['required', 'integer', 'min:1', 'max:100'],

            'state.live_rates_endpoint' => ['nullable', 'url'],
            'state.live_rates_cache_seconds' => ['required', 'integer', 'min:60'],
            'state.live_rates_timeout_seconds' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    /**
     * Overhead and design are taken out of the same 100%.
     *
     * Together they must leave something to divide by, or Step 2 has no
     * arithmetic meaning. Catching it here makes it a validation message on a
     * form rather than a surprise at a counter.
     */
    private function leavesSomethingToDivideBy(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $combined = (float) ($this->state['formula_overhead_percent'] ?? 0) + (float) $value;

            if ($combined >= 95) {
                $fail('Overhead and design together come to '.rtrim(rtrim(number_format($combined, 2), '0'), '.').'%, which leaves nothing for Step 2 to divide by. Keep them under 95%.');
            }
        };
    }

    /**
     * A live example, recalculated as the percentages are edited.
     *
     * Percentages on their own are abstract; what they do to a real piece is
     * not. This is the specification's own worked example — $180 of bench work
     * on $1,200 of platinum — so a change can be judged before it is saved.
     */
    public function getPreviewProperty(): array
    {
        $labour = 18000;
        $material = 120000;
        $determining = $labour + $material;

        $steps = [
            ['Determining factors', 'Labour $180.00 + materials $1,200.00', $determining],
        ];

        $price = $determining;

        foreach ([
            ['Basic price', (float) ($this->state['formula_overhead_percent'] ?? 0) + (float) ($this->state['formula_design_percent'] ?? 0), 'overhead + design'],
            ['Wholesale price', (float) ($this->state['formula_wholesale_commission_percent'] ?? 0), 'wholesale commission'],
            ['Retail price', (float) ($this->state['formula_retail_commission_percent'] ?? 0), 'retail commission'],
        ] as [$label, $percent, $what]) {
            $divisor = (100 - $percent) / 100;

            if ($divisor < 0.05) {
                $steps[] = [$label, 'No margin left to divide by', null];

                continue;
            }

            $price = (int) round($price / $divisor);
            $steps[] = [$label, '÷ '.number_format($divisor, 2).' ('.rtrim(rtrim(number_format($percent, 2), '0'), '.').'% '.$what.')', $price];
        }

        $increment = max(0, (int) round(((float) ($this->state['formula_rounding_increment'] ?? 0)) * 100));

        if (($this->state['formula_rounding_enabled'] ?? false) && $increment > 0) {
            $rounded = (int) (ceil($price / $increment) * $increment);

            if ($rounded !== $price) {
                $steps[] = ['Rounded up', 'To the nearest $'.number_format($increment / 100, 2), $rounded];
                $price = $rounded;
            }
        }

        return ['steps' => $steps, 'final' => $price];
    }

    public ?string $feedResult = null;

    public ?string $feedError = null;

    /**
     * Ask the feed for rates now, and say plainly what came back.
     *
     * Marked as a test so it stays out of the failure count and cannot set
     * off the "feed is down" alert while somebody is deliberately poking it.
     */
    public function testConnection(): void
    {
        Gate::authorize('manage-settings');

        $this->reset('feedResult', 'feedError');

        $provider = MetalRateProvider::query()
            ->where('slug', $this->state['live_rates_provider'] ?? '')
            ->first();

        $endpoint = trim((string) ($provider?->endpoint ?: ($this->state['live_rates_endpoint'] ?? '')));

        if ($endpoint === '') {
            $this->feedError = 'There is no feed address to test yet.';

            return;
        }

        $rates = app(LiveMetalRates::class)->fetch($endpoint, $provider, isTest: true);

        if ($rates === []) {
            $this->feedError = MetalRateFetch::latest('id')->value('error')
                ?? 'The feed did not return any rates that could be read.';

            return;
        }

        // Cached rates are now stale relative to what we just proved works.
        Cache::forget('pricing.live_rates');

        $this->feedResult = 'Read '.count($rates).' '.str('rate')->plural(count($rates)).': '
            .collect($rates)
                ->map(fn ($perGram, $metal) => str($metal)->headline().' $'.number_format($perGram, 2).'/g')
                ->implode(' · ');
    }

    public function render()
    {
        return view('livewire.settings.pricing', [
            'houseRates' => app(CraftsmanFormula::class)->percentagesFor(null),
            'providers' => MetalRateProvider::query()->where('is_active', true)->orderBy('name')->get(),
            'lastFetch' => MetalRateFetch::lastSuccessful(),
            'recentFetches' => MetalRateFetch::query()->latest('id')->limit(8)->get(),
            'consecutiveFailures' => MetalRateFetch::consecutiveFailures(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Pricing',
            'heading' => 'Pricing',
            'subheading' => "The craftsman's formula, and the layers above it",
        ]);
    }
}
