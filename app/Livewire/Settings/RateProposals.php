<?php

namespace App\Livewire\Settings;

use App\Models\RateChangeProposal;
use App\Services\Pricing\RateProposalService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Batch review of proposed rate changes.
 *
 * Approve in batch, reject in batch, inspect individually when something looks
 * wrong. The appraiser keeps the speed without losing the veto — and nothing
 * on this screen has changed a price until they say so.
 */
class RateProposals extends Component
{
    /** @var int[] */
    public array $selected = [];

    public string $note = '';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(): void
    {
        Gate::authorize('review-rate-proposals');
    }

    public function getProposalsProperty()
    {
        return RateChangeProposal::pending()
            ->orderBy('table_key')
            ->orderBy('entry_key')
            ->get();
    }

    /** What has been decided lately, so a mistake can be spotted and undone. */
    public function getRecentProperty()
    {
        return RateChangeProposal::whereIn('status', ['approved', 'rejected', 'stale'])
            ->with('decidedBy')
            ->latest('decided_at')
            ->limit(12)
            ->get();
    }

    public function toggleAll(): void
    {
        $all = $this->proposals->pluck('id')->all();

        $this->selected = count($this->selected) === count($all)
            ? []
            : array_map('strval', $all);
    }

    public function approveSelected(): void
    {
        Gate::authorize('approve-rate-proposals');

        if (! $this->guardSelection()) {
            return;
        }

        $result = app(RateProposalService::class)->approve(
            array_map('intval', $this->selected),
            Auth::id(),
            $this->note ?: null,
        );

        $this->reset('selected', 'note', 'error');

        $this->flash = $result['applied'].' '.str('rate')->plural($result['applied']).' updated.'
            .($result['stale'] > 0
                ? ' '.$result['stale'].' were left alone because somebody had already changed them.'
                : '');
    }

    public function rejectSelected(): void
    {
        Gate::authorize('approve-rate-proposals');

        if (! $this->guardSelection()) {
            return;
        }

        $count = app(RateProposalService::class)->reject(
            array_map('intval', $this->selected),
            Auth::id(),
            $this->note ?: null,
        );

        $this->reset('selected', 'note', 'error');

        $this->flash = $count.' '.str('proposal')->plural($count).' rejected. The rates are unchanged.';
    }

    private function guardSelection(): bool
    {
        $this->reset('flash', 'error');

        if ($this->selected === []) {
            $this->error = 'Nothing is selected.';

            return false;
        }

        return true;
    }

    public function render()
    {
        return view('livewire.settings.rate-proposals')->layout('layouts.admin-livewire', [
            'title' => 'Rate Proposals',
            'heading' => 'Rate proposals',
            'subheading' => 'Proposed changes to the pricing tables, awaiting your decision',
        ]);
    }
}
