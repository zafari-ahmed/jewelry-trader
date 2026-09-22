<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Account;
use App\Livewire\Shop\Checkout;
use App\Livewire\Shop\ProductDetail;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Orders\OrderService;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Storefront\StorefrontCart;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeApi $stripe;

    private Location $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);

        // A physical store holds the stock; web orders belong to the Web location.
        $this->store = Location::factory()->create(['state' => 'NY', 'tax_rate' => 0.08875]);
        Location::factory()->create(['name' => 'Web', 'slug' => 'web', 'is_web' => true, 'tax_rate' => 0]);
    }

    private function listed(int $priceCents = 1240000): Product
    {
        $product = Product::factory()->listed()->create(['title' => 'Art Deco Sapphire Line Bracelet']);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->store->id]);

        return $product;
    }

    private function filledCheckout(Product $product)
    {
        app(StorefrontCart::class)->add($product);

        return Livewire::test(Checkout::class)
            ->set('form.email', 'a.delacroix@example.com')
            ->set('form.name', 'Anne Delacroix')
            ->set('form.street', '88 Wooster Street')
            ->set('form.city', 'New York')
            ->set('form.state', 'NY')
            ->set('form.postal_code', '10012');
    }

    public function test_tax_follows_the_shipping_address_state(): void
    {
        $product = $this->listed(100000);
        app(StorefrontCart::class)->add($product);

        $component = Livewire::test(Checkout::class)->set('form.state', 'NY');
        $this->assertSame(8875, $component->get('taxCents'));

        // A state with no store charges nothing rather than guessing.
        $component->set('form.state', 'TX');
        $this->assertSame(0, $component->get('taxCents'));
    }

    public function test_starting_payment_creates_a_pending_web_order_and_an_intent(): void
    {
        $product = $this->listed(100000);

        $component = $this->filledCheckout($product)->call('startPayment')->assertHasNoErrors();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame('web', $order->channel);
        $this->assertSame('pending', $order->status);
        $this->assertSame(108875, $order->total_cents);
        // Web orders belong to the Web location (docs/DECISIONS.md).
        $this->assertTrue($order->location->is_web);
        $this->assertNotNull($component->get('clientSecret'));

        // No stock has moved yet: the customer has not paid.
        $this->assertSame('in_stock', InventoryStock::where('product_id', $product->id)->value('status'));
    }

    public function test_an_incomplete_address_blocks_payment(): void
    {
        app(StorefrontCart::class)->add($this->listed());

        Livewire::test(Checkout::class)
            ->set('form.email', 'not-an-email')
            ->call('startPayment')
            ->assertHasErrors(['form.email', 'form.name', 'form.street']);

        $this->assertSame(0, Order::count());
    }

    public function test_a_confirmed_payment_completes_the_order_and_sells_the_piece(): void
    {
        $product = $this->listed(100000);
        $component = $this->filledCheckout($product)->call('startPayment');

        $intentId = $component->get('intentId');
        // Stand in for the browser confirming with Stripe.
        $this->stripe->intents[$intentId]['status'] = 'succeeded';
        $this->stripe->intents[$intentId]['amount_received'] = 108875;

        $component->call('confirmPayment', $intentId);

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame('paid', $order->status);
        $this->assertSame('sold', $product->fresh()->status);
        // The piece sits at a store, not the Web location — it must still sell.
        $this->assertSame('sold', InventoryStock::where('product_id', $product->id)->value('status'));
        $this->assertSame(0, app(StorefrontCart::class)->count());
    }

    public function test_an_unconfirmed_intent_never_marks_the_order_paid(): void
    {
        $product = $this->listed(100000);
        $component = $this->filledCheckout($product)->call('startPayment');

        // Stripe still says it needs a payment method.
        $component->call('confirmPayment', $component->get('intentId'));

        $this->assertSame('pending', Order::latest('id')->first()->status);
        $this->assertSame('in_stock', InventoryStock::where('product_id', $product->id)->value('status'));
        $this->assertSame(0, \App\Models\Payment::where('status', 'succeeded')->count());
    }

    public function test_an_intent_from_another_order_is_rejected(): void
    {
        $product = $this->listed(100000);
        $component = $this->filledCheckout($product)->call('startPayment');

        $component->call('confirmPayment', 'pi_someone_elses_intent')
            ->assertSee('does not belong to this order');

        $this->assertSame('pending', Order::latest('id')->first()->status);
    }

    public function test_a_piece_sold_at_the_counter_drops_out_of_the_web_cart(): void
    {
        $product = $this->listed();
        $cart = app(StorefrontCart::class);
        $cart->add($product);

        $this->assertSame(1, $cart->count());

        // Sold at the register while the customer was browsing.
        InventoryStock::where('product_id', $product->id)->update(['status' => 'sold']);
        $product->update(['status' => 'sold']);

        $this->assertSame(0, $cart->items()->count());
        $this->assertTrue($cart->isEmpty());
    }

    public function test_a_sold_piece_cannot_be_added_to_the_bag(): void
    {
        $product = $this->listed();
        InventoryStock::where('product_id', $product->id)->update(['status' => 'sold']);

        $this->assertFalse(app(StorefrontCart::class)->add($product->fresh()));
    }

    public function test_the_storefront_and_pos_share_one_customer_record(): void
    {
        $product = $this->listed(100000);

        Customer::create(['name' => 'Anne Delacroix', 'email' => 'a.delacroix@example.com', 'phone' => '(917) 555-0142']);

        $this->filledCheckout($product)->call('startPayment')->assertHasNoErrors();

        $this->assertSame(1, Customer::where('email', 'a.delacroix@example.com')->count());
        $this->assertSame((int) Customer::first()->id, (int) Order::latest('id')->first()->customer_id);
    }

    public function test_a_guest_who_buys_can_later_claim_their_order_history(): void
    {
        $product = $this->listed(100000);
        $component = $this->filledCheckout($product)->call('startPayment');

        $intentId = $component->get('intentId');
        $this->stripe->intents[$intentId]['status'] = 'succeeded';
        $this->stripe->intents[$intentId]['amount_received'] = 108875;
        $component->call('confirmPayment', $intentId);

        // Registering with the same email claims the guest record, history and all.
        Livewire::test(Account::class)
            ->set('mode', 'register')
            ->set('form.name', 'Anne Delacroix')
            ->set('form.email', 'a.delacroix@example.com')
            ->set('form.password', 'correct-horse-battery')
            ->set('form.password_confirmation', 'correct-horse-battery')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertSame(1, Customer::count());
        $this->assertTrue(auth('customer')->check());
        $this->assertSame(1, Livewire::test(Account::class)->get('orders')->count());
    }

    public function test_a_customer_cannot_register_twice(): void
    {
        $existing = Customer::create(['name' => 'Anne', 'email' => 'a@example.com']);
        $existing->password = Hash::make('secret-password');
        $existing->save();

        Livewire::test(Account::class)
            ->set('mode', 'register')
            ->set('form.name', 'Anne')
            ->set('form.email', 'a@example.com')
            ->set('form.password', 'another-password')
            ->set('form.password_confirmation', 'another-password')
            ->call('register')
            ->assertHasErrors('form.email');
    }

    public function test_a_customer_sees_only_their_own_orders(): void
    {
        $mine = Customer::create(['name' => 'Anne', 'email' => 'anne@example.com']);
        $mine->password = Hash::make('secret-password');
        $mine->save();
        $theirs = Customer::create(['name' => 'Someone', 'email' => 'other@example.com']);

        $service = app(OrderService::class);
        $myOrder = $service->create([['description' => 'Mine', 'price_cents' => 1000]], $this->store->id, 'web', $mine->id);
        $myOrder->update(['status' => 'paid']);
        $theirOrder = $service->create([['description' => 'Theirs', 'price_cents' => 1000]], $this->store->id, 'web', $theirs->id);
        $theirOrder->update(['status' => 'paid']);

        auth('customer')->login($mine);

        $orders = Livewire::test(Account::class)->get('orders');

        $this->assertSame(1, $orders->count());
        $this->assertSame($myOrder->id, $orders->first()->id);

        // And the confirmation page of someone else's order is not reachable.
        $this->get(route('shop.confirmation', $theirOrder->order_number))->assertNotFound();
    }

    public function test_adding_to_the_bag_from_a_product_page(): void
    {
        $product = $this->listed();

        Livewire::test(ProductDetail::class, ['product' => $product])
            ->call('addToCart')
            ->assertSee('Added to your bag');

        $this->assertSame(1, app(StorefrontCart::class)->count());
    }
}
