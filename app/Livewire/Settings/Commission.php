<?php

namespace App\Livewire\Settings;

class Commission extends SettingsComponent
{
    /** Types are data-driven so a new structure is a row, not a code change. */
    public const TYPES = [
        'sales_based' => 'Sales-based — flat percentage of sale value',
        'profit_based' => 'Profit-based — percentage of margin over acquisition cost',
        'tiered' => 'Tiered — rate rises at configured thresholds',
        'split' => 'Split — pool divided across involved staff',
    ];

    protected function permission(): string
    {
        return 'manage-commission-config';
    }

    protected function group(): string
    {
        return 'commission';
    }

    protected function rules(): array
    {
        return [
            'state.default_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'state.default_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function render()
    {
        return view('livewire.settings.commission', ['types' => self::TYPES])
            ->layout('layouts.admin-livewire', [
                'title' => 'Commission',
                'heading' => 'Commission',
                'subheading' => 'Default plan used when a salesperson has no individual assignment',
            ]);
    }
}
