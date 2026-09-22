<?php

namespace App\Livewire\Admin;

use App\Models\InventoryLock;
use App\Models\Override;
use App\Models\Product;
use App\Models\StepUpChallenge;
use App\Services\Security\InventoryLockService;
use App\Services\Security\OverrideService;
use App\Services\Security\StepUpService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

class OverridesAndLocks extends Component
{
    public array $override = ['override_type' => 'price_below_floor', 'reason' => '', 'amount' => '', 'sku' => '', 'confirmed' => false];

    public array $lock = ['sku' => '', 'lock_type' => 'sales', 'reason' => ''];

    /** Step-up answers, keyed by challenge id. */
    public array $stepUpAnswers = [];

    public ?int $approvingId = null;

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('request-override');
    }

    #[Computed]
    public function pending()
    {
        return Override::with(['requestedBy', 'product'])->where('status', 'pending')->latest('id')->get();
    }

    #[Computed]
    public function activeLocks()
    {
        return InventoryLock::with(['product', 'lockedBy'])->active()->latest('locked_at')->get();
    }

    #[Computed]
    public function stepUpChallenges()
    {
        return app(StepUpService::class)->challenge(2);
    }

    public function requestOverride(): void
    {
        $this->reset('flash', 'error');

        if (! $this->override['confirmed']) {
            $this->error = 'Confirm that you understand this is attributed to your account permanently.';

            return;
        }

        try {
            app(OverrideService::class)->request(
                $this->override['override_type'],
                $this->override['reason'],
                auth()->user(),
                [
                    'product_id' => Product::where('sku', $this->override['sku'])->value('id'),
                    'amount' => $this->override['amount'] === '' ? null : (int) round((float) $this->override['amount'] * 100),
                ],
            );
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('override');
        $this->flash = 'Override requested. It takes effect once approved.';
        unset($this->pending);
    }

    public function startApproval(int $overrideId): void
    {
        Gate::authorize('approve-overrides');

        $this->approvingId = $overrideId;
        $this->reset('stepUpAnswers', 'error', 'flash');
    }

    public function approve(): void
    {
        $this->reset('error', 'flash');

        Gate::authorize('approve-overrides');

        $stepUp = app(StepUpService::class);

        // High-risk actions are gated behind the step-up challenge until it
        // passes — enforced here, not by hiding the button.
        if ($stepUp->isRequiredFor('override.approve') && ! $stepUp->hasPassed('override.approve')) {
            if (! $stepUp->verify($this->stepUpAnswers, 'override.approve')) {
                $this->error = 'Those answers are not correct. The attempt has been logged.';
                $this->reset('stepUpAnswers');

                return;
            }
        }

        try {
            app(OverrideService::class)->approve(Override::findOrFail($this->approvingId), auth()->user());
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('approvingId', 'stepUpAnswers');
        $this->flash = 'Override approved and recorded.';
        unset($this->pending);
    }

    public function reject(int $overrideId): void
    {
        Gate::authorize('approve-overrides');

        app(OverrideService::class)->reject(Override::findOrFail($overrideId), auth()->user());

        $this->flash = 'Override rejected.';
        unset($this->pending);
    }

    public function applyLock(): void
    {
        $this->reset('flash', 'error');

        Gate::authorize('lock-inventory');

        $product = Product::where('sku', $this->lock['sku'])->first();

        if (! $product) {
            $this->error = 'No item matches that SKU.';

            return;
        }

        try {
            app(InventoryLockService::class)->lock($product, $this->lock['lock_type'], $this->lock['reason'], auth()->user());
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('lock');
        $this->flash = 'Item locked.';
        unset($this->activeLocks);
    }

    public function release(int $lockId): void
    {
        Gate::authorize('unlock-inventory');

        app(InventoryLockService::class)->unlock(InventoryLock::findOrFail($lockId), auth()->user());

        $this->flash = 'Lock released.';
        unset($this->activeLocks);
    }

    public function render()
    {
        return view('livewire.admin.overrides-and-locks', [
            'types' => Override::TYPES,
            'lockTypes' => [
                'full' => 'Full — no sale, no edit, hidden',
                'sales' => 'Sales — cannot be sold, still editable',
                'edit' => 'Edit — frozen for editing, still sellable',
                'view' => 'View — hidden from storefront and POS',
                'rental' => 'Rental — cannot be rented (Phase 2)',
            ],
        ])->layout('layouts.admin-livewire', [
            'title' => 'Override & Lock',
            'heading' => 'Override & Lock',
            'subheading' => 'High-consequence actions · fully audited',
        ]);
    }
}
