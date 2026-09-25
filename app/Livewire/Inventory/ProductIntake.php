<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\FieldColorRule;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AI\AiCatalogueService;
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

    /** Set while the assistant is working, so the UI can say so. */
    public bool $analysing = false;

    /** The suggested price, with its workings, once one has been produced. */
    public ?array $priceSuggestion = null;

    /** Values as the assistant proposed them, to detect a human's edit. */
    public array $suggestedValues = [];

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
            FieldColorResolver::MODEL,
            $this->product?->ai_suggested_fields ?? [],
        );
    }

    /** Colour for one field, given the current value. */
    public function colorFor(array $rule): string
    {
        return app(FieldColorResolver::class)->colorFor(
            $rule,
            $this->values[$rule['field_name']] ?? null,
            $this->product?->manually_overridden_fields ?? [],
            $this->product?->ai_suggested_fields ?? [],
        );
    }

    #[Computed]
    public function aiAvailable(): bool
    {
        return app(AiCatalogueService::class)->isAvailable();
    }

    public function isSuggested(string $field): bool
    {
        return in_array($field, $this->product?->ai_suggested_fields ?? [], true);
    }

    /**
     * Read the photographs and fill the record with suggestions.
     *
     * Nothing is decided here: every value lands yellow for a person to accept
     * or replace, and the price is a band with its workings attached.
     */
    public function analysePhotos(): void
    {
        $this->reset('error', 'flash');

        $product = $this->ensureProduct();

        if ($product->images()->count() === 0) {
            $this->error = 'Take at least one photograph before running the analysis.';

            return;
        }

        $catalogue = app(AiCatalogueService::class);

        try {
            $analysis = $catalogue->analysePhotos($product);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($analysis['values'] === []) {
            $this->error = 'The photographs were not clear enough for a dependable suggestion. Add more angles, or catalogue by hand.';

            return;
        }

        // Only fill what a person has not already answered themselves.
        $filled = [];

        foreach ($analysis['values'] as $field => $value) {
            if (filled($this->values[$field] ?? null) && ! $this->isSuggested($field)) {
                continue;
            }

            $this->values[$field] = $value;
            $this->suggestedValues[$field] = $value;
            $filled[] = $field;
        }

        $catalogue->markSuggested($product, $filled);

        $this->priceSuggestion = $catalogue
            ->suggestPrice($this->values, $analysis['gemstones'])
            ->toArray();

        $this->product = $product->fresh(['images', 'currentPricing', 'stock']);
        $this->flash = count($filled).' '.str('field')->plural(count($filled))
            .' suggested from the photographs · '.$analysis['result']->confidenceScore.'% confidence. Check each one.';
    }

    /** Draft the five descriptions from what the record now says. */
    public function generateDescriptions(): void
    {
        $this->reset('error', 'flash');

        $product = $this->ensureProduct();

        try {
            $descriptions = app(AiCatalogueService::class)->describe(array_filter([
                'title' => $this->values['title'] ?? null,
                'category' => $this->values['category'] ?? null,
                'style_period' => $this->values['style_period'] ?? null,
                'metal_type' => $this->values['metal_type'] ?? null,
                'measurements' => $this->values['measurements'] ?? null,
                'brand' => $this->values['brand'] ?? null,
                'condition_notes' => $this->values['condition_notes'] ?? null,
            ]));
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($descriptions === []) {
            $this->error = 'No usable description came back. Try again, or write it by hand.';

            return;
        }

        foreach ($descriptions as $field => $value) {
            $this->values[$field] = $value;
            $this->suggestedValues[$field] = $value;
        }

        app(AiCatalogueService::class)->markSuggested($product, array_keys($descriptions));

        $this->product = $product->fresh();
        $this->flash = count($descriptions).' descriptions drafted. Read them before submitting.';
    }

    /** Price the piece from the rate table, showing the workings. */
    public function suggestPrice(): void
    {
        $this->reset('error', 'flash');

        $gemstones = $this->product?->gemstones()->get()->map->toArray()->all() ?? [];

        $suggestion = app(AiCatalogueService::class)->suggestPrice($this->values, $gemstones);

        if (! $suggestion->hasValue()) {
            $this->error = 'A price needs at least a metal and a weight: '.implode(', ', $suggestion->missing).'.';

            return;
        }

        $this->priceSuggestion = $suggestion->toArray();
    }

    /** Take the suggested retail price into the record. */
    public function applySuggestedPrice(): void
    {
        if (! $this->priceSuggestion) {
            return;
        }

        $product = $this->ensureProduct();

        foreach ([
            'retail_price' => $this->priceSuggestion['retail_cents'],
            'insurance_value' => $this->priceSuggestion['insurance_cents'],
            'negotiation_min' => $this->priceSuggestion['negotiation_floor_cents'],
        ] as $field => $cents) {
            $this->values[$field] = number_format($cents / 100, 2, '.', '');
            $this->suggestedValues[$field] = $this->values[$field];
        }

        app(AiCatalogueService::class)->markSuggested($product, ['retail_price', 'insurance_value', 'negotiation_min']);

        $this->product = $product->fresh();
        $this->flash = 'Suggested prices applied. They stay marked as suggestions until you accept them.';
    }

    /** A person agreed with the suggestion as it stands. */
    public function acceptSuggestion(string $field): void
    {
        if (! $this->product?->exists) {
            return;
        }

        app(AiCatalogueService::class)->acceptSuggestion($this->product, $field);

        unset($this->suggestedValues[$field]);
        $this->product = $this->product->fresh();
    }

    /** A person rejected the suggestion: the field is cleared for them to fill. */
    public function rejectSuggestion(string $field): void
    {
        if (! $this->product?->exists) {
            return;
        }

        app(AiCatalogueService::class)->recordCorrection(
            $this->product,
            $field,
            $this->suggestedValues[$field] ?? ($this->values[$field] ?? null),
            null,
            auth()->id(),
            'Rejected at intake',
        );

        $this->values[$field] = '';
        unset($this->suggestedValues[$field]);
        $this->product = $this->product->fresh();
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

    /**
     * Log any suggestion a person changed rather than accepted. This is what
     * turns day-to-day corrections into a record of where the assistant is
     * weak.
     */
    private function captureCorrections(Product $product): void
    {
        $catalogue = app(AiCatalogueService::class);

        foreach ($this->suggestedValues as $field => $suggested) {
            $current = $this->values[$field] ?? null;

            if ((string) $current === (string) $suggested) {
                continue;
            }

            $catalogue->recordCorrection($product, $field, $suggested, $current, auth()->id());

            unset($this->suggestedValues[$field]);
        }
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

        if ($this->suggestedValues !== []) {
            $this->captureCorrections($this->product);
            $this->product = $this->product->fresh();
        }

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
