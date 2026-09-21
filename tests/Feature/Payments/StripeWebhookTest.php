<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Payments\Tender;
use App\Models\Location;
use App\Models\Order;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeApi $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(PaymentGatewaySeeder::class);
        Setting::set('payments.stripe_test_secret_key', 'sk_test_fixture');
        Setting::set('payments.stripe_test_webhook_secret', 'whsec_fixture');

        $this->stripe = new FakeStripeApi;
        $this->app->instance(StripeApi::class, $this->stripe);
    }

    /** A real order to attach payments to — payments.order_id is a foreign key. */
    private function orderId(): int
    {
        return Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => Location::factory()->create()->id,
            'channel' => 'pos',
        ])->id;
    }

    private function sendEvent(array $event, ?string $signature = null)
    {
        $payload = json_encode($event);

        return $this->call(
            'POST',
            route('webhooks.stripe'),
            server: ['HTTP_STRIPE_SIGNATURE' => $signature ?? 'valid-signature-for-whsec_fixture', 'CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $this->sendEvent(['type' => 'payment_intent.succeeded'], signature: 'forged')
            ->assertStatus(400)
            ->assertSee('Invalid signature');

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.webhook_rejected', 'category' => 'security']);
    }

    public function test_a_request_is_rejected_when_no_signing_secret_is_configured(): void
    {
        Setting::set('payments.stripe_test_webhook_secret', null);

        $this->sendEvent(['type' => 'payment_intent.succeeded'])->assertStatus(400);
    }

    public function test_the_signature_is_checked_against_the_secret_for_the_active_key_pair(): void
    {
        // In live mode the live secret must be the one verified against.
        Setting::set('payments.test_mode', false);
        Setting::set('payments.stripe_secret_key', 'sk_live_fixture');
        Setting::set('payments.stripe_webhook_secret', 'whsec_live_fixture');

        $this->sendEvent(['type' => 'payment_intent.succeeded'], signature: 'valid-signature-for-whsec_fixture')
            ->assertStatus(400);

        $this->sendEvent(['type' => 'payment_intent.succeeded'], signature: 'valid-signature-for-whsec_live_fixture')
            ->assertOk();
    }

    public function test_payment_intent_succeeded_confirms_a_recorded_payment(): void
    {
        $payment = app(PaymentService::class)->pay($this->orderId(), [Tender::card(680000, 'pm_card_visa')], 'EST-4412');
        $payment->update(['status' => 'pending']);

        $this->sendEvent([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => $payment->gateway_transaction_id, 'amount' => 680000]],
        ])->assertOk();

        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.webhook_confirmed']);
    }

    public function test_charge_refunded_records_a_refund_issued_outside_the_application(): void
    {
        $payment = app(PaymentService::class)->pay($this->orderId(), [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        // A refund pressed in the Stripe dashboard reaches us only this way.
        $this->sendEvent([
            'type' => 'charge.refunded',
            'data' => ['object' => ['payment_intent' => $payment->gateway_transaction_id, 'amount_refunded' => 680000]],
        ])->assertOk();

        $payment->refresh();

        $this->assertSame('refunded', $payment->status);
        $this->assertSame(680000, $payment->amount_refunded);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.refund_observed']);
    }

    public function test_a_partial_refund_observed_from_stripe_is_recorded_as_partial(): void
    {
        $payment = app(PaymentService::class)->pay($this->orderId(), [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $this->sendEvent([
            'type' => 'charge.refunded',
            'data' => ['object' => ['payment_intent' => $payment->gateway_transaction_id, 'amount_refunded' => 200000]],
        ])->assertOk();

        $this->assertSame('partially_refunded', $payment->fresh()->status);
    }

    public function test_an_event_for_an_unknown_payment_is_logged_not_dropped(): void
    {
        $this->sendEvent([
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_not_ours']],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.webhook_unmatched']);
    }

    public function test_an_unhandled_event_type_is_acknowledged_and_logged(): void
    {
        $this->sendEvent(['type' => 'customer.created', 'data' => ['object' => []]])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.webhook_ignored']);
    }
}
