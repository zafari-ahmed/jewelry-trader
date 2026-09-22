<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use App\Services\Search\KeywordProductSearch;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Catalog extends Component
{
    use WithPagination;

    #[Url] public string $q = '';

    #[Url] public string $category = '';

    #[Url] public string $period = '';

    #[Url] public string $metal = '';

    #[Url] public string $minPrice = '';

    #[Url] public string $maxPrice = '';

    #[Url] public string $sort = 'newest';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['q', 'category', 'period', 'metal', 'minPrice', 'maxPrice', 'sort']);
    }

    public function render()
    {
        $products = app(KeywordProductSearch::class)
            ->query($this->q, [
                'category' => $this->category,
                'style_period' => $this->period,
                'metal_type' => $this->metal,
                'min_price' => $this->minPrice,
                'max_price' => $this->maxPrice,
                'sort' => $this->sort,
            ])
            ->paginate(12);

        // Facets are drawn from publicly visible stock only, so a filter can
        // never hint at an item the customer cannot see.
        $facets = fn (string $column) => Product::query()
            ->publiclyVisible()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column);

        return view('livewire.shop.catalog', [
            'products' => $products,
            'categories' => $facets('category'),
            'periods' => $facets('style_period'),
            'metals' => $facets('metal_type'),
        ])->layout('layouts.storefront-livewire', ['title' => 'Collection']);
    }
}
