<?php

namespace App\Livewire\Settings;

use App\Support\SettingsRegistry;

class FeatureFlags extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-feature-flags';
    }

    protected function group(): string
    {
        return 'features';
    }

    public function render()
    {
        return view('livewire.settings.feature-flags', ['flags' => SettingsRegistry::features()])
            ->layout('layouts.admin-livewire', [
                'title' => 'Feature Flags',
                'heading' => 'Feature Flags',
                'subheading' => 'Phase 2 modules · all off by default',
            ]);
    }
}
