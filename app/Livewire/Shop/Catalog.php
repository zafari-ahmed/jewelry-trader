<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\Providers\HttpSearchProvider;
use App\Services\Search\KeywordProductSearch;
use Livewire\Attributes\Computed;
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

    /**
     * Natural-language search reads the shopper's sentence and fills in the
     * filters the catalogue already understands. It never chooses which
     * records come back, so the "listed and in stock" guarantee is untouched.
     */
    #[Computed]
    public function interpretation(): array
    {
        if (trim($this->q) === '' || ! Setting::enabled('ai.enabled') || ! Setting::enabled('ai.search')) {
            return [];
        }

        return array_filter(app(HttpSearchProvider::class)->interpret($this->q), 'filled');
    }

    /**
     * The interpreted filters minus the search terms, for showing the shopper
     * what was understood. Kept separate from the query: stripping terms from
     * what is searched would send the whole raw sentence to keyword matching.
     */
    #[Computed]
    public function understoodFilters(): array
    {
        return array_diff_key($this->interpretation, array_flip(['terms']));
    }

    public function render()
    {
        $understood = $this->interpretation;

        $products = app(KeywordProductSearch::class)
            ->query($understood['terms'] ?? $this->q, [
                'category' => $this->category ?: ($understood['category'] ?? ''),
                'style_period' => $this->period ?: ($understood['style_period'] ?? ''),
                'metal_type' => $this->metal ?: ($understood['metal_type'] ?? ''),
                'min_price' => $this->minPrice ?: ($understood['min_price'] ?? ''),
                'max_price' => $this->maxPrice ?: ($understood['max_price'] ?? ''),
                'sort' => $this->sort !== 'newest' ? $this->sort : ($understood['sort'] ?? 'newest'),
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
