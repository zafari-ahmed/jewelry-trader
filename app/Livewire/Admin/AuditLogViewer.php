<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AuditLogViewer extends Component
{
    use WithPagination;

    #[Url] public string $search = '';

    #[Url] public string $userId = '';

    #[Url] public string $action = '';

    #[Url] public string $category = '';

    public function mount(): void
    {
        Gate::authorize('view-audit-log');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'userId', 'action', 'category']);
    }

    public function render()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('auditable_id', $this->search)
                ->orWhere('auditable_type', 'like', "%{$this->search}%")
                ->orWhere('new_values', 'like', "%{$this->search}%")
                ->orWhere('old_values', 'like', "%{$this->search}%")))
            ->when($this->userId, fn ($q) => $q->where('user_id', (int) $this->userId))
            ->when($this->action, fn ($q) => $q->where('action', 'like', $this->action.'%'))
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.audit-log', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'categories' => AuditLog::query()->distinct()->orderBy('category')->pluck('category'),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Audit Log',
            'heading' => 'Audit Log',
            'subheading' => 'Append-only · '.number_format(AuditLog::count()).' events retained',
        ]);
    }
}
