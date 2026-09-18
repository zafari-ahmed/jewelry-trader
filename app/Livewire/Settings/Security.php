<?php

namespace App\Livewire\Settings;

use Spatie\Permission\Models\Role;

class Security extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-security-config';
    }

    protected function group(): string
    {
        return 'security';
    }

    protected function rules(): array
    {
        return [
            'state.session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'state.audit_retention_days' => ['required', 'integer', 'min:30'],
            'state.audit_retention_days_financial' => ['required', 'integer', 'min:2557'],
            'state.password_policy.min_length' => ['required', 'integer', 'min:8', 'max:128'],
        ];
    }

    public function render()
    {
        return view('livewire.settings.security', [
            'roles' => Role::query()->orderBy('name')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Security',
            'heading' => 'Security',
            'subheading' => 'Two-factor, session policy, audit retention',
        ]);
    }
}
