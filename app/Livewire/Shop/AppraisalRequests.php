<?php

namespace App\Livewire\Shop;

use App\Models\AppraisalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The inbox behind "full record available on request".
 *
 * A customer asking for the documentation on a high-value piece is a warm
 * lead and a promise to keep, so the request is a worked item with an owner
 * and a closing state — not an email somebody may or may not have seen.
 */
class AppraisalRequests extends Component
{
    use WithPagination;

    public string $filter = 'open';

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('handle-appraisal-requests');
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markHandled(int $id): void
    {
        Gate::authorize('handle-appraisal-requests');

        AppraisalRequest::whereKey($id)->update([
            'status' => 'handled',
            'handled_by' => Auth::id(),
            'handled_at' => now(),
        ]);

        $this->flash = 'Marked as sent.';
    }

    public function reopen(int $id): void
    {
        Gate::authorize('handle-appraisal-requests');

        AppraisalRequest::whereKey($id)->update([
            'status' => 'open',
            'handled_by' => null,
            'handled_at' => null,
        ]);

        $this->flash = 'Reopened.';
    }

    public function render()
    {
        return view('livewire.shop.appraisal-requests', [
            'requests' => AppraisalRequest::query()
                ->with(['product'])
                ->when($this->filter !== 'all', fn ($q) => $q->where('status', $this->filter))
                ->latest('id')
                ->paginate(20),
            'openCount' => AppraisalRequest::where('status', 'open')->count(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Appraisal Requests',
            'heading' => 'Appraisal requests',
            'subheading' => 'Customers asking for the full record behind a piece',
        ]);
    }
}
