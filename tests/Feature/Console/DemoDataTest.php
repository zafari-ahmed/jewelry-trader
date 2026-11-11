<?php

namespace Tests\Feature\Console;

use App\Models\Commission;
use App\Models\Customer;
use App\Models\InventoryLock;
use App\Models\Order;
use App\Models\Override;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\ProductIntakeService;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);   // also seeds the categories

        // Somebody has to exist for the records to be attributed to.
        User::factory()->create()->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);
    }

    public function test_it_loads_a_worked_example_of_the_whole_business(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        $this->assertSame(12, Product::where('sku', 'like', 'DEMO-%')->count());
        $this->assertSame(4, Customer::where('email', 'like', '%@demo.invalid')->count());

        // Something in every state the workflow passes through.
        foreach (['draft', 'pending_review', 'approved', 'listed', 'sold'] as $status) {
            $this->assertTrue(
                Product::where('sku', 'like', 'DEMO-%')->where('status', $status)->exists(),
                "No demo product is in the [{$status}] state.",
            );
        }

        $this->assertSame(3, Order::count());
        $this->assertSame(1, Order::whereIn('status', ['refunded', 'partially_refunded'])->count());
        $this->assertSame(2, Commission::count());
        $this->assertSame(1, Override::count());
        $this->assertSame(1, InventoryLock::count());
    }

    public function test_every_record_it_writes_can_be_told_apart_from_a_real_one(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        // The whole point: demo data that reads as real data is worse than none.
        $this->assertSame(0, Product::where('sku', 'not like', 'DEMO-%')->count());
        $this->assertSame(0, Customer::where('email', 'not like', '%@demo.invalid')->count());
    }

    public function test_the_undisclosed_ruby_cannot_be_listed(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        $ruby = Product::where('title', 'like', '%Ruby%')->firstOrFail();

        // Left approved rather than listed on purpose, so the compliance gate
        // is something the client meets rather than reads about.
        $this->assertSame('approved', $ruby->status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be listed until its stones are disclosed');

        app(ProductIntakeService::class)->list($ruby);
    }

    public function test_the_disclosed_stones_do_not_block_their_pieces(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        // A heated sapphire and an oiled emerald are both disclosable and both
        // disclosed, so they are on the shop.
        foreach (['Sapphire', 'Emerald'] as $stone) {
            $this->assertSame(
                'listed',
                Product::where('title', 'like', "%{$stone}%")->firstOrFail()->status,
            );
        }
    }

    public function test_it_refuses_to_run_twice(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        $this->artisan('jt:demo-data --force')
            ->expectsOutputToContain('already loaded')
            ->assertFailed();

        $this->assertSame(12, Product::where('sku', 'like', 'DEMO-%')->count());
    }

    public function test_purge_removes_what_it_made(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();
        $this->artisan('jt:demo-data --purge --force')->assertSuccessful();

        $this->assertSame(0, Product::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Commission::count());
        $this->assertSame(0, Override::count());
        $this->assertSame(0, InventoryLock::count());
    }

    public function test_purge_leaves_a_real_record_alone(): void
    {
        $real = Product::factory()->listed()->create(['sku' => 'EST-0001']);
        $customer = Customer::create(['name' => 'A real buyer', 'email' => 'buyer@example.com']);

        $this->artisan('jt:demo-data --force')->assertSuccessful();
        $this->artisan('jt:demo-data --purge --force')->assertSuccessful();

        $this->assertTrue($real->exists());
        $this->assertDatabaseHas('products', ['sku' => 'EST-0001']);
        $this->assertDatabaseHas('customers', ['email' => 'buyer@example.com']);
        $this->assertSame(1, Product::count());
    }

    public function test_purge_keeps_the_audit_trail(): void
    {
        $this->artisan('jt:demo-data --force')->assertSuccessful();

        $before = \App\Models\AuditLog::count();
        $this->assertGreaterThan(0, $before);

        $this->artisan('jt:demo-data --purge --force')->assertSuccessful();

        // A log that can be cleared is not a log. Entries about demo records
        // are still a true account of what happened to this database.
        $this->assertGreaterThanOrEqual($before, \App\Models\AuditLog::count());
    }

    public function test_purge_on_a_clean_system_says_so_rather_than_failing(): void
    {
        $this->artisan('jt:demo-data --purge --force')
            ->expectsOutputToContain('no demo data')
            ->assertSuccessful();
    }
}
