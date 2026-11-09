<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The dashboard, reading the database.
 *
 * It was a static design screen from Module 0 — the figures, the activity feed
 * and even the date were written into the Blade file so the layout could be
 * reviewed before there was anything to read. No later module owned replacing
 * it, so it went on quoting $18,420 of sales and naming staff who do not
 * exist long after the data was real.
 *
 * Everything here is scoped through the models' own visibleTo scopes, so a
 * salesperson at one location does not see another's takings (rule 3.7).
 */
class Dashboard extends Component
{
    /**
     * Today's takings: orders paid or fulfilled today. A refunded order is
     * not a sale, so it is not counted — refunds are reported separately
     * rather than quietly netted off, because a day with £0 net and twenty
     * transactions is a different day from a quiet one.
     */
    #[Computed]
    public function today(): array
    {
        $orders = Order::query()
            ->visibleTo(auth()->user())
            ->whereDate('created_at', today())
            ->whereIn('status', ['paid', 'fulfilled'])
            ->get(['id', 'total_cents']);

        $refunded = Order::query()
            ->visibleTo(auth()->user())
            ->whereDate('created_at', today())
            ->whereIn('status', ['refunded', 'partially_refunded'])
            ->count();

        return [
            'total_cents' => (int) $orders->sum('total_cents'),
            'count' => $orders->count(),
            'refunded' => $refunded,
        ];
    }

    /** The line under today's figure, assembled here because Blade cannot. */
    #[Computed]
    public function todayMeta(): string
    {
        $today = $this->today;

        $parts = [$today['count'].' '.str()->plural('transaction', $today['count'])];

        if ($today['refunded'] > 0) {
            $parts[] = $today['refunded'].' returned';
        }

        return implode(' · ', $parts);
    }

    #[Computed]
    public function pendingReview(): int
    {
        return Product::query()
            ->visibleTo(auth()->user())
            ->where('status', 'pending_review')
            ->count();
    }

    /**
     * Drafts, labelled as drafts. The original tile said "Incomplete Records
     * — missing required fields", which would mean running the field rules
     * over every draft on every page load. A draft is by definition not yet
     * submitted, which is the thing a manager wants to see.
     */
    #[Computed]
    public function drafts(): int
    {
        return Product::query()
            ->visibleTo(auth()->user())
            ->where('status', 'draft')
            ->count();
    }

    #[Computed]
    public function activeInventory(): int
    {
        return InventoryStock::query()
            ->where('status', 'in_stock')
            ->when(
                ! auth()->user()?->can('view-all-locations'),
                fn ($q) => $q->where('location_id', auth()->user()?->location_id),
            )
            ->distinct()
            ->count('product_id');
    }

    #[Computed]
    public function locationNames(): string
    {
        $names = auth()->user()?->can('view-all-locations')
            ? Location::where('is_active', true)->orderBy('name')->pluck('name')
            : Location::whereKey(auth()->user()?->location_id)->pluck('name');

        return $names->implode(', ') ?: 'No active locations';
    }

    #[Computed]
    public function locationCount(): int
    {
        return auth()->user()?->can('view-all-locations')
            ? Location::where('is_active', true)->count()
            : 1;
    }

    /**
     * The audit trail is the activity feed — it already records who did what
     * and when, so inventing a second log would be two sources of truth.
     *
     * @return Collection<int, AuditLog>
     */
    #[Computed]
    public function activity(): Collection
    {
        return AuditLog::query()
            ->with(['user:id,name', 'auditable'])
            ->latest('id')
            ->limit(6)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')->layout('layouts.admin-livewire', [
            'title' => 'Dashboard',
            'heading' => 'Dashboard',
            'subheading' => now()->format('l, F j').' · '.$this->locationNames,
        ]);
    }
}
