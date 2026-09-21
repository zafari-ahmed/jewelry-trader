<?php

namespace App\Livewire\Inventory;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';

    #[Url] public string $locationId = '';

    #[Url] public string $status = '';

    #[Url] public string $category = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'locationId', 'status', 'category']);
    }

    public function render()
    {
        // Location scoping is applied in the query, not the view (rule 3.7).
        $products = Product::query()
            ->visibleTo(auth()->user())
            ->with(['currentPricing', 'stock.location', 'primaryImage'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
                ->orWhere('brand', 'like', "%{$this->search}%")))
            ->when($this->locationId, fn ($q) => $q->atLocation((int) $this->locationId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->latest('id')
            ->paginate(15);

        return view('livewire.inventory.product-list', [
            'products' => $products,
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'categories' => Product::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'statuses' => ['draft', 'pending_review', 'approved', 'listed', 'sold', 'archived'],
        ])->layout('layouts.admin-livewire', [
            'title' => 'Inventory',
            'heading' => 'Inventory',
            'subheading' => trim(Product::query()->visibleTo(auth()->user())->count().' records'),
        ]);
    }
}
