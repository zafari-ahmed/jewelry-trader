<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Register;
use App\Livewire\Pos\Returns;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\Stripe\StripeApi;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    private FakeStripeApi $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);

        $this->location = Location::factory()->create(['tax_rate' => 0.08875, 'state' => 'NY']);
        $this->staff = User::factory()->create(['location_id' => $this->location->id]);
        $this->staff->assignRole('store-manager');
    }

    private function listed(int $priceCents, string $title): Product
    {
        $product = Product::factory()->listed()->create(['title' => $title]);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        return $product;
    }

    private function soldOrder(array $products): Order
    {
        $component = Livewire::actingAs($this->staff)->test(Register::class);

        foreach ($products as $product) {
            $component->call('addProduct', $product->id);
        }

        $component->call('goToPayment')->call('charge');

        return Order::latest('id')->firstOrFail();
    }

    public function test_a_returned_item_is_refunded_and_goes_back_on_the_shelf(): void
    {
        $product = $this->listed(100000, 'Retro Ruby Cocktail Ring');
        $order = $this->soldOrder([$product]);

        Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$order->items->first()->id])
            ->call('refundSelected')
            ->assertSet('error', null);

        $order->refresh();

        $this->assertSame('refunded', $order->status);
        $this->assertSame('in_stock', InventoryStock::where('product_id', $product->id)->value('status'));
        $this->assertSame('listed', $product->fresh()->status);
        $this->assertSame(1, $this->stripe->callCount('refundPaymentIntent'));
    }

    public function test_returning_one_line_of_two_is_a_partial_refund(): void
    {
        $ring = $this->listed(100000, 'Ring');
        $brooch = $this->listed(50000, 'Brooch');
        $order = $this->soldOrder([$ring, $brooch]);

        $ringItem = $order->items->firstWhere('product_id', $ring->id);

        Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$ringItem->id])
            ->call('refundSelected')
            ->assertSet('error', null);

        $order->refresh();

        $this->assertSame('partially_refunded', $order->status);
        // Only the returned piece comes back; the other stays sold.
        $this->assertSame('in_stock', InventoryStock::where('product_id', $ring->id)->value('status'));
        $this->assertSame('sold', InventoryStock::where('product_id', $brooch->id)->value('status'));
    }

    public function test_the_refund_includes_the_tax_that_was_charged(): void
    {
        $product = $this->listed(100000, 'Ring');
        $order = $this->soldOrder([$product]);

        $component = Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$order->items->first()->id]);

        // $1,000 plus 8.875% tax.
        $this->assertSame(108875, $component->get('creditCents'));
    }

    public function test_a_return_outside_the_window_is_refused_without_authority(): void
    {
        Setting::set('pos.return_window_days', 30);

        $product = $this->listed(100000, 'Ring');
        $order = $this->soldOrder([$product]);
        $order->update(['paid_at' => now()->subDays(45)]);

        $salesStaff = User::factory()->create(['location_id' => $this->location->id]);
        $salesStaff->assignRole('sales-staff');
        $salesStaff->givePermissionTo('process-refunds');

        Livewire::actingAs($salesStaff->fresh())
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$order->items->first()->id])
            ->call('refundSelected')
            ->assertSee('outside the 30-day return window');

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_an_exchange_for_a_dearer_item_collects_the_difference(): void
    {
        $returned = $this->listed(100000, 'Ring being returned');
        $replacement = $this->listed(150000, 'Dearer replacement');

        $order = $this->soldOrder([$returned]);

        Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$order->items->first()->id])
            ->set('mode', 'exchange')
            ->call('addExchangeItem', $replacement->id)
            ->call('completeExchange')
            ->assertSet('error', null)
            ->assertSee('customer paid');

        // The returned piece is back on the shelf, the new one is sold.
        $this->assertSame('in_stock', InventoryStock::where('product_id', $returned->id)->value('status'));
        $this->assertSame('sold', InventoryStock::where('product_id', $replacement->id)->value('status'));

        $newOrder = Order::latest('id')->firstOrFail();
        $this->assertSame('paid', $newOrder->status);
        // $1,500 tax = 13312.5c, rounded half-up to 13313c.
        $this->assertSame(163313, $newOrder->total_cents);
    }

    public function test_an_exchange_for_a_cheaper_item_refunds_the_difference(): void
    {
        $returned = $this->listed(150000, 'Dearer ring returned');
        $replacement = $this->listed(100000, 'Cheaper replacement');

        $order = $this->soldOrder([$returned]);

        Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', [$order->items->first()->id])
            ->set('mode', 'exchange')
            ->call('addExchangeItem', $replacement->id)
            ->call('completeExchange')
            ->assertSet('error', null)
            ->assertSee('refunded');

        $this->assertSame('paid', Order::latest('id')->first()->status);
    }

    public function test_an_unknown_order_number_reports_clearly(): void
    {
        Livewire::actingAs($this->staff)
            ->test(Returns::class)
            ->set('orderNumber', 'ORD-2026-999999')
            ->call('lookup')
            ->assertSee('No paid order matches');
    }

    public function test_staff_without_refund_permission_are_refused(): void
    {
        $salesStaff = User::factory()->create(['location_id' => $this->location->id]);
        $salesStaff->assignRole('sales-staff');

        Livewire::actingAs($salesStaff)->test(Returns::class)->assertForbidden();
    }
}
