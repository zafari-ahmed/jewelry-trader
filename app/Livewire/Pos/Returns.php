<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Payments\Tender;
use App\Services\Pos\Cart;
use App\Services\Pos\PosSaleService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Returns and exchanges. Looking up by order number, selecting lines, then
 * either refunding them or swapping them for something else — the difference
 * settles in one transaction.
 */
class Returns extends Component
{
    public string $orderNumber = '';

    public ?int $orderId = null;

    public array $selectedItems = [];

    public string $mode = 'refund';        // refund | exchange

    public string $exchangeSearch = '';

    public array $exchangeLines = [];

    public string $differenceMethod = 'card';

    public ?string $error = null;

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('process-refunds');
    }

    public function lookup(): void
    {
        $this->reset('error', 'flash', 'selectedItems', 'exchangeLines', 'orderId');

        $order = Order::query()
            ->visibleTo(auth()->user())
            ->where('order_number', 'like', '%'.trim($this->orderNumber).'%')
            ->whereIn('status', ['paid', 'fulfilled', 'partially_refunded'])
            ->latest('id')
            ->first();

        if (! $order) {
            $this->error = 'No paid order matches that number.';

            return;
        }

        $this->orderId = $order->id;
    }

    #[Computed]
    public function order(): ?Order
    {
        return $this->orderId
            ? Order::with(['items.product', 'payments.splits', 'customer', 'location'])->find($this->orderId)
            : null;
    }

    #[Computed]
    public function withinWindow(): bool
    {
        return (bool) $this->order?->isWithinReturnWindow();
    }

    #[Computed]
    public function exchangeResults()
    {
        if (strlen($this->exchangeSearch) < 2) {
            return collect();
        }

        return Product::query()
            ->visibleTo(auth()->user())
            ->where('status', 'listed')
            ->where(fn ($q) => $q->where('sku', 'like', "%{$this->exchangeSearch}%")->orWhere('title', 'like', "%{$this->exchangeSearch}%"))
            ->with(['currentPricing', 'stock'])
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function exchangeCart(): Cart
    {
        $cart = new Cart($this->order?->location_id);
        $cart->lines = $this->exchangeLines;

        return $cart;
    }

    #[Computed]
    public function creditCents(): int
    {
        $order = $this->order;

        if (! $order) {
            return 0;
        }

        $lines = $order->items->whereIn('id', $this->selectedItems);
        $subtotal = $lines->sum(fn ($i) => $i->lineTotalCents());

        return $subtotal + (int) round($subtotal * (float) $order->tax_rate);
    }

    #[Computed]
    public function differenceCents(): int
    {
        return $this->exchangeCart->totalCents() - $this->creditCents;
    }

    public function addExchangeItem(int $productId): void
    {
        $product = Product::with(['currentPricing', 'stock'])->findOrFail($productId);

        $cart = $this->exchangeCart;
        $cart->addProduct($product);
        $this->exchangeLines = $cart->lines;

        $this->exchangeSearch = '';
        unset($this->exchangeCart, $this->exchangeResults);
    }

    public function removeExchangeItem(string $key): void
    {
        $cart = $this->exchangeCart;
        $cart->remove($key);
        $this->exchangeLines = $cart->lines;

        unset($this->exchangeCart);
    }

    public function refundSelected(): void
    {
        $this->reset('error', 'flash');

        if ($this->selectedItems === []) {
            $this->error = 'Select at least one item to return.';

            return;
        }

        // Outside the window a return needs authority, not a silent refusal.
        if (! $this->withinWindow && ! auth()->user()->can('approve-overrides')) {
            $days = Setting::get('pos.return_window_days', 30);
            $this->error = "This sale is outside the {$days}-day return window — a manager override is required.";

            return;
        }

        try {
            app(PosSaleService::class)->refund($this->order, $this->selectedItems);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->flash = 'Refund issued to the original tender.';
        $this->reset('selectedItems');
        unset($this->order);
    }

    public function completeExchange(): void
    {
        $this->reset('error', 'flash');

        if ($this->selectedItems === [] || $this->exchangeLines === []) {
            $this->error = 'Choose the items coming back and the items going out.';

            return;
        }

        $difference = $this->differenceCents;

        try {
            $result = app(PosSaleService::class)->exchange(
                order: $this->order,
                returnedItemIds: $this->selectedItems,
                newCart: $this->exchangeCart,
                tenders: $difference > 0 ? [$this->differenceTender($difference)] : [],
                userId: auth()->id(),
            );
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->flash = $difference > 0
            ? "Exchange complete · {$result['order']->order_number} · customer paid $".number_format($difference / 100, 2)
            : "Exchange complete · {$result['order']->order_number} · $".number_format(abs($difference) / 100, 2).' refunded';

        $this->reset('selectedItems', 'exchangeLines');
        unset($this->order, $this->exchangeCart);
    }

    private function differenceTender(int $cents): Tender
    {
        return $this->differenceMethod === 'cash'
            ? Tender::cash($cents, $cents)
            : Tender::card($cents, 'pm_card_visa');
    }

    public function render()
    {
        return view('livewire.pos.returns')->layout('layouts.pos-livewire', ['title' => 'Returns']);
    }
}
