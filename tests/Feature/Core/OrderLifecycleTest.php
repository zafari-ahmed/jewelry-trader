<?php

namespace Tests\Feature\Core;

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function stockedProduct(Location $location, int $priceCents = 680000): Product
    {
        $product = Product::factory()->listed()->create();
        \App\Models\Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $location->id]);

        return $product;
    }

    public function test_order_numbers_are_human_readable_and_sequential_per_year(): void
    {
        $location = Location::factory()->create();
        $service = app(OrderService::class);

        $first = $service->create([['description' => 'Ring sizing', 'price_cents' => 14500]], $location->id, 'pos');
        $second = $service->create([['description' => 'Ring sizing', 'price_cents' => 14500]], $location->id, 'pos');

        $year = now()->format('Y');

        $this->assertSame("ORD-{$year}-000001", $first->order_number);
        $this->assertSame("ORD-{$year}-000002", $second->order_number);
    }

    public function test_totals_are_computed_from_lines_with_tax_applied_after_discount(): void
    {
        $location = Location::factory()->create(['tax_rate' => 0.08875, 'state' => 'NY']);
        $product = $this->stockedProduct($location);

        $order = app(OrderService::class)->create([
            ['product_id' => $product->id, 'price_cents' => 680000, 'discount_cents' => 30000],
            ['description' => 'Ring sizing to 6 ¼', 'price_cents' => 14500],
        ], $location->id, 'pos', taxRate: 0.08875, taxState: 'NY');

        $this->assertSame(694500, $order->subtotal_cents);
        $this->assertSame(30000, $order->discount_total_cents);
        // (694500 - 30000) * 0.08875 = 58974.375 -> 58974
        $this->assertSame(58974, $order->tax_cents);
        $this->assertSame(723474, $order->total_cents);
    }

    public function test_a_service_line_needs_no_product(): void
    {
        $location = Location::factory()->create();

        $order = app(OrderService::class)->create(
            [['description' => 'Ring sizing to 6 ¼', 'price_cents' => 14500]],
            $location->id,
            'pos',
        );

        $item = $order->items->first();

        $this->assertNull($item->product_id);
        $this->assertFalse($item->isInventoryLine());
        $this->assertSame('Ring sizing to 6 ¼', $item->description);
    }

    public function test_paying_an_order_flips_stock_to_sold_in_the_same_transaction(): void
    {
        $location = Location::factory()->create();
        $product = $this->stockedProduct($location);
        $order = app(OrderService::class)->create([['product_id' => $product->id, 'price_cents' => 680000]], $location->id, 'pos');

        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'stripe', 'amount' => 680000,
            'currency' => 'USD', 'status' => 'succeeded', 'method' => 'card',
        ]);

        $paid = app(OrderService::class)->markPaid($order, $payment);

        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('sold', $product->fresh()->status);
        $this->assertSame('sold', InventoryStock::where('product_id', $product->id)->value('status'));
        $this->assertSame($order->id, $payment->fresh()->order_id);
    }

    public function test_marking_an_already_paid_order_paid_again_is_a_no_op(): void
    {
        $location = Location::factory()->create();
        $product = $this->stockedProduct($location);
        $order = app(OrderService::class)->create([['product_id' => $product->id, 'price_cents' => 680000]], $location->id, 'pos');

        app(OrderService::class)->markPaid($order);
        $again = app(OrderService::class)->markPaid($order->fresh());

        $this->assertSame('paid', $again->status);
        $this->assertSame(1, Order::where('status', 'paid')->count());
    }

    public function test_refunding_an_order_returns_the_item_to_the_shelf(): void
    {
        $location = Location::factory()->create();
        $product = $this->stockedProduct($location);
        $order = app(OrderService::class)->create([['product_id' => $product->id, 'price_cents' => 680000]], $location->id, 'pos');

        app(OrderService::class)->markPaid($order);
        $refunded = app(OrderService::class)->restock($order->fresh(), 'refunded');

        $this->assertSame('refunded', $refunded->status);
        $this->assertSame('in_stock', InventoryStock::where('product_id', $product->id)->value('status'));
        $this->assertSame('listed', $product->fresh()->status);
    }

    public function test_a_walk_in_order_needs_no_customer(): void
    {
        $location = Location::factory()->create();

        $order = app(OrderService::class)->create([['description' => 'Repair', 'price_cents' => 5000]], $location->id, 'pos');

        $this->assertNull($order->customer_id);
    }

    public function test_pos_and_storefront_share_one_customer_record_matched_on_email(): void
    {
        $fromPos = Customer::findOrCreateByEmail('a.delacroix@example.com', ['name' => 'Anne Delacroix']);
        $fromWeb = Customer::findOrCreateByEmail('a.delacroix@example.com', ['name' => 'Anne Delacroix', 'phone' => '(917) 555-0142']);

        $this->assertSame($fromPos->id, $fromWeb->id);
        $this->assertSame(1, Customer::count());
        $this->assertSame('(917) 555-0142', $fromWeb->phone);
    }

    public function test_the_return_window_comes_from_settings(): void
    {
        $location = Location::factory()->create();
        $order = app(OrderService::class)->create([['description' => 'Item', 'price_cents' => 1000]], $location->id, 'pos');
        $order->update(['paid_at' => now()->subDays(20)]);

        \App\Models\Setting::set('pos.return_window_days', 30);
        $this->assertTrue($order->fresh()->isWithinReturnWindow());

        \App\Models\Setting::set('pos.return_window_days', 14);
        $this->assertFalse($order->fresh()->isWithinReturnWindow());
    }

    public function test_an_order_is_audited_as_a_financial_record(): void
    {
        $location = Location::factory()->create();
        app(OrderService::class)->create([['description' => 'Item', 'price_cents' => 1000]], $location->id, 'pos');

        $this->assertDatabaseHas('audit_logs', ['action' => 'order.created', 'category' => 'financial']);
    }
}
