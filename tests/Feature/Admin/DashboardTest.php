<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard;
use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Location $madison;

    private Location $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->madison = Location::factory()->create(['name' => 'Madison Ave', 'is_active' => true]);
        $this->workshop = Location::factory()->create(['name' => 'Workshop', 'is_active' => true]);
    }

    private function manager(?Location $at = null): User
    {
        $user = User::factory()->create(['location_id' => ($at ?? $this->madison)->id]);
        $user->assignRole('store-manager');

        return $user->fresh();
    }

    private function paidOrder(Location $at, int $cents, string $status = 'paid'): Order
    {
        return Order::factory()->create([
            'location_id' => $at->id, 'status' => $status, 'total_cents' => $cents,
        ]);
    }

    public function test_an_empty_system_reports_nothing_rather_than_a_figure(): void
    {
        $user = $this->manager();

        // Seeding writes its own trail — settings created, locations created —
        // so an empty feed has to be arranged rather than assumed.
        AuditLog::query()->delete();

        $component = Livewire::actingAs($user)->test(Dashboard::class);

        // The static screen this replaced said $18,420 and 1,284 items on a
        // database that held neither.
        $this->assertSame(0, $component->get('today')['total_cents']);
        $this->assertSame(0, $component->get('today')['count']);
        $this->assertSame(0, $component->get('pendingReview'));
        $this->assertSame(0, $component->get('drafts'));
        $this->assertSame(0, $component->get('activeInventory'));

        $component->assertSeeText('$0.00')
            ->assertSeeText('Nothing has happened yet')
            ->assertDontSeeText('18,420')
            ->assertDontSeeText('Whitfield');
    }

    public function test_todays_takings_count_paid_orders_only(): void
    {
        $this->paidOrder($this->madison, 680000);
        $this->paidOrder($this->madison, 20000, 'fulfilled');
        $this->paidOrder($this->madison, 999900, 'pending');

        $today = Livewire::actingAs($this->manager())->test(Dashboard::class)->get('today');

        $this->assertSame(700000, $today['total_cents']);
        $this->assertSame(2, $today['count']);
    }

    public function test_a_refunded_order_is_not_counted_as_a_sale(): void
    {
        $this->paidOrder($this->madison, 50000);
        $this->paidOrder($this->madison, 30000, 'refunded');

        $today = Livewire::actingAs($this->manager())->test(Dashboard::class)->get('today');

        // Reported beside the takings rather than netted off: twenty sales and
        // nineteen returns is not a quiet day and should not read like one.
        $this->assertSame(50000, $today['total_cents']);
        $this->assertSame(1, $today['refunded']);
    }

    public function test_yesterdays_sales_are_not_todays(): void
    {
        $order = $this->paidOrder($this->madison, 50000);

        // created_at is not fillable, so it is moved underneath the model.
        DB::table('orders')->where('id', $order->id)->update(['created_at' => now()->subDay()]);

        $this->assertSame(0, Livewire::actingAs($this->manager())->test(Dashboard::class)->get('today')['total_cents']);
    }

    public function test_the_figures_are_scoped_to_the_users_own_location(): void
    {
        $this->paidOrder($this->madison, 10000);
        $this->paidOrder($this->workshop, 90000);

        $clerk = User::factory()->create(['location_id' => $this->madison->id]);
        $clerk->assignRole('customer-service');   // no view-all-locations

        // Rule 3.7: one shop's takings are not another's business.
        $component = Livewire::actingAs($clerk->fresh())->test(Dashboard::class);

        $this->assertSame(10000, $component->get('today')['total_cents']);
        $this->assertSame(1, $component->get('locationCount'));
    }

    public function test_a_user_with_cross_location_sight_sees_every_location(): void
    {
        $this->paidOrder($this->madison, 10000);
        $this->paidOrder($this->workshop, 90000);

        $component = Livewire::actingAs($this->manager())->test(Dashboard::class);

        $this->assertSame(100000, $component->get('today')['total_cents']);
        $this->assertSame(2, $component->get('locationCount'));
        $this->assertStringContainsString('Madison Ave', $component->get('locationNames'));
    }

    public function test_the_queues_count_what_is_actually_waiting(): void
    {
        foreach (['pending_review', 'draft'] as $status) {
            $product = Product::factory()->create(['status' => $status]);
            InventoryStock::factory()->create([
                'product_id' => $product->id, 'location_id' => $this->madison->id,
            ]);
        }

        $listed = Product::factory()->listed()->create();
        InventoryStock::factory()->create([
            'product_id' => $listed->id, 'location_id' => $this->madison->id, 'status' => 'in_stock',
        ]);

        $component = Livewire::actingAs($this->manager())->test(Dashboard::class);

        $this->assertSame(1, $component->get('pendingReview'));
        $this->assertSame(1, $component->get('drafts'));
    }

    public function test_a_sold_item_is_not_active_inventory(): void
    {
        $product = Product::factory()->create(['status' => 'sold']);
        InventoryStock::factory()->create([
            'product_id' => $product->id, 'location_id' => $this->madison->id, 'status' => 'sold',
        ]);

        $this->assertSame(0, Livewire::actingAs($this->manager())->test(Dashboard::class)->get('activeInventory'));
    }

    public function test_the_activity_feed_is_the_audit_trail(): void
    {
        $user = $this->manager();
        $product = Product::factory()->create(['sku' => 'EST-9001', 'status' => 'draft']);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'product.updated',
            'category' => 'inventory',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'new_values' => ['title' => 'A ring'],
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)
            ->assertSeeText('Product updated — EST-9001')
            ->assertSeeText($user->name)
            ->assertDontSeeText('Nothing has happened yet');
    }

    public function test_an_entry_survives_the_record_it_describes(): void
    {
        $user = $this->manager();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'order.deleted',
            'category' => 'orders',
            'auditable_type' => Order::class,
            'auditable_id' => 99999,          // gone, as a deletion entry's subject is
            'ip_address' => '127.0.0.1',
        ]);

        Livewire::actingAs($user)->test(Dashboard::class)->assertSeeText('Order deleted');
    }
}
