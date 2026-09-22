<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use App\Services\Storefront\StorefrontCart;
use Livewire\Component;

class ProductDetail extends Component
{
    public Product $product;

    public ?string $activeImage = null;

    public ?string $flash = null;

    public function mount(Product $product): void
    {
        // A draft, pending or sold item must never be reachable publicly.
        abort_unless($product->isAvailableForSale(), 404);

        $this->product = $product->load(['images', 'currentPricing', 'gemstones']);
        $this->activeImage = $this->product->primaryImage?->file_path
            ?? $this->product->images->first()?->file_path;
    }

    public function addToCart(): void
    {
        $added = app(StorefrontCart::class)->add($this->product);

        $this->flash = $added
            ? 'Added to your bag.'
            : 'Sorry — this piece has just been sold.';

        $this->dispatch('cart-updated');
    }

    public function render()
    {
        return view('livewire.shop.product-detail')
            ->layout('layouts.storefront-livewire', ['title' => $this->product->title]);
    }
}
