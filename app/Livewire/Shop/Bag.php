<?php

namespace App\Livewire\Shop;

use App\Services\Storefront\StorefrontCart;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Bag extends Component
{
    #[Computed]
    public function cart(): StorefrontCart
    {
        return app(StorefrontCart::class);
    }

    public function remove(int $productId): void
    {
        $this->cart->remove($productId);

        unset($this->cart);
        $this->dispatch('cart-updated');
    }

    #[On('cart-updated')]
    public function refresh(): void
    {
        unset($this->cart);
    }

    public function render()
    {
        return view('livewire.shop.bag')->layout('layouts.storefront-livewire', ['title' => 'Your bag']);
    }
}
