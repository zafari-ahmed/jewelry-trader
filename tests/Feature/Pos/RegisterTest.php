<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\Register;
use App\Models\Customer;
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
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $this->app->instance(StripeApi::class, new FakeStripeApi);

        $this->location = Location::factory()->create(['tax_rate' => 0.08875, 'state' => 'NY']);
        $this->staff = User::factory()->create(['location_id' => $this->location->id]);
        $this->staff->assignRole('sales-staff');
    }

    private function listed(int $priceCents = 680000, string $title = 'Edwardian Diamond Cluster Ring'): Product
    {
        $product = Product::factory()->listed()->create(['title' => $title]);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        return $product;
    }

    public function test_a_complete_card_sale_marks_the_item_sold(): void
    {
        $product = $this->listed();

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->call('charge')
            ->assertSet('screen', 'receipt')
            ->assertSet('error', null);

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertSame('pos', $order->channel);
        $this->assertSame(740350, $order->total_cents);   // 6800 + 8.875% tax
        $this->assertSame('sold', $product->fresh()->status);
        $this->assertSame('sold', InventoryStock::where('product_id', $product->id)->value('status'));
    }

    public function test_a_sale_completes_in_well_under_two_minutes(): void
    {
        $product = $this->listed();

        $started = microtime(true);

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->set('search', 'Edwardian')
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->call('charge')
            ->assertSet('screen', 'receipt');

        // Four interactions, server-side, against the two-minute budget.
        $this->assertLessThan(20, microtime(true) - $started);
    }

    public function test_a_custom_service_line_needs_no_product(): void
    {
        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->set('customLineDescription', 'Ring sizing to 6 ¼')
            ->set('customLinePrice', '145.00')
            ->call('addCustomLine')
            ->call('goToPayment')
            ->call('charge')
            ->assertSet('screen', 'receipt');

        $item = Order::latest('id')->firstOrFail()->items->first();

        $this->assertNull($item->product_id);
        $this->assertSame(14500, $item->price_cents);
    }

    public function test_a_split_of_card_and_cash_records_both_tenders(): void
    {
        $product = $this->listed(100000);   // $1,000 + 8.875% = $1,088.75

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->set('tenders', [
                ['method' => 'card', 'amount' => '1000.00'],
                ['method' => 'cash', 'amount' => '88.75'],
            ])
            ->set('cashTendered', '100.00')
            ->call('charge')
            ->assertSet('screen', 'receipt');

        $payment = Order::latest('id')->firstOrFail()->payments->first();

        $this->assertSame('split', $payment->method);
        $this->assertSame(['card', 'cash'], $payment->splits->pluck('method')->all());
        $this->assertSame(108875, (int) $payment->splits->sum('amount'));
        $this->assertSame(1125, $payment->splits->firstWhere('method', 'cash')->raw_response['change']);
    }

    public function test_tenders_must_equal_the_sale_total(): void
    {
        $product = $this->listed();

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->set('tenders', [['method' => 'cash', 'amount' => '10.00']])
            ->call('charge')
            ->assertSet('screen', 'payment');

        $this->assertSame(0, Order::where('status', 'paid')->count());
    }

    public function test_a_discount_within_the_ceiling_is_applied(): void
    {
        $product = $this->listed(100000);

        $component = Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id);

        $key = $component->get('cartState')[0]['key'];

        $component->set('discountLine', ['key' => $key, 'percent' => '10', 'fixed' => ''])
            ->call('applyDiscount')
            ->assertSet('error', null);

        $this->assertSame(10000, $component->get('cartState')[0]['discount_cents']);
    }

    public function test_a_discount_above_the_ceiling_needs_approval(): void
    {
        Setting::set('pos.max_staff_discount_percent', 10);
        $product = $this->listed(100000);

        $component = Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id);

        $key = $component->get('cartState')[0]['key'];

        $component->set('discountLine', ['key' => $key, 'percent' => '25', 'fixed' => ''])
            ->call('applyDiscount');

        // Refused, and the cart is unchanged.
        $this->assertStringContainsString("manager's approval", $component->get('error'));
        $this->assertSame(0, $component->get('cartState')[0]['discount_cents']);
    }

    public function test_a_manager_may_discount_above_the_ceiling(): void
    {
        $manager = User::factory()->create(['location_id' => $this->location->id]);
        $manager->assignRole('store-manager');

        $product = $this->listed(100000);

        $component = Livewire::actingAs($manager)
            ->test(Register::class)
            ->call('addProduct', $product->id);

        $key = $component->get('cartState')[0]['key'];

        $component->set('discountLine', ['key' => $key, 'percent' => '25', 'fixed' => ''])
            ->call('applyDiscount')
            ->assertSet('error', null);

        $this->assertSame(25000, $component->get('cartState')[0]['discount_cents']);
    }

    public function test_an_item_already_sold_cannot_be_added(): void
    {
        $product = $this->listed();
        InventoryStock::where('product_id', $product->id)->update(['status' => 'sold']);

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->assertSee('is not available for sale');
    }

    public function test_the_same_piece_cannot_be_added_twice(): void
    {
        $product = $this->listed();

        $component = Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id);

        $this->assertCount(1, $component->get('cartState'));
    }

    public function test_a_receipt_is_emailed_when_a_customer_email_is_on_file(): void
    {
        Mail::fake();

        $product = $this->listed();

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->set('customerName', 'Anne Delacroix')
            ->set('customerEmail', 'a.delacroix@example.com')
            ->call('charge')
            ->call('emailReceipt')
            ->assertSet('emailed', true);

        Mail::assertSent(\App\Mail\ReceiptMail::class);
        $this->assertDatabaseHas('customers', ['email' => 'a.delacroix@example.com']);
    }

    public function test_a_walk_in_sale_needs_no_customer(): void
    {
        $product = $this->listed();

        Livewire::actingAs($this->staff)
            ->test(Register::class)
            ->call('addProduct', $product->id)
            ->call('goToPayment')
            ->call('charge')
            ->assertSet('screen', 'receipt');

        $this->assertNull(Order::latest('id')->first()->customer_id);
        $this->assertSame(0, Customer::count());
    }

    public function test_staff_without_pos_permission_are_refused(): void
    {
        $accountant = User::factory()->create(['location_id' => $this->location->id]);
        $accountant->assignRole('accountant');

        Livewire::actingAs($accountant)->test(Register::class)->assertForbidden();
    }
}
