<?php

namespace Tests\Feature\Payments;

use App\Models\Setting;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\Stripe\StripeApi;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

/**
 * Partial capture and void back the POS exchange flow: authorise, then settle
 * the difference or release the hold entirely.
 */
class CaptureAndVoidTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeApi $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');

        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);
    }

    public function test_an_authorised_charge_can_be_captured_for_less(): void
    {
        $this->stripe->nextIntentStatus = 'requires_capture';

        $auth = PaymentGatewayFactory::make()->charge(680000, 'USD', 'pm_card_visa', 'exchange hold');

        $this->assertTrue($auth->success);
        $this->assertSame('requires_capture', $auth->status);

        $captured = PaymentGatewayFactory::make()->capture($auth->gatewayTransactionId, 540000);

        $this->assertTrue($captured->success);
        $this->assertSame(540000, $captured->amountCents);
    }

    public function test_an_authorisation_can_be_voided(): void
    {
        $this->stripe->nextIntentStatus = 'requires_capture';

        $auth = PaymentGatewayFactory::make()->charge(680000, 'USD', 'pm_card_visa', 'abandoned sale');
        $void = PaymentGatewayFactory::make()->void($auth->gatewayTransactionId);

        $this->assertTrue($void->success);
        $this->assertSame('canceled', $void->status);
    }

    public function test_a_zero_or_negative_charge_is_refused_before_reaching_the_gateway(): void
    {
        $result = PaymentGatewayFactory::make()->charge(0, 'USD', 'pm_card_visa', 'nothing');

        $this->assertTrue($result->failed());
        $this->assertSame(0, $this->stripe->callCount('createPaymentIntent'));
    }

    public function test_a_failing_gateway_status_surfaces_as_a_failed_result(): void
    {
        $this->stripe->nextIntentStatus = 'requires_payment_method';

        $result = PaymentGatewayFactory::make()->charge(1000, 'USD', 'pm_card_visa', 'declined');

        $this->assertTrue($result->failed());
        $this->assertStringContainsString('requires_payment_method', $result->errorMessage);
    }
}
