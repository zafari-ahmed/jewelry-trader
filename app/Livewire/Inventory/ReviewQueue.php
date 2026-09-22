<?php

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Services\Inventory\FieldColorResolver;
use App\Services\Inventory\ProductIntakeService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Approve is not the same as list: an item can be approved for the record
 * without being published for sale (CLAUDE.md Module 5).
 */
class ReviewQueue extends Component
{
    public ?int $selectedId = null;

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    #[Computed]
    public function queue()
    {
        return Product::query()
            ->visibleTo(auth()->user())
            ->whereIn('status', ['pending_review', 'approved'])
            ->with(['images', 'currentPricing', 'createdBy'])
            ->orderBy('submitted_for_review_at')
            ->get();
    }

    #[Computed]
    public function selected(): ?Product
    {
        $product = $this->selectedId
            ? $this->queue->firstWhere('id', $this->selectedId)
            : $this->queue->first();

        return $product?->loadMissing('stock.location');
    }

    /**
     * Median intake time, surfaced to managers (Module 5 acceptance: photo
     * upload through submit-for-review in well under five minutes).
     */
    #[Computed]
    public function intakeMetric(): ?array
    {
        $minutes = Product::query()
            ->whereNotNull('submitted_for_review_at')
            ->get()
            ->map(fn (Product $p) => $p->intakeMinutes())
            ->filter()
            ->sort()
            ->values();

        if ($minutes->isEmpty()) {
            return null;
        }

        return [
            'median' => round($minutes[(int) floor($minutes->count() / 2)], 1),
            'slowest' => round($minutes->last(), 1),
            'count' => $minutes->count(),
        ];
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reset('flash', 'error');
    }

    public function approve(): void
    {
        $this->act(fn (Product $p) => app(ProductIntakeService::class)->approve($p, auth()->id()), 'Approved.');
    }

    public function listItem(): void
    {
        $this->act(fn (Product $p) => app(ProductIntakeService::class)->list($p), 'Listed for sale.');
    }

    public function returnToSubmitter(): void
    {
        $this->act(fn (Product $p) => app(ProductIntakeService::class)->returnToSubmitter($p), 'Returned to the submitter.');
    }

    private function act(callable $action, string $message): void
    {
        $this->reset('flash', 'error');

        $product = $this->selected;

        if (! $product) {
            return;
        }

        Gate::authorize('approve', $product);

        try {
            $action($product);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        unset($this->queue, $this->selected);
        $this->flash = $message;
    }

    public function render()
    {
        $product = $this->selected;

        return view('livewire.inventory.review-queue', [
            'fieldRules' => $product
                ? app(FieldColorResolver::class)->rulesFor($product->category)
                : collect(),
            'values' => $product ? app(ProductIntakeService::class)->valuesFor($product) : [],
        ])->layout('layouts.admin-livewire', [
            'title' => 'Review Queue',
            'heading' => 'Review Queue',
            'subheading' => $this->queue->count().' items awaiting a decision',
        ]);
    }
}
