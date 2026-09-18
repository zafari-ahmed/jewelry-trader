<?php

namespace App\Livewire\Settings;

use App\Models\AiProvider;

/**
 * Visible but inert in Phase 1. Saving is allowed (so a provider can be
 * provisioned ahead of time) but every capability flag stays off by default and
 * the Null providers in Module 2 refuse calls regardless.
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
        ])->layout('layouts.admin-livewire', [
            'title' => 'AI & Automation',
            'heading' => 'AI & Automation',
            'subheading' => 'Prepared for Phase 2 — no capability active',
        ]);
    }
}
