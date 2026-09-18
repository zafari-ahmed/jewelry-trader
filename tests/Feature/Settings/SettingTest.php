<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_values_by_type(): void
    {
        Setting::set('pos.return_window_days', 30);
        Setting::set('payments.test_mode', true);
        Setting::set('security.password_policy', ['min_length' => 12]);

        $this->assertSame(30, Setting::get('pos.return_window_days'));
        $this->assertTrue(Setting::get('payments.test_mode'));
        $this->assertSame(['min_length' => 12], Setting::get('security.password_policy'));
    }

    public function test_it_returns_the_default_when_a_setting_is_missing(): void
    {
        $this->assertSame('stripe', Setting::get('payments.active_gateway', 'stripe'));
    }

    public function test_a_secret_is_encrypted_at_rest_and_decrypted_on_read(): void
    {
        Setting::query()->create([
            'group' => 'payments', 'key' => 'stripe_secret_key',
            'type' => 'encrypted_string', 'is_encrypted' => true,
        ]);

        Setting::set('payments.stripe_secret_key', 'sk_live_51NfQ2xKq4417');

        $stored = Setting::query()->where('key', 'stripe_secret_key')->value('value');

        $this->assertNotSame('sk_live_51NfQ2xKq4417', $stored);
        $this->assertStringNotContainsString('sk_live', $stored);
        $this->assertSame('sk_live_51NfQ2xKq4417', Setting::get('payments.stripe_secret_key'));
    }

    public function test_it_masks_a_stored_secret_for_display(): void
    {
        Setting::query()->create([
            'group' => 'payments', 'key' => 'stripe_secret_key',
            'type' => 'encrypted_string', 'is_encrypted' => true,
        ]);
        Setting::set('payments.stripe_secret_key', 'sk_live_51NfQ2xKq4417');

        $this->assertSame('sk_live_••••4417', Setting::masked('payments.stripe_secret_key'));
        $this->assertNull(Setting::masked('payments.stripe_webhook_secret'));
    }

    public function test_feature_flags_are_off_until_set(): void
    {
        $this->assertFalse(Setting::enabled('features.rental.enabled'));

        Setting::set('features.rental.enabled', true);

        $this->assertTrue(Setting::enabled('features.rental.enabled'));
    }

    public function test_a_change_is_audited_with_encrypted_values_masked(): void
    {
        Setting::set('payments.active_gateway', 'stripe');
        Setting::set('payments.active_gateway', 'square');

        $update = AuditLog::query()->where('action', 'setting.updated')->latest('id')->first();

        $this->assertSame('stripe', $update->old_values['value']);
        $this->assertSame('square', $update->new_values['value']);

        Setting::query()->create([
            'group' => 'payments', 'key' => 'stripe_secret_key',
            'type' => 'encrypted_string', 'is_encrypted' => true,
        ]);
        Setting::set('payments.stripe_secret_key', 'sk_live_51NfQ2xKq4417');

        $secretUpdate = AuditLog::query()->where('action', 'setting.updated')->latest('id')->first();

        $this->assertStringNotContainsString('sk_live', json_encode($secretUpdate->new_values));
    }

    public function test_the_path_must_be_group_dot_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Setting::set('active_gateway', 'stripe');
    }
}
