<div class="flex flex-col gap-16">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-ground px-16 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-surface border border-status-required bg-status-required-ground px-16 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif

    <x-ui.card title="Proposed changes" :meta="count($this->proposals).' awaiting a decision'">
        @if ($this->proposals->isEmpty())
            <p class="text-body-sm text-muted">Nothing is waiting. Proposed rate changes appear here for review before they touch a single price.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-body-sm">
                    <thead>
                        <tr class="text-label uppercase tracking-brand text-muted">
                            <th class="py-6 pr-8 text-left font-semibold">
                                <input type="checkbox" wire:click="toggleAll"
                                    @checked(count($selected) === count($this->proposals))
                                    class="size-16 accent-navy" aria-label="Select all">
                            </th>
                            <th class="py-6 pr-10 text-left font-semibold">Table</th>
                            <th class="py-6 pr-10 text-left font-semibold">Entry</th>
                            <th class="py-6 pr-10 text-right font-semibold">Current</th>
                            <th class="py-6 pr-10 text-right font-semibold">Proposed</th>
                            <th class="py-6 pr-10 text-right font-semibold">Change</th>
                            <th class="py-6 pr-10 text-left font-semibold">Why</th>
                            <th class="py-6 text-right font-semibold">Confidence</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->proposals as $proposal)
                            @php $change = $proposal->changePercent(); @endphp
                            <tr class="border-t border-rule" wire:key="proposal-{{ $proposal->id }}">
                                <td class="py-8 pr-8 align-top">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $proposal->id }}"
                                        class="size-16 accent-navy" aria-label="Select this proposal">
                                </td>
                                <td class="py-8 pr-10 align-top">{{ $proposal->tableLabel() }}</td>
                                <td class="py-8 pr-10 align-top font-semibold">{{ str($proposal->entry_key)->headline() }}</td>
                                <td class="py-8 pr-10 text-right align-top text-muted">
                                    {{ $proposal->value_at_proposal === null ? 'new' : rtrim(rtrim(number_format($proposal->value_at_proposal, 4), '0'), '.') }}
                                </td>
                                <td class="py-8 pr-10 text-right align-top font-semibold">
                                    {{ rtrim(rtrim(number_format($proposal->proposed_value, 4), '0'), '.') }}
                                </td>
                                <td class="py-8 pr-10 text-right align-top {{ $change === null ? 'text-muted' : ($change > 0 ? 'text-status-valid' : 'text-status-required') }}">
                                    {{ $change === null ? '—' : ($change > 0 ? '+' : '').$change.'%' }}
                                </td>
                                <td class="py-8 pr-10 align-top text-muted">
                                    {{ $proposal->reason ?: '—' }}
                                    @if ($proposal->source)
                                        <span class="block text-caption">{{ $proposal->source }}</span>
                                    @endif
                                    {{-- A rate someone has edited since is held back rather than overwritten. --}}
                                    @if ($proposal->isStale())
                                        <span class="mt-4 block text-caption text-status-override">
                                            Changed by hand since this was proposed — approving will leave your value alone.
                                        </span>
                                    @endif
                                </td>
                                <td class="py-8 text-right align-top text-muted">
                                    {{ $proposal->confidence === null ? '—' : $proposal->confidence.'%' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-15 border-t border-rule pt-12">
                <label class="mb-5 block text-label font-semibold">Note (optional)</label>
                <x-ui.input wire:model="note" placeholder="Why you decided this way" />
            </div>

            @can('approve-rate-proposals')
                <div class="mt-12 flex flex-wrap items-center gap-9">
                    <x-ui.button wire:click="approveSelected" variant="primary">
                        Approve selected ({{ count($selected) }})
                    </x-ui.button>
                    <x-ui.button wire:click="rejectSelected">Reject selected</x-ui.button>
                    <span class="text-caption text-muted">Nothing changes until you approve it.</span>
                </div>
            @else
                <div class="mt-12 text-caption text-muted">You can review these, but approving them needs an appraiser.</div>
            @endcan
        @endif
    </x-ui.card>

    @if ($this->recent->isNotEmpty())
        <x-ui.card title="Recently decided">
            @foreach ($this->recent as $proposal)
                <div class="flex flex-wrap items-baseline justify-between gap-10 border-b border-rule py-7 last:border-0">
                    <div>
                        <span class="font-semibold">{{ str($proposal->entry_key)->headline() }}</span>
                        <span class="text-muted">· {{ $proposal->tableLabel() }}</span>
                        <span class="block text-caption text-muted">
                            {{ $proposal->decidedBy?->name ?? 'System' }} · {{ $proposal->decided_at?->diffForHumans() }}
                            @if ($proposal->decision_note) · {{ $proposal->decision_note }} @endif
                        </span>
                    </div>
                    <span class="text-caption font-semibold uppercase tracking-brand
                        {{ $proposal->status === 'approved' ? 'text-status-valid' : ($proposal->status === 'stale' ? 'text-status-override' : 'text-muted') }}">
                        {{ $proposal->status === 'stale' ? 'left alone' : $proposal->status }}
                    </span>
                </div>
            @endforeach
        </x-ui.card>
    @endif
</div>
