<div class="flex flex-col gap-16">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-ground px-16 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <x-ui.card :title="$openCount.' open'" meta="A request for documentation on a high-value piece is a warm lead, not an inbox chore">
        <div class="mb-14 flex flex-wrap gap-8">
            @foreach (['open' => 'Open', 'handled' => 'Sent', 'all' => 'Everything'] as $value => $label)
                <button type="button" wire:click="$set('filter', '{{ $value }}')"
                    class="cursor-pointer rounded-surface border px-13 py-7 text-caption transition-colors
                        {{ $filter === $value ? 'border-gold bg-gold-pressed font-semibold' : 'border-border-field bg-surface hover:border-gold' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @forelse ($requests as $request)
            <div class="flex flex-wrap items-start justify-between gap-12 border-b border-rule py-11 last:border-0"
                wire:key="req-{{ $request->id }}">
                <div class="min-w-0 flex-1">
                    <div class="text-body-sm font-semibold">
                        {{ $request->name }}
                        <a href="mailto:{{ $request->email }}" class="ml-6 font-normal text-gold-ink underline">{{ $request->email }}</a>
                    </div>
                    <div class="mt-3 text-caption text-muted">
                        @if ($request->product)
                            <a href="{{ route('admin.inventory.edit', $request->product) }}" class="text-gold-ink underline">
                                {{ $request->product->sku }} · {{ $request->product->title }}
                            </a>
                        @else
                            The piece has since been removed
                        @endif
                        · {{ $request->created_at->diffForHumans() }}
                    </div>
                    @if ($request->message)
                        <p class="mt-7 max-w-prose text-caption-lg">{{ $request->message }}</p>
                    @endif
                    @if ($request->status === 'handled')
                        <div class="mt-5 text-caption text-status-valid">Sent {{ $request->handled_at?->diffForHumans() }}</div>
                    @endif
                </div>

                <div class="flex gap-7">
                    @if ($request->status === 'open')
                        <x-ui.button type="button" size="sm" variant="approve" wire:click="markHandled({{ $request->id }})">Mark sent</x-ui.button>
                    @else
                        <x-ui.button type="button" size="sm" wire:click="reopen({{ $request->id }})">Reopen</x-ui.button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-body-sm text-muted">Nothing here.</p>
        @endforelse

        <div class="mt-14">{{ $requests->links() }}</div>
    </x-ui.card>
</div>
