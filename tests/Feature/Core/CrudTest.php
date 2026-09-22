<?php

namespace Tests\Feature\Core;

use App\Livewire\Customers\CustomerList;
use App\Livewire\Inventory\ProductIntake;
use App\Livewire\Inventory\ProductList;
use App\Livewire\Orders\OrderList;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Module 4 acceptance: CRUD works for all four core entities, and every list
 * is filterable by location, status and category.
 */
class CrudTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->location = Location::factory()->create(['name' => 'Madison Ave']);
        $this->manager = User::factory()->create(['location_id' => $this->location->id]);
        $this->manager->assignRole('store-manager');
    }

    public function test_a_product_can_be_created_with_pricing_and_stock(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ProductIntake::class)
            ->set('values.sku', 'EST-9001')
            ->set('values.title', 'Edwardian Diamond Cluster Ring')
            ->set('values.category', 'rings')
            ->set('values.metal_type', '950 Platinum')
            ->set('values.retail_price', '6800.00')
            ->set('values.acquisition_value', '3100.00')
            ->set('values.location_id', (string) $this->location->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'EST-9001')->firstOrFail();

        // Money is stored in cents, so $6,800.00 is 680000.
        $this->assertSame(680000, $product->currentPricing->retail_price_cents);
        $this->assertSame(310000, $product->currentPricing->acquisition_value_cents);
        $this->assertSame('in_stock', $product->stock()->value('status'));
    }

    public function test_a_duplicate_sku_is_rejected(): void
    {
        Product::factory()->create(['sku' => 'EST-9002']);

        Livewire::actingAs($this->manager)
            ->test(ProductIntake::class)
            ->set('values.sku', 'EST-9002')
            ->set('values.title', 'Another ring')
            ->call('saveDraft')
            ->assertHasErrors('values.sku');
    }

    public function test_editing_a_price_appends_history_rather_than_overwriting(): void
    {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);
        \App\Models\Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 725000]);

        Livewire::actingAs($this->manager)
            ->test(ProductIntake::class, ['product' => $product])
            ->set('values.retail_price', '6800.00')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertSame(2, $product->pricing()->count());
        $this->assertSame(680000, $product->fresh()->currentPricing->retail_price_cents);
    }

    public function test_a_product_can_be_deleted(): void
    {
        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        $product->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_the_inventory_list_filters_by_location_status_and_category(): void
    {
        $other = Location::factory()->create(['name' => 'Greenwich']);

        $here = Product::factory()->listed()->create(['title' => 'Madison ring', 'category' => 'rings']);
        InventoryStock::factory()->create(['product_id' => $here->id, 'location_id' => $this->location->id]);

        $there = Product::factory()->create(['title' => 'Greenwich brooch', 'category' => 'brooches', 'status' => 'draft']);
        InventoryStock::factory()->create(['product_id' => $there->id, 'location_id' => $other->id]);

        $list = Livewire::actingAs($this->manager)->test(ProductList::class);

        $list->assertSee('Madison ring')->assertSee('Greenwich brooch');

        $list->set('locationId', (string) $this->location->id)
            ->assertSee('Madison ring')
            ->assertDontSee('Greenwich brooch');

        $list->set('locationId', '')->set('status', 'draft')
            ->assertSee('Greenwich brooch')
            ->assertDontSee('Madison ring');

        $list->set('status', '')->set('category', 'rings')
            ->assertSee('Madison ring')
            ->assertDontSee('Greenwich brooch');

        $list->set('category', '')->set('search', 'brooch')
            ->assertSee('Greenwich brooch')
            ->assertDontSee('Madison ring');
    }

    public function test_a_customer_can_be_created_edited_and_deleted(): void
    {
        $component = Livewire::actingAs($this->manager)
            ->test(CustomerList::class)
            ->set('form.name', 'Anne Delacroix')
            ->set('form.email', 'a.delacroix@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $customer = Customer::where('email', 'a.delacroix@example.com')->firstOrFail();

        $component->call('edit', $customer->id)
            ->set('form.phone', '(917) 555-0142')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('(917) 555-0142', $customer->fresh()->phone);

        $component->call('delete', $customer->id);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_a_duplicate_customer_email_is_rejected(): void
    {
        Customer::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($this->manager)
            ->test(CustomerList::class)
            ->set('form.name', 'Someone else')
            ->set('form.email', 'taken@example.com')
            ->call('save')
            ->assertHasErrors('form.email');
    }

    public function test_the_order_list_filters_by_status_channel_and_location(): void
    {
        $service = app(OrderService::class);

        $pos = $service->create([['description' => 'Ring', 'price_cents' => 100000]], $this->location->id, 'pos');
        $web = $service->create([['description' => 'Bracelet', 'price_cents' => 200000]], $this->location->id, 'web');
        $web->update(['status' => 'paid']);

        $list = Livewire::actingAs($this->manager)->test(OrderList::class);

        $list->assertSee($pos->order_number)->assertSee($web->order_number);

        $list->set('channel', 'web')
            ->assertSee($web->order_number)
            ->assertDontSee($pos->order_number);

        $list->set('channel', '')->set('status', 'pending')
            ->assertSee($pos->order_number)
            ->assertDontSee($web->order_number);

        $list->set('status', '')->set('search', $web->order_number)
            ->assertSee($web->order_number)
            ->assertDontSee($pos->order_number);
    }

    public function test_a_user_without_permission_cannot_open_the_lists(): void
    {
        $customerService = User::factory()->create(['location_id' => $this->location->id]);
        $customerService->assignRole('customer-service');

        // Customer Service may view orders but not create or edit inventory.
        Livewire::actingAs($customerService)->test(ProductIntake::class)->assertForbidden();

        $accountant = User::factory()->create(['location_id' => $this->location->id]);
        $accountant->assignRole('accountant');

        Livewire::actingAs($accountant)->test(ProductIntake::class)->assertForbidden();
    }
}
