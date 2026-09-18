<?php

namespace App\Livewire\Settings;

use App\Models\Location;

class General extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-settings';
    }

    protected function group(): string
    {
        return 'general';
    }

    protected function rules(): array
    {
        return [
            'state.company_name' => ['required', 'string', 'max:255'],
            'state.timezone' => ['required', 'string', 'timezone'],
            'state.currency' => ['required', 'string', 'size:3'],
        ];
    }

    public function render()
    {
        return view('livewire.settings.general', [
            // Tax is per location because it is US state-based (docs/DECISIONS.md).
            'locations' => Location::query()->orderBy('name')->get(),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA),
        ])->layout('layouts.admin-livewire', [
            'title' => 'General Settings',
            'heading' => 'General',
            'subheading' => 'Business details, tax profile, currency, receipt footer',
        ]);
    }
}
