<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\FieldColorRule;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Inventory\FieldColorResolver;
use App\Services\Inventory\ProductIntakeService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The intake workflow (CLAUDE.md Module 5): photos, then colour-coded field
 * entry, then submit for review.
 *
 * Target is well under five minutes for a representative item, so the happy
 * path is: drop photos, fill the red fields, submit. Everything else is
 * optional and stays out of the way.
 */
class ProductIntake extends Component
{
    use WithFileUploads;

    public ?Product $product = null;

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $values = [];

    public array $photos = [];

    public array $photoTypes = [];

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            Gate::authorize('view', $product);

            $this->product = $product->load('images', 'currentPricing', 'stock');
            $this->values = app(ProductIntakeService::class)->valuesFor($product);
            $this->step = 2;

            return;
        }

        Gate::authorize('create', Product::class);

        $this->values = ['sku' => $this->suggestSku()];
    }

    #[Computed]
    public function sections(): \Illuminate\Support\Collection
    {
        return app(FieldColorResolver::class)->sectionsFor($this->values['category'] ?? null);
    }

    #[Computed]
    public function missing(): array
    {
        return app(FieldColorResolver::class)->missingRequired($this->values['category'] ?? null, $this->values);
    }

    #[Computed]
    public function completeness(): array
    {
        return app(FieldColorResolver::class)->completeness(
            $this->values['category'] ?? null,
            $this->values,
            $this->product?->manually_overridden_fields ?? [],
        );
    }

    /** Colour for one field, given the current value. */
    public function colorFor(array $rule): string
    {
        return app(FieldColorResolver::class)->colorFor(
            $rule,
            $this->values[$rule['field_name']] ?? null,
            $this->product?->manually_overridden_fields ?? [],
        );
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function updatedPhotos(): void
    {
        $this->validate(['photos.*' => ['image', 'max:12288']]);
    }

    /**
     * 5–15 photos is guidance, not a server-side rule (docs/DECISIONS.md), so
     * the count is shown but never blocks submission.
     */
    public function storePhotos(): void
    {
        if ($this->photos === []) {
            return;
        }

        $this->validate(['photos.*' => ['image', 'max:12288']]);

        $product = $this->ensureProduct();

        foreach ($this->photos as $index => $photo) {
            $path = $photo->store("products/{$product->id}", 'public');

            ProductImage::create([
                'product_id' => $product->id,
                'type' => $this->photoTypes[$index] ?? 'front',
                'file_path' => $path,
                'is_primary' => ! $product->images()->where('is_primary', true)->exists() && $index === 0,
                'sort_order' => $product->images()->count() + $index,
            ]);
        }

        $this->reset('photos', 'photoTypes');
        $this->product = $product->fresh('images');
        $this->flash = 'Photos added.';
    }

    public function setPrimary(int $imageId): void
    {
        $this->product->images()->update(['is_primary' => false]);
        ProductImage::whereKey($imageId)->update(['is_primary' => true]);

        $this->product = $this->product->fresh('images');
    }

    public function removePhoto(int $imageId): void
    {
        ProductImage::whereKey($imageId)->delete();

        $this->product = $this->product->fresh('images');
    }

    public function saveDraft(): void
    {
        $this->persist();

        $this->flash = 'Draft saved.';
    }

    public function submitForReview(): void
    {
        $this->reset('error', 'flash');

        $product = $this->persist();

        try {
            // Server-side gate: the browser's view of the rules is a convenience.
            app(ProductIntakeService::class)->submitForReview($product, $this->values);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->product = $product->fresh();
        $this->flash = 'Submitted for review.';
        $this->step = 3;
    }

    private function persist(): Product
    {
        $this->product?->exists
            ? Gate::authorize('update', $this->product)
            : Gate::authorize('create', Product::class);

        $this->validate([
            'values.sku' => ['required', 'string', 'max:64', 'unique:products,sku'.($this->product?->id ? ','.$this->product->id : '')],
            'values.title' => ['required', 'string', 'max:255'],
            'values.weight_grams' => ['nullable', 'numeric', 'min:0'],
            'values.retail_price' => ['nullable', 'numeric', 'min:0'],
            'values.acquisition_value' => ['nullable', 'numeric', 'min:0'],
        ], attributes: ['values.sku' => 'SKU', 'values.title' => 'item title']);

        $this->product = app(ProductIntakeService::class)->save($this->product, $this->values, auth()->id());

        return $this->product;
    }

    private function ensureProduct(): Product
    {
        return $this->product?->exists ? $this->product : $this->persist();
    }

    private function suggestSku(): string
    {
        $last = Product::query()->where('sku', 'like', 'EST-%')->orderByDesc('id')->value('sku');
        // Digits only: FILTER_SANITIZE_NUMBER_INT keeps the hyphen and turns
        // EST-4363 into -4363, which produced SKUs like EST--4362.
        $next = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 4001;

        return 'EST-'.$next;
    }

    public function render()
    {
        return view('livewire.inventory.product-intake', [
            'locations' => Location::query()->active()->orderBy('name')->get(),
            'categories' => Category::query()->active()->orderBy('sort_order')->get(),
            'imageTypes' => ['front', 'back', 'side', 'hallmark', 'gemstone', 'clasp', 'movement', 'signature', 'packaging', 'certificate'],
        ])->layout('layouts.admin-livewire', [
            'title' => 'Add / Edit Item',
            'heading' => 'Add / Edit Item',
            'subheading' => $this->product?->exists
                ? "{$this->product->sku} · ".str($this->product->status)->headline().($this->product->intakeMinutes() ? " · intake {$this->product->intakeMinutes()} min" : '')
                : 'New record',
        ]);
    }
}
