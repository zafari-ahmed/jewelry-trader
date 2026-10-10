<div>
    @include('livewire.settings.partials-saved')

    <form wire:submit="save" class="flex flex-col gap-16">
        <x-ui.card title="The gate">
            <div class="flex flex-col gap-12">
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model="state.enabled" class="size-16 accent-navy">
                    Run the quality gauge
                </label>
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model="state.block_listing_when_critical_fails" class="size-16 accent-navy">
                    A failed critical check stops a piece being listed
                </label>
                <label class="flex items-center gap-10 text-body-sm">
                    <input type="checkbox" wire:model="state.show_customer_panel" class="size-16 accent-navy">
                    Show verified facts on the customer page
                </label>
                <p class="text-caption text-muted">
                    Customers see specific attributable claims — never a score and never stars. The score
                    measures how complete a record is; a customer would read it as a judgement about the piece.
                </p>
                <div class="max-w-200">
                    <label class="mb-5 block text-label font-semibold">Photographs required</label>
                    <x-ui.input wire:model="state.minimum_photos" :status="$errors->has('state.minimum_photos') ? 'red' : null" />
                    @error('state.minimum_photos')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>
            </div>
        </x-ui.card>

        {{-- Which checks block a sale is a business decision, not one for
             the registry file. --}}
        <x-ui.card title="What each check is worth" meta="Critical blocks a sale · standard flags · optional is informational">
            @foreach ($checks as $stage => $stageChecks)
                <div class="{{ $loop->first ? '' : 'mt-18' }}">
                    <x-ui.eyebrow class="mb-8">{{ \App\Services\Quality\QualityCheckRegistry::STAGES[$stage] ?? $stage }}</x-ui.eyebrow>

                    <div class="overflow-x-auto">
                        <table class="w-full text-body-sm">
                            <tbody>
                                @foreach ($stageChecks as $check)
                                    <tr class="border-t border-rule" wire:key="qc-{{ $check['key'] }}">
                                        <td class="w-40 py-6 pr-8 align-middle text-caption text-muted">{{ $check['key'] }}</td>
                                        <td class="py-6 pr-10 align-middle">
                                            {{ $check['label'] }}
                                            <span class="ml-6 text-caption text-muted">
                                                {{ $check['derived'] ? 'read from the record' : 'needs a person' }}
                                            </span>
                                        </td>
                                        <td class="w-200 py-4">
                                            <x-ui.select wire:model="ranks.{{ \App\Livewire\Settings\QualityControl::fieldKey($check['key']) }}">
                                                <option value="">{{ ucfirst($check['type']) }} (default)</option>
                                                <option value="critical">Critical</option>
                                                <option value="standard">Standard</option>
                                                <option value="optional">Optional</option>
                                                <option value="disabled">Do not ask</option>
                                            </x-ui.select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </x-ui.card>

        <x-ui.button type="submit" variant="primary">Save quality settings</x-ui.button>
    </form>
</div>
