<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 8 acceptance: every create, update and delete on the audited models
 * produces a queryable entry with correct before/after values.
 */
class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public static function auditedModels(): array
    {
        return [
            'product' => [Product::class, 'product', ['title' => 'A ring'], ['title' => 'A renamed ring']],
            'order' => [Order::class, 'order', [], ['status' => 'paid']],
            'setting' => [Setting::class, 'setting', ['group' => 'general', 'key' => 'test_key', 'value' => 'one', 'type' => 'string'], ['value' => 'two']],
            'user' => [User::class, 'user', [], ['name' => 'Renamed person']],
            'customer' => [Customer::class, 'customer', ['name' => 'Anne'], ['name' => 'Anne Delacroix']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('auditedModels')]
    public function test_create_update_and_delete_are_all_recorded(string $class, string $name, array $attributes, array $changes): void
    {
        $model = $class === Order::class
            ? Order::create(['order_number' => Order::nextOrderNumber(), 'location_id' => Location::factory()->create()->id, 'channel' => 'pos'])
            : match ($class) {
                User::class => User::factory()->create(),
                Setting::class => Setting::create($attributes),
                default => $class::factory()->create($attributes),
            };

        $this->assertDatabaseHas('audit_logs', ['action' => "{$name}.created", 'auditable_id' => $model->getKey()]);

        $field = array_key_first($changes);
        $before = $model->{$field};
        $model->update($changes);

        $update = AuditLog::query()->where('action', "{$name}.updated")->where('auditable_id', $model->getKey())->latest('id')->firstOrFail();

        $this->assertSame($before, $update->old_values[$field] ?? null);
        $this->assertSame($changes[$field], $update->new_values[$field] ?? null);

        $model->delete();

        $this->assertDatabaseHas('audit_logs', ['action' => "{$name}.deleted", 'auditable_id' => $model->getKey()]);
    }

    public function test_payments_are_audited_as_financial_records(): void
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => Location::factory()->create()->id,
            'channel' => 'pos',
        ]);

        Payment::create([
            'order_id' => $order->id, 'gateway' => 'stripe', 'amount' => 1000,
            'currency' => 'USD', 'status' => 'succeeded', 'method' => 'card',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.created', 'category' => 'financial']);
        // Orders too: both sides of the money.
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.created', 'category' => 'financial']);
    }

    public function test_retention_keeps_financial_records_longer_than_general_activity(): void
    {
        Setting::set('security.audit_retention_days', 730);
        Setting::set('security.audit_retention_days_financial', 2557);

        $old = now()->subDays(1000);

        AuditLog::create(['action' => 'product.updated', 'category' => 'general', 'created_at' => $old]);
        AuditLog::create(['action' => 'payment.created', 'category' => 'financial', 'created_at' => $old]);

        $this->artisan('audit:purge')->assertSuccessful();

        // 1,000 days is past the general window but well inside the 7-year
        // financial one (IRS recordkeeping).
        $this->assertSame(0, AuditLog::where('action', 'product.updated')->count());
        $this->assertSame(1, AuditLog::where('action', 'payment.created')->count());
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        AuditLog::create(['action' => 'product.updated', 'category' => 'general', 'created_at' => now()->subDays(5000)]);

        $this->artisan('audit:purge --dry-run')->assertSuccessful();

        $this->assertSame(1, AuditLog::count());
    }

    public function test_an_encrypted_setting_is_masked_in_the_trail(): void
    {
        Setting::query()->create([
            'group' => 'payments', 'key' => 'stripe_secret_key',
            'type' => 'encrypted_string', 'is_encrypted' => true,
        ]);

        Setting::set('payments.stripe_secret_key', 'sk_live_supersecret4417');

        $log = AuditLog::query()->where('action', 'setting.updated')->latest('id')->firstOrFail();

        $this->assertStringNotContainsString('supersecret', json_encode($log->new_values));
    }

    public function test_a_login_is_recorded_with_its_ip(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $log = AuditLog::query()->where('action', 'auth.login')->latest('id')->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertNotNull($log->ip_address);
        $this->assertSame('security', $log->category);
    }

    public function test_a_failed_login_is_recorded_too(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'category' => 'security']);
    }
}
