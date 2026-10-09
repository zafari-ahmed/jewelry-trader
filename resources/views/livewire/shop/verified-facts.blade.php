<div>
@if ($this->claims)
        <div class="mt-24 rounded-surface border border-border-card bg-surface px-20 py-18">
            <div class="text-label font-semibold uppercase tracking-brand text-gold-ink">Verified by us</div>
    
            <div class="mt-12 flex flex-col gap-11">
                @foreach ($this->claims as $claim)
                    <div class="flex gap-10">
                        <span class="mt-3 shrink-0 text-status-valid">✓</span>
                        <div class="min-w-0">
                            <div class="text-body-sm font-semibold">
                                {{ $claim['claim'] }}@if ($claim['detail']) <span class="font-normal text-muted">— {{ $claim['detail'] }}</span>@endif
                            </div>
                            {{-- Role and date. The name is in the record and
                                 produced on request: publishing a named person's
                                 professional judgement is their decision. --}}
                            @if ($claim['by'])
                                <div class="text-caption text-muted">Verified by {{ $claim['by'] }}@if ($claim['on']) · {{ $claim['on'] }}@endif</div>
                            @endif
                        </div>
                    </div>
                @endforeach
    
                <div class="flex gap-10">
                    <span class="mt-3 shrink-0 text-status-valid">✓</span>
                    <div>
                        <div class="text-body-sm font-semibold">Full record available on request</div>
                        <div class="text-caption text-muted">Including the name of each person who verified the above.</div>
                    </div>
                </div>
            </div>
    
            @if ($sent)
                <div class="mt-14 rounded-surface border border-status-valid bg-status-valid-ground px-14 py-10 text-caption text-status-valid">{{ $sent }}</div>
            @elseif ($requesting)
                <form wire:submit="requestAppraisal" class="mt-14 flex flex-col gap-10 border-t border-rule pt-14">
                    <div>
                        <label class="mb-5 block text-label font-semibold">Your name</label>
                        <x-ui.input wire:model="name" :status="$errors->has('name') ? 'red' : null" />
                        @error('name')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Email</label>
                        <x-ui.input wire:model="email" type="email" :status="$errors->has('email') ? 'red' : null" />
                        @error('email')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="mb-5 block text-label font-semibold">Anything particular you would like to see?</label>
                        <x-ui.textarea wire:model="message" rows="3" />
                    </div>
                    <div class="flex gap-9">
                        <x-ui.button type="submit" variant="primary" size="sm">Send request</x-ui.button>
                        <x-ui.button type="button" size="sm" wire:click="$set('requesting', false)">Cancel</x-ui.button>
                    </div>
                </form>
            @else
                <x-ui.button type="button" size="sm" class="mt-14" wire:click="$set('requesting', true)">Request the full appraisal</x-ui.button>
            @endif
        </div>
    @endif
</div>
