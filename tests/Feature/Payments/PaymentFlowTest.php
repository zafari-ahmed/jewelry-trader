<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\PaymentService;
use App\Services\Payments\Stripe\StripeApi;
use App\Services\Payments\Tender;
use Database\Seeders\PaymentGatewaySeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\FakeStripeApi;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
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

    private function service(): PaymentService
    {
        return app(PaymentService::class);
    }

    public function test_a_full_card_sale_completes_and_records_one_card_payment(): void
    {
        $payment = $this->service()->pay(1, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('card', $payment->method);
        $this->assertSame(680000, $payment->amount);
        $this->assertSame('stripe', $payment->gateway);
        $this->assertStringStartsWith('pi_test_', $payment->gateway_transaction_id);
        $this->assertCount(1, $payment->splits);
        $this->assertSame(1, $this->stripe->callCount('createPaymentIntent'));
    }

    public function test_a_cash_sale_is_recorded_without_any_gateway_call(): void
    {
        $payment = $this->service()->pay(2, [Tender::cash(194500, cashTenderedCents: 200000)], 'EST-4365');

        $this->assertSame('succeeded', $payment->status);
        $this->assertSame('cash', $payment->method);
        $this->assertSame(0, $this->stripe->callCount('createPaymentIntent'));
        $this->assertSame(5500, $payment->splits->first()->raw_response['change']);
    }

    public function test_a_split_card_and_cash_sale_produces_splits_that_sum_to_the_total(): void
    {
        $payment = $this->service()->pay(3, [
            Tender::card(600000, 'pm_card_visa'),
            Tender::cash(178000, cashTenderedCents: 178000),
        ], 'EST-4412 + sizing');

        $this->assertSame('split', $payment->method);
        $this->assertSame(778000, $payment->amount);
        $this->assertSame(778000, (int) $payment->splits->sum('amount'));
        $this->assertSame(['card', 'cash'], $payment->splits->pluck('method')->all());
        // Only the card tender reaches Stripe.
        $this->assertSame(1, $this->stripe->callCount('createPaymentIntent'));
    }

    public function test_a_declined_card_records_a_failed_payment_and_charges_nothing(): void
    {
        $this->stripe->failNextWith = 'Your card was declined.';

        $payment = $this->service()->pay(4, [Tender::card(680000, 'pm_card_declined')], 'EST-4412');

        $this->assertSame('failed', $payment->status);
        $this->assertStringContainsString('declined', $payment->error_message);
        $this->assertCount(0, $payment->splits()->get());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.failed', 'category' => 'financial']);
    }

    public function test_a_split_whose_second_tender_fails_reverses_the_first(): void
    {
        // The customer must never be left charged for a sale that did not
        // complete: the successful first tender is reversed at the gateway.
        $this->stripe->failOnChargeNumber = 2;

        $payment = $this->service()->pay(5, [
            Tender::card(400000, 'pm_card_visa'),
            Tender::card(378000, 'pm_card_declined'),
        ], 'split that fails');

        $this->assertSame('failed', $payment->status);
        $this->assertCount(0, $payment->splits()->get());

        // One charge succeeded, one was declined, and the successful one was refunded.
        $this->assertSame(2, $this->stripe->callCount('createPaymentIntent'));
        $this->assertSame(1, $this->stripe->callCount('refundPaymentIntent'));

        $firstIntent = array_key_first($this->stripe->intents);
        $this->assertSame(400000, $this->stripe->refundedTotal($firstIntent));

        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.split_reversed', 'category' => 'financial']);
    }

    public function test_a_refund_reverses_through_the_gateway_and_updates_status(): void
    {
        $payment = $this->service()->pay(6, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $refunded = $this->service()->refund($payment, 680000);

        $this->assertSame('refunded', $refunded->status);
        $this->assertSame(680000, $refunded->amount_refunded);
        $this->assertSame(1, $this->stripe->callCount('refundPaymentIntent'));
        $this->assertSame(680000, $this->stripe->refundedTotal($payment->gateway_transaction_id));
    }

    public function test_a_partial_refund_marks_the_payment_partially_refunded(): void
    {
        $payment = $this->service()->pay(7, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $refunded = $this->service()->refund($payment, 180000);

        $this->assertSame('partially_refunded', $refunded->status);
        $this->assertSame(180000, $refunded->amount_refunded);
        $this->assertSame(500000, $refunded->refundableAmount());
    }

    public function test_a_refund_on_a_split_draws_from_the_card_tender_first(): void
    {
        $payment = $this->service()->pay(8, [
            Tender::card(600000, 'pm_card_visa'),
            Tender::cash(178000),
        ], 'split refund');

        // Refund less than the card tender: cash should be untouched.
        $refunded = $this->service()->refund($payment, 500000);

        $card = $refunded->splits->firstWhere('method', 'card');
        $cash = $refunded->splits->firstWhere('method', 'cash');

        $this->assertSame(500000, $card->amount_refunded);
        $this->assertSame(0, $cash->amount_refunded);
        $this->assertSame('partially_refunded', $refunded->status);
    }

    public function test_a_refund_cannot_exceed_what_remains(): void
    {
        $payment = $this->service()->pay(9, [Tender::card(100000, 'pm_card_visa')], 'EST-4412');
        $this->service()->refund($payment, 60000);

        $this->expectExceptionMessage('Refund exceeds the remaining refundable amount');

        $this->service()->refund($payment->fresh()->load('splits'), 60000);
    }

    public function test_a_payment_method_turned_off_in_settings_is_refused(): void
    {
        Setting::set('payments.accept_cash', false);

        $this->expectExceptionMessage('cash payment method is turned off');

        $this->service()->pay(10, [Tender::cash(50000)], 'cash sale');
    }

    public function test_split_payments_can_be_turned_off_in_settings(): void
    {
        Setting::set('payments.accept_split', false);

        $this->expectExceptionMessage('Split payments are turned off');

        $this->service()->pay(11, [Tender::card(50000, 'pm_card_visa'), Tender::cash(50000)], 'split');
    }

    public function test_every_payment_is_audited_as_a_financial_record(): void
    {
        $this->service()->pay(12, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.created',
            'category' => 'financial',
            'auditable_type' => Payment::class,
        ]);
    }

    public function test_the_gateway_response_is_not_written_into_the_audit_trail(): void
    {
        $this->service()->pay(13, [Tender::card(680000, 'pm_card_visa')], 'EST-4412');

        $log = \App\Models\AuditLog::query()->where('action', 'payment.created')->latest('id')->firstOrFail();

        $this->assertStringNotContainsString('pm_card_visa', json_encode($log->new_values));
        $this->assertSame('••••redacted••••', $log->new_values['raw_response']);
    }
}
