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

            // The dashboard shows publishable and secret keys side by side, so
            // pasting the wrong one is easy — and would only surface as a
            // cryptic gateway error at checkout. Catch it at the form.
            'state.stripe_publishable_key' => ['nullable', 'string', 'starts_with:pk_live_,pk_test_'],
            'state.stripe_test_publishable_key' => ['nullable', 'string', 'starts_with:pk_test_'],
            'state.stripe_secret_key' => ['nullable', 'string', 'starts_with:sk_live_,rk_live_'],
            'state.stripe_test_secret_key' => ['nullable', 'string', 'starts_with:sk_test_,rk_test_'],
            'state.stripe_webhook_secret' => ['nullable', 'string', 'starts_with:whsec_'],
            'state.stripe_test_webhook_secret' => ['nullable', 'string', 'starts_with:whsec_'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'state.stripe_publishable_key' => 'publishable key (live)',
            'state.stripe_test_publishable_key' => 'publishable key (test)',
            'state.stripe_secret_key' => 'secret key (live)',
            'state.stripe_test_secret_key' => 'secret key (test)',
            'state.stripe_webhook_secret' => 'webhook signing secret (live)',
            'state.stripe_test_webhook_secret' => 'webhook signing secret (test)',
        ];
    }

    protected function messages(): array
    {
        return [
            'state.stripe_test_secret_key.starts_with' => 'That looks like a publishable key. The secret key starts with sk_test_ — use "Reveal test key" in the Stripe dashboard.',
            'state.stripe_secret_key.starts_with' => 'That looks like a publishable key. The live secret key starts with sk_live_.',
            'state.stripe_test_publishable_key.starts_with' => 'The test publishable key starts with pk_test_.',
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
