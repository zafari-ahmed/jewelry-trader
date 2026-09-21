<?php

namespace Tests\Feature\Core;

use App\Exceptions\ItemNoLongerAvailableException;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Services\Orders\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CLAUDE.md Module 4 acceptance: two staff cannot both sell the same
 * one-of-a-kind item.
 *
 * DatabaseTruncation rather than RefreshDatabase: this test needs real
 * committed rows and real row locks, which a wrapping transaction would hide.
 */
class ConcurrentSaleTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        // These rows are committed, not rolled back: leaving them behind would
        // corrupt the sequential order-number test that runs later.
        foreach (['payments', 'order_items', 'orders', 'inventory_stock', 'pricing', 'products', 'locations'] as $table) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table($table)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        parent::tearDown();
    }

    private function listedProduct(Location $location): Product
    {
        $product = Product::factory()->listed()->create();
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 680000]);
        InventoryStock::factory()->create([
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity' => 1,
            'status' => 'in_stock',
        ]);

        return $product;
    }

    private function orderFor(Product $product, Location $location): Order
    {
        return app(OrderService::class)->create(
            [['product_id' => $product->id, 'price_cents' => 680000]],
            $location->id,
            'pos',
        );
    }

    public function test_two_concurrent_processes_cannot_both_sell_the_same_item(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required to run two real concurrent requests.');
        }

        $location = Location::factory()->create();
        $product = $this->listedProduct($location);

        $orderA = $this->orderFor($product, $location);
        $orderB = $this->orderFor($product, $location);

        // Both children wait for the same wall-clock moment before attempting,
        // so the two transactions genuinely overlap rather than running in turn.
        $startAt = microtime(true) + 0.25;

        $pids = [];

        foreach ([$orderA, $orderB] as $order) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // Child: its own connection, its own attempt at the same item.
                DB::purge();
                DB::reconnect();

                usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));

                try {
                    app(OrderService::class)->markPaid(Order::findOrFail($order->id));
                    exit(0);   // sold it
                } catch (ItemNoLongerAvailableException) {
                    exit(1);   // lost the race, correctly refused
                } catch (\Throwable) {
                    exit(2);   // anything else is a failure of the guarantee
                }
            }

            $pids[] = $pid;
        }

        $codes = [];

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $codes[] = pcntl_wexitstatus($status);
        }

        sort($codes);

        $this->assertSame([0, 1], $codes, 'Exactly one sale should succeed and one should be refused.');

        // And the books agree: one paid order, one still pending, stock sold once.
        $this->assertSame(1, Order::where('status', 'paid')->count());
        $this->assertSame(1, Order::where('status', 'pending')->count());

        $stock = InventoryStock::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('sold', $stock->status);
        $this->assertSame(0, $stock->quantity);
        $this->assertSame('sold', $product->fresh()->status);
    }

    public function test_a_second_sale_of_a_sold_item_is_refused_even_sequentially(): void
    {
        $location = Location::factory()->create();
        $product = $this->listedProduct($location);

        $orderA = $this->orderFor($product, $location);
        $orderB = $this->orderFor($product, $location);

        app(OrderService::class)->markPaid($orderA);

        $this->expectException(ItemNoLongerAvailableException::class);

        app(OrderService::class)->markPaid($orderB);
    }

    public function test_a_failed_sale_leaves_no_partial_write(): void
    {
        $location = Location::factory()->create();
        $available = $this->listedProduct($location);
        $claimed = $this->listedProduct($location);

        // Someone else buys the second item first.
        app(OrderService::class)->markPaid($this->orderFor($claimed, $location));

        // An order containing both items must fail whole: the available item
        // must not be flipped to sold on the way to discovering the problem.
        $order = app(OrderService::class)->create([
            ['product_id' => $available->id, 'price_cents' => 100000],
            ['product_id' => $claimed->id, 'price_cents' => 680000],
        ], $location->id, 'pos');

        try {
            app(OrderService::class)->markPaid($order);
            $this->fail('The sale should have been refused.');
        } catch (ItemNoLongerAvailableException) {
            // expected
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('in_stock', InventoryStock::where('product_id', $available->id)->value('status'));
        $this->assertSame('listed', $available->fresh()->status);
    }
}
