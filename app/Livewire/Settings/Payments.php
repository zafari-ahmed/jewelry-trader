<?php

namespace App\Livewire\Settings;

use App\Models\PaymentGateway;
use Illuminate\Validation\Rule;

class Payments extends SettingsComponent
{
    protected function permission(): string
    {
        return 'manage-payments-config';
    }

    protected function group(): string
    {
        return 'payments';
    }

    protected function secretKeys(): array
    {
        return ['stripe_secret_key', 'stripe_webhook_secret', 'stripe_test_secret_key', 'stripe_test_webhook_secret'];
    }

    protected function rules(): array
    {
        return [
            // An unknown gateway must fail here, not at checkout (Module 3 acceptance).
            'state.active_gateway' => ['required', 'string', Rule::exists('payment_gateways', 'slug')->where('is_active', true)],
        ];
    }

    public function render()
    {
        return view('livewire.settings.payments', [
            'gateways' => PaymentGateway::query()->active()->orderBy('name')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Payment Settings',
            'heading' => 'Payment Settings',
            'subheading' => 'Gateway, API credentials, test mode',
        ]);
    }
}
