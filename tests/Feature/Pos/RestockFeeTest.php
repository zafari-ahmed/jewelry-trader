<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Register;
use App\Livewire\Pos\Returns;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\RestockFee;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Payments\Tender;
use App\Services\Pos\Cart;
use App\Services\Pos\PosSaleService;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class RestockFeeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $this->app->instance(StripeApi::class, new FakeStripeApi);

        // A round tax rate, so the arithmetic in the assertions is readable.
        $this->location = Location::factory()->create(['tax_rate' => 0.10, 'state' => 'NY']);
        $this->manager = User::factory()->create(['location_id' => $this->location->id]);
        $this->manager->assignRole('store-manager');

        Setting::set('pos.restock_fee_percent', 10);
        Setting::set('pos.restock_fee_cap_cents', 0);
    }

    private function listed(int $priceCents, string $title = 'A piece'): Product
    {
        $product = Product::factory()->listed()->create(['title' => $title]);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        return $product;
    }

    private function soldOrder(array $products): Order
    {
        $component = Livewire::actingAs($this->manager)->test(Register::class);

        foreach ($products as $product) {
            $component->call('addProduct', $product->id);
        }

        $component->call('goToPayment')->call('charge');

        return Order::latest('id')->firstOrFail();
    }

    public function test_the_fee_is_withheld_from_the_refund(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        // $200 goods + $20 tax = $220 paid. The fee is 10% of the goods, $20.
        $this->assertSame(22000, $order->total_cents);

        app(PosSaleService::class)->refund($order, userId: $this->manager->id);

        $this->assertSame(20000, (int) $order->payments()->sum('amount_refunded'));
    }

    public function test_the_whole_of_the_tax_comes_back_even_though_the_fee_is_kept(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        app(PosSaleService::class)->refund($order, userId: $this->manager->id);

        $refunded = (int) $order->payments()->sum('amount_refunded');

        // $220 paid, $20 withheld from the goods. The $20 of tax is not
        // touched: the sale is reversed, so the tax is owed back in full.
        // Pro-rating it would mean keeping tax that still has to be remitted.
        $this->assertSame(22000 - 2000, $refunded);
        $this->assertSame(18000 + 2000, $refunded);
    }

    public function test_the_cap_limits_the_fee_on_a_high_value_piece(): void
    {
        Setting::set('pos.restock_fee_cap_cents', 25000);

        $order = $this->soldOrder([$this->listed(1200000)]);   // a $12,000 ring

        app(PosSaleService::class)->refund($order, userId: $this->manager->id);

        // 10% would be $1,200. The cap holds it at $250.
        $this->assertSame(25000, RestockFee::latest('id')->first()->amount_cents);
    }

    public function test_no_fee_is_charged_when_the_percentage_is_zero(): void
    {
        Setting::set('pos.restock_fee_percent', 0);

        $order = $this->soldOrder([$this->listed(20000)]);

        app(PosSaleService::class)->refund($order, userId: $this->manager->id);

        $this->assertSame(22000, (int) $order->payments()->sum('amount_refunded'));
        $this->assertSame(0, RestockFee::count());
    }

    public function test_an_exchange_carries_no_fee_by_default(): void
    {
        $returned = $this->listed(20000, 'Returned');
        $replacement = $this->listed(20000, 'Replacement');
        $order = $this->soldOrder([$returned]);

        $cart = new Cart($this->location->id);
        $cart->addProduct($replacement);

        $result = app(PosSaleService::class)->exchange(
            order: $order,
            returnedItemIds: $order->items->pluck('id')->all(),
            newCart: $cart,
            userId: $this->manager->id,
        );

        // An even swap stays even: the money never left the business, so there
        // is nothing to charge for.
        $this->assertSame(0, $result['difference_cents']);
        $this->assertSame(22000, $result['credit_cents']);
    }

    public function test_an_exchange_can_be_made_to_carry_the_fee(): void
    {
        Setting::set('pos.restock_fee_applies_to_exchanges', true);

        $returned = $this->listed(20000, 'Returned');
        $replacement = $this->listed(20000, 'Replacement');
        $order = $this->soldOrder([$returned]);

        $cart = new Cart($this->location->id);
        $cart->addProduct($replacement);

        $result = app(PosSaleService::class)->exchange(
            order: $order,
            returnedItemIds: $order->items->pluck('id')->all(),
            newCart: $cart,
            // The fee turns an even swap into $20 owed, so it has to be
            // tendered — the service will not let the books go short.
            tenders: [Tender::cash(2000, 2000)],
            userId: $this->manager->id,
        );

        $this->assertSame(20000, $result['credit_cents']);
        $this->assertSame(2000, $result['difference_cents']);
    }

    public function test_a_waiver_needs_a_configured_reason(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a configured reason');

        app(PosSaleService::class)->refund($order, waiverReason: 'because I said so');
    }

    public function test_a_waived_fee_refunds_in_full_and_keeps_the_reason(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        app(PosSaleService::class)->refund(
            $order,
            waiverReason: 'Item defective',
            userId: $this->manager->id,
        );

        $this->assertSame(22000, (int) $order->payments()->sum('amount_refunded'));

        $fee = RestockFee::latest('id')->firstOrFail();
        $this->assertTrue($fee->wasWaived());
        $this->assertSame('Item defective', $fee->waiver_reason);
        $this->assertSame($this->manager->id, $fee->created_by);
    }

    public function test_the_basis_is_recorded_so_the_fee_can_be_explained_later(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        app(PosSaleService::class)->refund($order, userId: $this->manager->id);

        $fee = RestockFee::latest('id')->firstOrFail();

        // The percentage behind it is a setting that will move, so the figure
        // it was applied to is kept alongside the result.
        $this->assertSame(20000, $fee->goods_cents);
        $this->assertSame(2000, $fee->amount_cents);
        $this->assertSame('10.00', $fee->percent_applied);
    }

    public function test_staff_without_the_permission_cannot_waive_it(): void
    {
        $clerk = User::factory()->create(['location_id' => $this->location->id]);
        $clerk->assignRole('customer-service');

        $order = $this->soldOrder([$this->listed(20000)]);

        // The field exists in the browser's state, so the gate has to be on
        // the server: this role processes returns but does not discount.
        $component = Livewire::actingAs($clerk)->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', $order->items->pluck('id')->all())
            ->set('waiverReason', 'Item defective');

        $this->assertSame([], $component->get('waiverOptions'));

        $component->call('refundSelected');

        $this->assertSame(20000, (int) $order->payments()->sum('amount_refunded'));
        $this->assertFalse(RestockFee::latest('id')->firstOrFail()->wasWaived());
    }

    public function test_a_manager_sees_the_fee_before_issuing_the_refund(): void
    {
        $order = $this->soldOrder([$this->listed(20000)]);

        $component = Livewire::actingAs($this->manager)->test(Returns::class)
            ->set('orderNumber', $order->order_number)
            ->call('lookup')
            ->set('selectedItems', $order->items->pluck('id')->all());

        // Shown before the button is pressed: a fee the customer hears about
        // afterwards is the one that gets disputed.
        $this->assertSame(2000, $component->get('restockFeeCents'));
        $this->assertSame(22000, $component->get('grossCredit'));
        $this->assertSame(20000, $component->get('creditCents'));
        $component->assertSeeText('Restocking fee');
    }
}
