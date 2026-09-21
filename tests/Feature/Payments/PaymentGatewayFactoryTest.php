<?php

namespace Tests\Feature\Payments;

use App\Models\Location;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Payments\Tender;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeApi $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);

        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);
    }

    public function test_it_resolves_the_gateway_named_in_settings(): void
    {
        $gateway = PaymentGatewayFactory::make();

        $this->assertInstanceOf(StripeGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertSame('stripe', $gateway->slug());
    }

    public function test_a_deactivated_gateway_fails_with_a_clear_message(): void
    {
        PaymentGateway::query()->where('slug', 'stripe')->update(['is_active' => false]);

        $this->expectExceptionMessage('Payment gateway [stripe] is not configured or not active');

        PaymentGatewayFactory::make();
    }

    public function test_a_gateway_whose_driver_class_is_missing_fails_with_a_clear_message(): void
    {
        PaymentGateway::query()->where('slug', 'stripe')->update(['driver_class' => 'App\\Nope\\Missing']);

        $this->expectExceptionMessage('names a driver class that does not exist');

        PaymentGatewayFactory::make();
    }

    public function test_adding_a_gateway_is_a_row_plus_a_class(): void
    {
        PaymentGateway::query()->create([
            'name' => 'Fixture Pay', 'slug' => 'fixture',
            'driver_class' => \Tests\Fixtures\FixtureGateway::class,
            'is_active' => true,
        ]);

        Setting::set('payments.active_gateway', 'fixture');

        // No code in the factory changed; the active gateway is data.
        $this->assertSame('fixture', PaymentGatewayFactory::make()->slug());
    }

    public function test_keys_come_from_settings_so_rotation_needs_no_deploy(): void
    {
        Setting::set('payments.stripe_test_secret_key', 'sk_test_first');

        PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'first');

        Setting::set('payments.stripe_test_secret_key', 'sk_test_rotated');

        PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'second');

        $keysUsed = array_map(fn ($c) => $c[2], array_values(array_filter($this->stripe->calls, fn ($c) => $c[0] === 'createPaymentIntent')));

        $this->assertSame(['sk_test_first', 'sk_test_rotated'], $keysUsed);
    }

    public function test_test_mode_selects_the_stored_test_key_pair(): void
    {
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');
        Setting::set('payments.stripe_secret_key', 'sk_live_fixture');

        Setting::set('payments.test_mode', true);
        PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'in test mode');

        Setting::set('payments.test_mode', false);
        PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'in live mode');

        $keysUsed = array_map(fn ($c) => $c[2], array_values(array_filter($this->stripe->calls, fn ($c) => $c[0] === 'createPaymentIntent')));

        $this->assertSame(['sk_test_fixture', 'sk_live_fixture'], $keysUsed);
    }

    public function test_charging_without_a_configured_key_fails_loudly(): void
    {
        Setting::set('payments.stripe_test_secret_key', null);

        $this->expectExceptionMessage('No Stripe test secret key is configured');

        PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'no key');
    }

    public function test_no_raw_card_data_is_ever_persisted(): void
    {
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => Location::factory()->create()->id,
            'channel' => 'pos',
        ]);

        app(\App\Services\Payments\PaymentService::class)
            ->pay($order->id, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        // PCI DSS: only a token and the gateway's ids are stored.
        foreach (\App\Models\Payment::all() as $payment) {
            $serialised = json_encode($payment->toArray());

            $this->assertStringNotContainsString('4242', $serialised);
            $this->assertStringNotContainsString('cvc', strtolower($serialised));
            $this->assertStringNotContainsString('card_number', strtolower($serialised));
        }
    }
}
