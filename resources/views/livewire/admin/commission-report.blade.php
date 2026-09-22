@php $cols = '1.6fr 0.9fr 1fr 1fr 0.9fr 0.9fr'; @endphp
<div class="flex flex-col gap-18">
    <div class="flex flex-wrap items-center gap-10">
        <x-ui.select wire:model.live="period" class="w-200">
            <option value="day">Today</option>
            <option value="week">Week to date</option>
            <option value="month">Month to date</option>
        </x-ui.select>

        @can('view-all-commissions')
            <x-ui.select wire:model.live="userId" class="w-200">
                <option value="">All staff</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </x-ui.select>

            <x-ui.select wire:model.live="locationId" class="w-200">
                <option value="">All locations</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </x-ui.select>
        @endcan

        <x-ui.select wire:model.live="status" class="w-200">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="paid">Paid</option>
        </x-ui.select>

        @can('export-payroll')
            <x-ui.button wire:click="exportCsv" class="ml-auto">Export CSV</x-ui.button>
        @endcan
    </div>

    <div class="grid gap-16 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile label="Gross sales" :value="'$'.number_format($this->totals['sales'] / 100)" />
        <x-ui.stat-tile label="Commissionable" :value="'$'.number_format($this->totals['commissionable'] / 100)" />
        <x-ui.stat-tile label="Commission due" :value="'$'.number_format($this->totals['due'] / 100, 2)" accent="gold" />
        <x-ui.stat-tile label="Records" :value="$this->commissions->count()" />
    </div>

    @if ($this->commissions->isEmpty())
        <x-ui.empty-state title="No commissionable sales in this range" description="Widen the period or clear the filters." />
    @else
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Salesperson</div><div>Order</div><div>Plan</div><div>Commissionable</div><div>Status</div><div>Commission</div>
            </x-ui.table-row>

            @foreach ($this->commissions as $commission)
                <x-ui.table-row :cols="$cols" wire:key="c-{{ $commission->id }}">
                    <div>
                        <div class="font-semibold">{{ $commission->user?->name }}</div>
                        <div class="mt-3 text-caption text-muted">{{ $commission->order?->location?->name }}</div>
                    </div>
                    <div class="font-mono text-caption-lg">{{ $commission->order?->order_number }}</div>
                    <div class="text-caption-lg text-muted">{{ $commission->plan?->name ?? 'Settings default' }}</div>
                    <div>${{ number_format($commission->commissionable_cents / 100, 2) }}</div>
                    <div>
                        <x-ui.badge :variant="match ($commission->status) {
                            'paid' => 'listed', 'approved' => 'approved', default => 'pending',
                        }">{{ str($commission->status)->headline() }}</x-ui.badge>
                    </div>
                    <div class="font-semibold">${{ number_format($commission->amount_cents / 100, 2) }}</div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <x-ui.card title="California compliance">
            <div class="flex flex-col gap-12 text-body-sm leading-body">
                <div><strong>§2751</strong> — each staff assignment stores the written terms alongside the numeric plan.</div>
                <div><strong>§221</strong> — nothing here deducts from an earned commission. A refund leaves the record standing; any reduction goes through an override, so it is reasoned, approved and logged.</div>
                <div><strong>§204</strong> — approval and payment are recorded separately, so wage timing is auditable.</div>
            </div>
        </x-ui.card>
    @endif
</div>
