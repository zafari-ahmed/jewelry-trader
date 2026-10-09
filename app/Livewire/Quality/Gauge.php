<?php

namespace App\Livewire\Quality;

use App\Models\Product;
use App\Models\QualityControlCheck;
use App\Services\Quality\QualityCheckRegistry;
use App\Services\Quality\QualityControlService;
use App\Services\Quality\QualityStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The quality gauge, as staff see it.
 *
 * Everything the record can answer is already answered by the time this
 * renders. What is left is the handful of judgements that need a person, and
 * those are the only things this screen asks for.
 */
class Gauge extends Component
{
    public Product $product;

    public ?string $flash = null;

    public bool $showAll = false;

    public function mount(Product $product): void
    {
        Gate::authorize('view', $product);

        $this->product = $product;
        app(QualityControlService::class)->evaluate($product);
    }

    public function getChecksProperty()
    {
        return QualityControlCheck::where('product_id', $this->product->id)
            ->with('verifiedBy')
            ->get()
            ->sortBy(fn ($c) => [array_search($c->stage, array_keys(QualityCheckRegistry::STAGES)), $c->check_key])
            ->groupBy('stage');
    }

    public function getGaugeProperty(): array
    {
        return app(QualityControlService::class)->gauge($this->product);
    }

    public function getScoreProperty(): array
    {
        return app(QualityControlService::class)->score($this->product);
    }

    /** The checks still waiting on somebody — the actual work list. */
    public function getOutstandingProperty()
    {
        // Critical first: that is the order somebody should work through
        // them in. Sorted here rather than in SQL so the ordering does not
        // depend on a particular database's dialect.
        $rank = ['critical' => 0, 'standard' => 1, 'optional' => 2];

        return QualityControlCheck::where('product_id', $this->product->id)
            ->whereIn('status', [QualityStatus::PENDING, QualityStatus::FAILED])
            ->get()
            ->sortBy(fn ($check) => [$rank[$check->check_type] ?? 9, $check->check_key])
            ->values();
    }

    public function mark(string $key, string $status): void
    {
        Gate::authorize('update', $this->product);

        app(QualityControlService::class)->record($this->product, $key, $status, Auth::user());

        $this->flash = 'Recorded against your name.';
    }

    public function recheck(): void
    {
        Gate::authorize('view', $this->product);

        app(QualityControlService::class)->evaluate($this->product->fresh());

        $this->flash = 'Re-read from the record.';
    }

    public function render()
    {
        return view('livewire.quality.gauge', [
            'registry' => app(QualityCheckRegistry::class),
        ]);
    }
}
