<?php

namespace App\Livewire\Quality;

use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/** The quality gauge on its own page, reached from the item record. */
class GaugePage extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        Gate::authorize('view', $product);

        $this->product = $product;
    }

    public function render()
    {
        return view('livewire.quality.gauge-page')->layout('layouts.admin-livewire', [
            'title' => 'Quality control',
            'heading' => 'Quality control',
            'subheading' => $this->product->sku.' · '.$this->product->title,
        ]);
    }
}
