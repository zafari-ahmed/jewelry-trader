<?php

namespace App\Livewire\Settings;

use App\Models\AiProvider;
use App\Models\AiUsage;

/**
 * The connection to the reading service, and what it is costing.
 *
 * No supplier is named here or anywhere in the codebase: the endpoint, the key
 * and the model are entries on this form, so changing supplier is a saved form
 * rather than a rebuild (rule 3.3). Every capability is off by default, and
 * with none configured cataloguing works by hand exactly as before.
 */
class Ai extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-ai-config';
    }

    protected function group(): string
    {
        return 'ai';
    }

    protected function secretKeys(): array
    {
        return ['api_key'];
    }

    public function render()
    {
        return view('livewire.settings.ai', [
            'providers' => AiProvider::query()->orderBy('name')->get(),
            'thisMonth' => AiUsage::summaryFor(now()),
            'lastMonth' => AiUsage::summaryFor(now()->subMonthNoOverflow()),
        ])->layout('layouts.admin-livewire', [
            'title' => 'AI & Automation',
            'heading' => 'AI & Automation',
            'subheading' => 'The reading service, and what it costs',
        ]);
    }
}
