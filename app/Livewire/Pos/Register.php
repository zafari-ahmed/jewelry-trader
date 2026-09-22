<?php

namespace App\Livewire\Pos;

use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Payments\Tender;
use App\Services\Pos\Cart;
use App\Services\Pos\PosSaleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Component;

/**
 * The register. Target is a complete sale in under two minutes, so the happy
 * path is: search, add, charge — no confirmation modals in the way.
 *
 * Cart state is held in the session so a page refresh mid-sale does not lose
 * the customer's items.
 */
class Register extends Component
{
    #[Session(key: 'pos.cart')]
    public array $cartState = [];

    #[Session(key: 'pos.location')]
    public ?int $locationId = null;

    public string $search = '';

    public string $screen = 'sale';        // sale | payment | receipt

    public string $customLineDescription = '';

    public string $customLinePrice = '';

    public array $tenders = [];            // [['method' => 'card'|'cash', 'amount' => '12.34']]

    public string $cashTendered = '';

    public ?string $customerEmail = null;

    public ?string $customerName = null;

    public ?int $completedOrderId = null;

    public ?string $error = null;

    public array $discountLine = ['key' => null, 'percent' => '', 'fixed' => ''];

    public function mount(): void
    {
        Gate::authorize('use-pos');

        $this->locationId ??= auth()->user()->location_id ?? Location::query()->where('is_web', false)->value('id');
    }

    #[Computed]
    public function cart(): Cart
    {
        $cart = new Cart($this->locationId);
        $cart->lines = $this->cartState;

        return $cart;
    }

