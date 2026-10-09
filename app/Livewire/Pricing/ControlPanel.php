<?php

namespace App\Livewire\Pricing;

use App\Models\MetalRateFetch;
use App\Models\RateChangeProposal;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * One screen that says what the pricing stack is currently doing.
 *
 * The four layers are configured on separate screens, which makes it easy to
 * switch something on in one place and forget it is on. This shows all four
 * at once, with a real piece priced through them so the effect of each is a
 * number rather than a claim.
 */
class ControlPanel extends Component
{
    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('manage-settings');
    }

    /**
     * The layers, top to bottom, as they currently stand.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLayersProperty(): array
    {
        $multipliersOn = (bool) Setting::get('pricing.layer.multipliers_enabled', true);

        return [
            [
                'number' => 1,
                'name' => 'Base metal rates',
                'summary' => 'The rates you maintain. Always on — every other layer falls back to this.',
                'toggle' => null,
                'on' => true,
                'updated' => $this->updatedAt('pricing.metal_rates_per_gram'),
                'blocked' => null,
            ],
            [
                'number' => 2,
                'name' => 'Live metal rates',
                'summary' => 'Prices metal at the market instead of at your table, adjusted for purity.',
                'toggle' => 'pricing.layer.live_rates_enabled',
                'on' => (bool) Setting::get('pricing.layer.live_rates_enabled', false),
                'updated' => MetalRateFetch::lastSuccessful()?->created_at,
                'blocked' => null,
            ],
            [
                'number' => 3,
                'name' => 'Maker, period and condition',
                'summary' => 'What this particular piece is worth beyond its materials and labour.',
                'toggle' => 'pricing.layer.multipliers_enabled',
                'on' => $multipliersOn,
                'updated' => $this->updatedAt('pricing.brand_premiums'),
                'blocked' => null,
            ],
            [
                'number' => 4,
                'name' => 'Market adjustments',
                'summary' => 'Category demand, the season, the region, and how long the piece has sat.',
                'toggle' => 'pricing.layer.market_enabled',
                'on' => (bool) Setting::get('pricing.layer.market_enabled', false),
                'updated' => $this->updatedAt('pricing.category_demand'),
                // Layer 4 reads the market; Layer 3 reads the piece. Adjusting
                // for a soft market on a figure that has not accounted for the
                // maker means nothing yet.
                'blocked' => $multipliersOn ? null : 'Needs Layer 3 switched on first',
            ],
        ];
    }

    /**
     * A real piece, priced through whatever is currently switched on.
     *
     * Each layer's contribution is shown in dollars, because "Layer 3 is on"
     * tells nobody what it is doing to the shelf price.
     */
    public function getExampleProperty(): array
    {
        $suggestion = app(PricingEngine::class)->suggest([
            'category' => 'rings',
            'brand' => 'Cartier',
            'style_period' => 'Art Deco',
            'condition_grade' => 'Excellent',
            'metal_type' => '950 Platinum',
            'weight_grams' => 8.2,
            'labor_cost_cents' => 18000,
            'region' => 'New York',
            'days_in_stock' => 14,
        ], [[
            'stone_type' => 'diamond',
            'estimated_weight_ct' => 2.0,
            'clarity' => 'SI1',
            'color' => 'J',
        ]]);

        $running = $suggestion->baseRetailCents;
        $contributions = [];

        foreach ($suggestion->lines as $line) {
            if (! str_starts_with($line->label, 'Layer ') || $line->resultCents === null) {
                continue;
            }

            $contributions[] = [
                'label' => $line->label,
                'detail' => $line->detail,
                'delta' => $line->resultCents - $running,
            ];

            $running = $line->resultCents;
        }

        return ['suggestion' => $suggestion, 'contributions' => $contributions];
    }

    public function getPendingProposalsProperty(): int
    {
        return RateChangeProposal::pending()->count();
    }

    public function toggle(string $key): void
    {
        Gate::authorize('manage-settings');

        $turningOn = ! Setting::get($key, false);

        Setting::set($key, $turningOn, Auth::id());

        // Switching Layer 3 off leaves Layer 4 switched on but inert, which
        // reads as a bug to anyone looking at the panel. Take it down with
        // its dependency and say so, rather than leaving a lie on screen.
        if ($key === 'pricing.layer.multipliers_enabled' && ! $turningOn && Setting::get('pricing.layer.market_enabled', false)) {
            Setting::set('pricing.layer.market_enabled', false, Auth::id());
            $this->flash = 'Layer 3 switched off, and Layer 4 with it — it cannot run on its own.';

            return;
        }

        $this->flash = 'Saved.';
    }

    /** Switch every optional layer on or off in one action. */
    public function setAllLayers(bool $on): void
    {
        Gate::authorize('manage-settings');

        foreach ([
            'pricing.layer.live_rates_enabled',
            'pricing.layer.multipliers_enabled',
            'pricing.layer.market_enabled',
        ] as $key) {
            Setting::set($key, $on, Auth::id());
        }

        $this->flash = $on
            ? 'Every layer is on. Layer 1 was already — it cannot be switched off.'
            : 'Every optional layer is off. Pricing is the formula on your own base rates.';
    }

    /**
     * The whole pricing configuration as a file.
     *
     * Useful before a change nobody is sure about, and useful to an
     * accountant who wants to see what the rates were in March.
     */
    public function export()
    {
        Gate::authorize('manage-settings');

        $config = collect(Setting::query()->where('group', 'pricing')->get())
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => Setting::get('pricing.'.$setting->key)])
            // A key is a secret, not configuration, and must never ride out
            // in a file somebody emails around (rule 3.2).
            ->except(['live_rates_api_key'])
            ->sortKeys();

        $filename = 'pricing-configuration-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            fn () => print(json_encode([
                'exported_at' => now()->toIso8601String(),
                'pricing' => $config,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    private function updatedAt(string $path): ?\Illuminate\Support\Carbon
    {
        [$group, $key] = explode('.', $path, 2);

        return Setting::query()->where('group', $group)->where('key', $key)->value('updated_at');
    }

    public function render()
    {
        return view('livewire.pricing.control-panel')->layout('layouts.admin-livewire', [
            'title' => 'Pricing Control',
            'heading' => 'Pricing control',
            'subheading' => 'What the stack is doing right now, and what each layer is worth',
        ]);
    }
}
