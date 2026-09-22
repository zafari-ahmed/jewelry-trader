@php $cols = '1fr 0.9fr 1fr 1fr 1.8fr'; @endphp
<div class="flex flex-col gap-18">
    <div class="flex flex-wrap items-center gap-10">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Filter by entity id or value…" class="w-230" />

        <x-ui.select wire:model.live="userId" class="w-200">
            <option value="">All users</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="action" class="w-200">
            <option value="">All actions</option>
            @foreach ($actions as $value)
                <option value="{{ $value }}">{{ $value }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.select wire:model.live="category" class="w-200">
            <option value="">All categories</option>
            @foreach ($categories as $value)
                <option value="{{ $value }}">{{ str($value)->headline() }}</option>
            @endforeach
        </x-ui.select>
    </div>

    @if ($logs->isEmpty())
        <x-ui.empty-state title="No events match these filters" description="The audit log is append-only — nothing has been deleted.">
            <x-ui.button wire:click="clearFilters">Clear filters</x-ui.button>
        </x-ui.empty-state>
    @else
        <x-ui.table>
            <x-ui.table-row head :cols="$cols">
                <div>Timestamp</div><div>User</div><div>Action</div><div>Entity</div><div>Before → after</div>
            </x-ui.table-row>

            @foreach ($logs as $log)
                <x-ui.table-row :cols="$cols" wire:key="log-{{ $log->id }}">
                    <div class="font-mono text-caption-lg text-muted">{{ $log->created_at?->format('M d H:i:s') }}</div>
                    <div>{{ $log->user?->name ?? 'System' }}</div>
                    <div>
                        <x-ui.badge :variant="match (true) {
                            str_contains($log->action, 'deleted'), str_contains($log->action, 'failed') => 'sold',
                            str_contains($log->action, 'created') => 'listed',
                            str_contains($log->action, 'updated') => 'approved',
                            default => 'draft',
                        }">{{ $log->action }}</x-ui.badge>
                        @if ($log->category === 'financial')
                            <div class="mt-3 text-tiny uppercase tracking-badge text-gold-ink">7-year retention</div>
                        @endif
                    </div>
                    <div class="font-mono text-caption-lg">
                        {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}
                    </div>
                    <div class="text-caption-lg">
                        @php
                            $changed = array_keys(($log->new_values ?? []) + ($log->old_values ?? []));
                            $shown = array_slice(array_diff($changed, ['id']), 0, 3);
                        @endphp
                        @forelse ($shown as $field)
                            <div class="truncate">
                                <span class="text-muted">{{ $field }}</span>
                                @if (isset($log->old_values[$field]))
                                    <span class="line-through">{{ Str::limit((string) json_encode($log->old_values[$field]), 24, '') }}</span> →
                                @endif
                                <strong>{{ Str::limit((string) json_encode($log->new_values[$field] ?? '—'), 24, '') }}</strong>
                            </div>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                        <div class="mt-3 text-caption text-muted">{{ $log->ip_address }}</div>
                    </div>
                </x-ui.table-row>
            @endforeach
        </x-ui.table>

        <div>{{ $logs->links() }}</div>
    @endif
</div>
