<?php

namespace App\Livewire\Admin;

use App\Models\Commission;
use App\Models\Location;
use App\Models\User;
use App\Services\Commission\PayrollExporter;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class CommissionReport extends Component
{
    #[Url] public string $period = 'month';      // day | week | month

    #[Url] public string $userId = '';

    #[Url] public string $locationId = '';

    #[Url] public string $status = '';

    public function mount(): void
    {
        // Staff may see their own; managers and accountants see everyone's.
        Gate::authorize('view-own-commission');
    }

    #[Computed]
    public function commissions()
    {
        $query = Commission::query()
            ->with(['user', 'order.location', 'plan'])
            ->whereHas('order', fn ($q) => $q->where('paid_at', '>=', $this->from()))
            ->when($this->userId, fn ($q) => $q->where('user_id', (int) $this->userId))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->locationId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('location_id', (int) $this->locationId)));

        // Without view-all-commissions a user sees only their own figures.
        if (! auth()->user()->can('view-all-commissions')) {
            $query->where('user_id', auth()->id());
        }

        return $query->latest('id')->get();
    }

    #[Computed]
    public function totals(): array
    {
        $commissions = $this->commissions;

        return [
            'sales' => $commissions->pluck('order')->filter()->unique('id')->sum('total_cents'),
            'commissionable' => $commissions->sum('commissionable_cents'),
            'due' => $commissions->where('status', '!=', 'paid')->sum('amount_cents'),
            'total' => $commissions->sum('amount_cents'),
        ];
    }

    public function exportCsv()
    {
        Gate::authorize('export-payroll');

        $csv = app(PayrollExporter::class)->toCsv($this->commissions, now()->toDateString());

        return response()->streamDownload(
            fn () => print($csv),
            'commissions-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    private function from()
    {
        return match ($this->period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };
    }

    public function render()
    {
        return view('livewire.admin.commission-report', [
            'users' => User::query()->orderBy('name')->get(),
            'locations' => Location::query()->active()->orderBy('name')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Commission Report',
            'heading' => 'Commission Report',
            'subheading' => ucfirst($this->period).' to date',
        ]);
    }
}