    #[Computed]
    public function results()
    {
        if (strlen($this->search) < 2) {
            return collect();
        }

        return Product::query()
            ->visibleTo(auth()->user())
            ->where('status', 'listed')
            ->where(fn ($q) => $q
                ->where('sku', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%")
                ->orWhere('brand', 'like', "%{$this->search}%"))
            ->with(['currentPricing', 'stock', 'primaryImage'])
            ->limit(12)
            ->get();
    }

    #[Computed]
    public function balanceDueCents(): int
    {
        $allocated = array_sum(array_map(fn ($t) => $this->toCents($t['amount'] ?? ''), $this->tenders));

        return $this->cart->totalCents() - $allocated;
    }

    public function addProduct(int $productId): void
    {
        $this->reset('error');

        $product = Product::with(['currentPricing', 'stock'])->findOrFail($productId);

        // The definitive check is at markPaid, under a row lock; this is the
        // courtesy that keeps unavailable stock out of the cart.
        if (! $product->isAvailableForSale()) {
            $this->error = "{$product->sku} is not available for sale.";

            return;
        }

        $cart = $this->cart;
        $cart->addProduct($product);
        $this->cartState = $cart->lines;

        $this->search = '';
        unset($this->cart, $this->results);
    }

    public function addCustomLine(): void
    {
        $this->validate([
            'customLineDescription' => ['required', 'string', 'max:255'],
            'customLinePrice' => ['required', 'numeric', 'min:0'],
        ]);

        $cart = $this->cart;
        $cart->addCustomLine($this->customLineDescription, $this->toCents($this->customLinePrice), 'SVC');
        $this->cartState = $cart->lines;

        $this->reset('customLineDescription', 'customLinePrice');
        unset($this->cart);
    }

    public function removeLine(string $key): void
    {
        $cart = $this->cart;
        $cart->remove($key);
        $this->cartState = $cart->lines;

        unset($this->cart);
    }

    public function applyDiscount(): void
    {
        $this->reset('error');

        $cart = $this->cart;
        $cart->discountLine(
            $this->discountLine['key'],
            (float) ($this->discountLine['percent'] ?: 0),
            $this->toCents($this->discountLine['fixed'] ?: ''),
        );

        // Above the configured ceiling, a discount needs someone with the
        // authority to approve it (rule 3.6 — checked here, not in the view).
        if ($cart->exceedsStaffDiscountCeiling() && ! auth()->user()->can('apply-discount-above-threshold')) {
            $ceiling = Setting::get('pos.max_staff_discount_percent', 10);
            $this->error = "Discounts above {$ceiling}% need a manager's approval.";

            return;
        }

        $this->cartState = $cart->lines;
        $this->discountLine = ['key' => null, 'percent' => '', 'fixed' => ''];

        unset($this->cart);
    }

    public function voidSale(): void
    {
        $this->cartState = [];
        $this->tenders = [];
        $this->reset('search', 'error', 'customerEmail', 'customerName', 'cashTendered');
        $this->screen = 'sale';

        unset($this->cart);
    }

    public function goToPayment(): void
    {
        $this->reset('error');

        if ($this->cart->isEmpty()) {
            $this->error = 'Add an item before taking payment.';

            return;
        }

        // Pre-fill a single card tender for the full amount: the common case
        // is one card, and it should take zero extra clicks.
        $this->tenders = [['method' => 'card', 'amount' => $this->toDecimal($this->cart->totalCents())]];
        $this->screen = 'payment';
    }

    public function setTender(string $method): void
    {
        $this->tenders = [['method' => $method, 'amount' => $this->toDecimal($this->cart->totalCents())]];
    }

    public function addTender(): void
    {
        $this->tenders[] = ['method' => 'cash', 'amount' => $this->toDecimal(max(0, $this->balanceDueCents))];
    }

    public function removeTender(int $index): void
    {
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
    }

    public function charge(): void
    {
        $this->reset('error');

        Gate::authorize('use-pos');

        try {
            $customer = app(PosSaleService::class)->attachCustomer($this->customerEmail, $this->customerName);

            $order = app(PosSaleService::class)->complete(
                cart: $this->cart,
                tenders: $this->buildTenders(),
                customerId: $customer?->id,
                userId: auth()->id(),
            );
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->completedOrderId = $order->id;
        $this->cartState = [];
        $this->tenders = [];
        $this->screen = 'receipt';

        unset($this->cart);
    }

    #[Computed]
    public function completedOrder(): ?Order
    {
        return $this->completedOrderId
            ? Order::with(['items', 'payments.splits', 'customer', 'location'])->find($this->completedOrderId)
            : null;
    }

    public bool $emailed = false;

    /** Receipts are emailed only when a customer email is on file. */
    public function emailReceipt(): void
    {
        $order = $this->completedOrder;

        if (! $order?->customer?->email) {
            $this->error = 'No customer email is on file for this sale.';

            return;
        }

        \Illuminate\Support\Facades\Mail::to($order->customer->email)->send(new \App\Mail\ReceiptMail($order));

        $this->emailed = true;
    }

    public function newSale(): void
    {
        $this->voidSale();
        $this->completedOrderId = null;
        $this->emailed = false;
    }

    /** @return Tender[] */
    private function buildTenders(): array
    {
        $tenders = [];

        foreach ($this->tenders as $tender) {
            $cents = $this->toCents($tender['amount'] ?? '');

            if ($cents <= 0) {
                continue;
            }

            $tenders[] = $tender['method'] === 'cash'
                ? Tender::cash($cents, $this->cashTendered ? $this->toCents($this->cashTendered) : $cents)
                // A real integration hands over a gateway token from the card
                // form; the test token stands in until the Payment Element is
                // mounted on this screen.
                : Tender::card($cents, 'pm_card_visa');
        }

        return $tenders;
    }

    private function toCents(string|float|null $value): int
    {
        return ($value === '' || $value === null) ? 0 : (int) round((float) $value * 100);
    }

    private function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function render()
    {
        return view('livewire.pos.register', [
            'locations' => Location::query()->active()->where('is_web', false)->orderBy('name')->get(),
            'maxDiscount' => Setting::get('pos.max_staff_discount_percent', 10),
        ])->layout('layouts.pos-livewire', ['title' => 'Register']);
    }
}
