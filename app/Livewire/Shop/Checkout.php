<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentService;
use App\Services\Storefront\StorefrontCart;
use App\Models\Location;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Checkout. Card details are entered in Stripe's Payment Element and confirmed
 * by the browser directly with Stripe — no card data ever reaches this
 * application (PCI DSS, SAQ-A).
 *
 * The order is written first as pending; the payment is recorded only after
 * the intent is re-read from the gateway, and marking it paid is what flips
 * stock to sold under a row lock.
 */
class Checkout extends Component
{
    public array $form = [
        'email' => '', 'name' => '', 'phone' => '',
        'street' => '', 'city' => '', 'state' => '', 'postal_code' => '',
    ];

    public ?string $clientSecret = null;

    public ?string $intentId = null;

    public ?int $orderId = null;

    public ?string $error = null;

    public function mount(): void
    {
        if ($customer = auth('customer')->user()) {
            $this->form = array_merge($this->form, [
                'email' => $customer->email ?? '',
                'name' => $customer->name,
                'phone' => $customer->phone ?? '',
            ], $customer->address ?? []);
        }
    }

    #[Computed]
    public function cart(): StorefrontCart
    {
        return app(StorefrontCart::class);
    }

    #[Computed]
    public function taxCents(): int
    {
        return $this->cart->taxCents($this->form['state'] ?: null);
    }

    #[Computed]
    public function totalCents(): int
    {
        return $this->cart->totalCents($this->form['state'] ?: null);
    }

    public function publishableKey(): ?string
    {
        $gateway = PaymentGatewayFactory::make();

        return method_exists($gateway, 'publishableKey') ? $gateway->publishableKey() : null;
    }

    /** Validate the address, write the order, and prepare the payment intent. */
    public function startPayment(): void
    {
        $this->reset('error');

        if ($this->cart->isEmpty()) {
            $this->error = 'Your bag is empty.';

            return;
        }

        $this->validate([
            'form.email' => ['required', 'email', 'max:255'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:32'],
            'form.street' => ['required', 'string', 'max:255'],
            'form.city' => ['required', 'string', 'max:255'],
            'form.state' => ['required', 'string', 'size:2'],
            'form.postal_code' => ['required', 'string', 'max:16'],
        ]);

        $customer = \App\Models\Customer::findOrCreateByEmail($this->form['email'], [
            'name' => $this->form['name'],
            'phone' => $this->form['phone'] ?: null,
            'address' => array_intersect_key($this->form, array_flip(['street', 'city', 'state', 'postal_code'])),
        ]);

        // Web orders belong to the dedicated Web location (docs/DECISIONS.md),
        // but the tax applied follows the shipping address's state.
        $webLocation = Location::web() ?? Location::query()->firstOrFail();
        $state = strtoupper($this->form['state']);

        $order = app(OrderService::class)->create(
            lines: $this->cart->toOrderLines(),
            locationId: $webLocation->id,
            channel: 'web',
            customerId: $customer?->id,
            taxRate: $this->cart->taxRateForState($state),
            taxState: $state,
        );

        $intent = PaymentGatewayFactory::make()->prepare(
            $order->total_cents,
            Setting::get('general.currency', 'USD'),
            ['order_number' => $order->order_number, 'channel' => 'web'],
        );

        if ($intent->failed()) {
            $this->error = $intent->errorMessage ?: 'Unable to start the payment.';

            return;
        }

        $this->orderId = $order->id;
        $this->intentId = $intent->gatewayTransactionId;
        $this->clientSecret = $intent->rawResponse['client_secret'] ?? null;

        // The Element mounts against this secret; the browser confirms it.
        // Dispatched rather than pushed to a stack: a Livewire re-render never
        // re-runs @push, so the script has to be told, not templated.
        $this->dispatch(
            'payment-ready',
            clientSecret: $this->clientSecret,
            publishableKey: $this->publishableKey(),
        );
    }

    /** Called by the browser once Stripe reports the payment succeeded. */
    #[\Livewire\Attributes\On('confirm-payment')]
    public function confirmPayment(string $intentId): void
    {
        $this->reset('error');

        $order = Order::findOrFail($this->orderId);

        if ($intentId !== $this->intentId) {
            $this->error = 'That payment does not belong to this order.';

            return;
        }

        try {
            // Re-read from the gateway: the browser is not authoritative.
            $payment = app(PaymentService::class)->recordConfirmed($order->id, $intentId, $order->total_cents);

            app(OrderService::class)->markPaid($order, $payment);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->cart->clear();

        // Lets a guest see their confirmation once without an account.
        session()->put('shop.just_ordered', $order->order_number);

        $this->redirectRoute('shop.confirmation', ['order' => $order->order_number], navigate: true);
    }

    public function render()
    {
        return view('livewire.shop.checkout')
            ->layout('layouts.storefront-livewire', ['title' => 'Checkout']);
    }
}
