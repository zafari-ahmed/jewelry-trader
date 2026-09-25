<?php

namespace App\Livewire\Settings;

/**
 * The rate table behind price suggestions. These are the numbers the business
 * maintains — the reason a suggested price can be explained rather than merely
 * asserted.
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

    protected function rules(): array
    {
        return [
            'state.retail_multiplier' => ['required', 'numeric', 'min:1'],
            'state.insurance_multiplier' => ['required', 'numeric', 'min:1'],
            'state.suggestion_band_percent' => ['required', 'integer', 'min:0', 'max:50'],
            'state.negotiation_floor_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function render()
    {
        return view('livewire.settings.pricing')->layout('layouts.admin-livewire', [
            'title' => 'Pricing Factors',
            'heading' => 'Pricing Factors',
            'subheading' => 'The rates and weights behind a suggested price',
        ]);
    }
}
