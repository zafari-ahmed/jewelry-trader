<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Pricing;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Core CRUD for a product. Module 5 replaces this screen with the multi-step
 * intake workflow (photos, colour-coded fields, submit for review); the data
 * it writes is the same.
 */
class ProductForm extends Component
{
    public ?Product $product = null;

    public array $form = [
        'sku' => '', 'title' => '', 'subtitle' => '', 'category' => '', 'subcategory' => '',
        'brand' => '', 'style_period' => '', 'metal_type' => '', 'weight_grams' => '',
        'measurements' => '', 'condition_notes' => '', 'internal_description' => '',
        'customer_description' => '', 'status' => 'draft',
    ];

    public array $pricing = [
        'acquisition_value' => '', 'retail_price' => '', 'insurance_value' => '', 'negotiation_min' => '',
    ];

    public ?string $locationId = null;

    public ?string $saved = null;

    public function mount(?Product $product = null): void
    {
        if ($product?->exists) {
            Gate::authorize('view', $product);

            $this->product = $product->load('currentPricing', 'stock');
            $this->form = array_merge($this->form, $product->only(array_keys($this->form)));

            $current = $product->currentPricing;
            $this->pricing = [
                'acquisition_value' => $this->toDecimal($current?->acquisition_value_cents),
                'retail_price' => $this->toDecimal($current?->retail_price_cents),
                'insurance_value' => $this->toDecimal($current?->insurance_value_cents),
                'negotiation_min' => $this->toDecimal($current?->negotiation_min_cents),
            ];

            $this->locationId = (string) ($product->stock->first()?->location_id ?? '');

            return;
        }

        Gate::authorize('create', Product::class);
    }

    public function save(): void
    {
        $this->product?->exists
            ? Gate::authorize('update', $this->product)
            : Gate::authorize('create', Product::class);

        $data = $this->validate([
            'form.sku' => ['required', 'string', 'max:64', 'unique:products,sku'.($this->product?->id ? ','.$this->product->id : '')],
            'form.title' => ['required', 'string', 'max:255'],
            'form.category' => ['nullable', 'string', 'max:64'],
            'form.metal_type' => ['nullable', 'string', 'max:64'],
            'form.weight_grams' => ['nullable', 'numeric', 'min:0'],
            'form.measurements' => ['nullable', 'string', 'max:255'],
            'form.status' => ['required', 'in:draft,pending_review,approved,listed,sold,archived'],
            'pricing.retail_price' => ['nullable', 'numeric', 'min:0'],
            'pricing.acquisition_value' => ['nullable', 'numeric', 'min:0'],
            'locationId' => ['nullable', 'exists:locations,id'],
        ]);

        DB::transaction(function () use ($data) {
            $attributes = array_merge($data['form'], [
                'weight_grams' => $data['form']['weight_grams'] ?: null,
                'created_by' => $this->product?->created_by ?? auth()->id(),
            ]);

            $this->product = $this->product?->exists
                ? tap($this->product)->update($attributes)
                : Product::create($attributes);

            // Pricing is append-only history: a change adds a row.
            if ($this->pricingChanged()) {
                Pricing::create([
                    'product_id' => $this->product->id,
                    'acquisition_value_cents' => $this->toCents($this->pricing['acquisition_value']),
                    'retail_price_cents' => $this->toCents($this->pricing['retail_price']),
                    'insurance_value_cents' => $this->toCents($this->pricing['insurance_value']),
                    'negotiation_min_cents' => $this->toCents($this->pricing['negotiation_min']),
                    'priced_by' => auth()->id(),
                ]);
            }

            if ($this->locationId) {
                InventoryStock::updateOrCreate(
                    ['product_id' => $this->product->id, 'location_id' => (int) $this->locationId],
                    ['quantity' => 1, 'status' => 'in_stock'],
                );
            }
        });

        $this->saved = 'Saved.';
        $this->product = $this->product->fresh(['currentPricing', 'stock']);
    }

    public function delete(): void
    {
        Gate::authorize('delete', $this->product);

        $this->product->delete();

        $this->redirectRoute('admin.inventory', navigate: true);
    }

    private function pricingChanged(): bool
    {
        $current = $this->product->currentPricing()->first();

        return $this->toCents($this->pricing['retail_price']) !== $current?->retail_price_cents
            || $this->toCents($this->pricing['acquisition_value']) !== $current?->acquisition_value_cents
            || $this->toCents($this->pricing['insurance_value']) !== $current?->insurance_value_cents
            || $this->toCents($this->pricing['negotiation_min']) !== $current?->negotiation_min_cents;
    }

    private function toCents(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) round((float) $value * 100);
    }

    private function toDecimal(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, '.', '');
    }

    public function render()
    {
        return view('livewire.inventory.product-form', [
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => $this->product?->exists ? 'Edit Item' : 'Add Item',
            'heading' => $this->product?->exists ? 'Add / Edit Item' : 'Add / Edit Item',
            'subheading' => $this->product?->exists
                ? "{$this->product->sku} · ".str($this->product->status)->headline()
                : 'New record',
        ]);
    }
}
