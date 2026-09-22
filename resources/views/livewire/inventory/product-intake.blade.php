<div class="flex flex-col gap-18">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-badge px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-surface border border-status-required bg-status-required-ground px-18 py-11 text-body-sm text-status-required">{{ $error }}</div>
    @endif

    {{-- Step rail --}}
    <div class="flex flex-wrap items-center gap-10">
        @foreach ([1 => 'Photography', 2 => 'Item record', 3 => 'Review'] as $number => $label)
            <button type="button" wire:click="goToStep({{ $number }})"
                class="flex cursor-pointer items-center gap-8 rounded-surface border px-16 py-9 text-body transition-colors
                {{ $step === $number ? 'border-gold bg-gold-pressed font-semibold' : 'border-border-field bg-surface hover:border-gold' }}">
                <span class="flex size-17 items-center justify-center rounded-full border {{ $step > $number ? 'border-status-valid text-status-valid' : 'border-muted text-muted' }} text-tiny">{{ $step > $number ? '✓' : $number }}</span>
                {{ $label }}
            </button>
        @endforeach

        <div class="ml-auto flex items-center gap-14">
            <x-ui.eyebrow>Field status key</x-ui.eyebrow>
            @foreach ([['required', 'Required'], ['suggested', 'Suggested'], ['valid', 'Complete'], ['na', 'N/A'], ['override', 'Override']] as [$tone, $label])
                <span class="flex items-center gap-7 text-caption text-muted">
                    <span class="size-8 rounded-full bg-status-{{ $tone }}"></span>{{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            {{-- Step 1 · Photography --}}
            @if ($step === 1)
                <x-ui.card>
                    <x-slot:header>
                        <h2 class="font-serif text-display-xs font-semibold">Photography</h2>
                        <span class="text-label text-muted">{{ $product?->images->count() ?? 0 }} of 5–15 suggested</span>
                    </x-slot:header>

                    <div class="rounded-surface border border-dashed border-border-field bg-ivory-raised px-20 py-22 text-center">
                        <input type="file" wire:model="photos" multiple accept="image/*" class="mx-auto block text-body-sm" />
                        <div class="mt-10 text-caption text-muted">JPEG or PNG, up to 12 MB each. Tag each shot by type before adding.</div>
                        @error('photos.*')<div class="mt-8 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>

                    @if ($photos)
                        <div class="mt-15 flex flex-col gap-10">
                            @foreach ($photos as $index => $photo)
                                <div class="flex flex-wrap items-center gap-12 rounded-surface border border-border-card px-13 py-10">
                                    <span class="flex-1 truncate text-body-sm">{{ $photo->getClientOriginalName() }}</span>
                                    <x-ui.select wire:model="photoTypes.{{ $index }}" class="w-200">
                                        @foreach ($imageTypes as $type)
                                            <option value="{{ $type }}">{{ str($type)->headline() }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>
                            @endforeach
                            <x-ui.button wire:click="storePhotos" variant="primary" wire:loading.attr="disabled" class="self-start">Add photos</x-ui.button>
                        </div>
                    @endif

                    @if ($product?->images->isNotEmpty())
                        <div class="mt-18 grid grid-cols-3 gap-10 border-t border-rule pt-15">
                            @foreach ($product->images as $image)
                                <div class="rounded-surface border {{ $image->is_primary ? 'border-gold' : 'border-border-card' }} p-8" wire:key="image-{{ $image->id }}">
                                    <img src="{{ Storage::url($image->file_path) }}" alt="{{ $image->type }}" class="aspect-square w-full rounded-surface object-cover" />
                                    <div class="mt-8 flex items-center justify-between gap-8">
                                        <span class="text-eyebrow uppercase tracking-eyebrow text-muted">{{ $image->type }}</span>
                                        <div class="flex gap-8">
                                            @unless ($image->is_primary)
                                                <button type="button" wire:click="setPrimary({{ $image->id }})" class="cursor-pointer text-caption text-muted hover:text-gold">Hero</button>
                                            @endunless
                                            <button type="button" wire:click="removePhoto({{ $image->id }})" class="cursor-pointer text-caption text-muted hover:text-status-required">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-12 text-caption text-muted">The hero image is used on the storefront card.</div>
                    @endif

                    <div class="mt-18 flex gap-9 border-t border-rule pt-15">
                        <x-ui.button wire:click="goToStep(2)" variant="primary">Continue to item record</x-ui.button>
                    </div>
                </x-ui.card>
            @endif

            {{-- Step 2 · Field entry, every field in its traffic-light colour --}}
            @if ($step === 2)
                @foreach ($this->sections as $section => $rules)
                    <x-ui.card>
                        <x-slot:header>
                            <h2 class="font-serif text-display-xs font-semibold">{{ str($section)->headline() }}</h2>
                        </x-slot:header>

                        <div class="grid gap-15 md:grid-cols-2">
                            @foreach ($rules as $rule)
                                @php $color = $this->colorFor($rule); @endphp
                                <div wire:key="field-{{ $rule['field_name'] }}" class="{{ in_array($rule['field_name'], ['condition_notes', 'customer_description', 'internal_description'], true) ? 'md:col-span-2' : '' }}">
                                    <x-ui.field-status
                                        :color="$color"
                                        :label="$rule['label'] ?: str($rule['field_name'])->headline()"
                                        :required="$rule['is_required'] && $color !== 'gray'"
                                        :helper="$color === 'red' ? ($rule['help'] ?: 'Required before this item can be submitted for review.') : ($color === 'yellow' ? 'AI suggestion available in a future update.' : ($color === 'blue' ? 'Overridden by a human.' : $rule['help']))"
                                    >
                                        @if ($rule['field_name'] === 'category')
                                            <x-ui.select wire:model.live="values.category" :status="$color">
                                                <option value="">Select a category…</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->slug }}">{{ $category->name }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @elseif ($rule['field_name'] === 'location_id')
                                            <x-ui.select wire:model.live="values.location_id" :status="$color">
                                                <option value="">Select a location…</option>
                                                @foreach ($locations as $location)
                                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                                @endforeach
                                            </x-ui.select>
                                        @elseif (in_array($rule['field_name'], ['condition_notes', 'customer_description', 'internal_description'], true))
                                            <x-ui.textarea wire:model.blur="values.{{ $rule['field_name'] }}" :status="$color" rows="3" :disabled="$color === 'gray'" />
                                        @else
                                            <x-ui.input wire:model.blur="values.{{ $rule['field_name'] }}" :status="$color" :disabled="$color === 'gray'"
                                                :placeholder="$color === 'gray' ? 'Not applicable for this category' : ''" />
                                        @endif
                                    </x-ui.field-status>
                                    @error('values.'.$rule['field_name'])<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endforeach

                <x-ui.card padded="false" class="px-20 py-16">
                    <div class="flex flex-wrap items-center gap-9">
                        <x-ui.button wire:click="saveDraft">Save as draft</x-ui.button>
                        <x-ui.button wire:click="submitForReview" variant="primary" :disabled="count($this->missing) > 0">Submit for review</x-ui.button>
                        @if (count($this->missing) > 0)
                            <span class="text-caption text-status-required">{{ count($this->missing) }} required {{ Str::plural('field', count($this->missing)) }} remaining</span>
                        @else
                            <span class="text-caption text-status-valid">Every required field is complete.</span>
                        @endif
                    </div>
                </x-ui.card>
            @endif

            {{-- Step 3 · Submitted --}}
            @if ($step === 3)
                <x-ui.card title="Submitted for review">
                    <div class="flex flex-col gap-12">
                        <div class="text-body-sm">{{ $product?->sku }} · {{ $product?->title }}</div>
                        @if ($product?->intakeMinutes())
                            <div class="text-caption text-muted">Intake took {{ $product->intakeMinutes() }} minutes from first save to submission.</div>
                        @endif
                        <div class="flex flex-wrap gap-9 border-t border-rule pt-15">
                            <x-ui.button :href="route('admin.review')" variant="primary">Open review queue</x-ui.button>
                            <x-ui.button :href="route('admin.inventory.create')">Intake another item</x-ui.button>
                        </div>
                    </div>
                </x-ui.card>
            @endif
        </div>

        {{-- Completeness panel --}}
        <div class="flex flex-col gap-16">
            <x-ui.card title="Record completeness">
                @php $c = $this->completeness; @endphp
                <div class="flex flex-col gap-10">
                    @foreach ([
                        ['Complete', $c['complete'], 'valid'],
                        ['Required, missing', $c['missing'], 'required'],
                        ['Awaiting verification', $c['awaiting'], 'suggested'],
                        ['Overridden', $c['overridden'], 'override'],
                        ['Optional, empty', $c['optional'], 'na'],
                        ['Not applicable', $c['not_applicable'], 'na'],
                    ] as [$label, $count, $tone])
                        <div class="flex items-center justify-between text-body-sm">
                            <span class="flex items-center gap-7"><span class="size-8 rounded-full bg-status-{{ $tone }}"></span>{{ $label }}</span>
                            <span class="font-semibold">{{ $count }}</span>
                        </div>
                    @endforeach

                    <div class="mt-4 h-5 w-full overflow-hidden rounded-surface bg-disabled-ground">
                        {{-- Data-driven width: a computed percentage cannot be a token --}}
                        <div class="h-full bg-status-valid" style="width: {{ $c['percent'] }}%"></div>
                    </div>
                    <div class="text-caption text-muted">{{ $c['percent'] }}% ready for review</div>
                </div>
            </x-ui.card>

            <x-ui.card title="AI assistance">
                <livewire:inventory.ai-auto-fill />
                <div class="mt-12 text-caption text-muted">Yellow fields are the ones auto-fill will populate in Phase 2. Every suggestion stays yellow until a human verifies it.</div>
            </x-ui.card>

            @can('manage-settings')
                <x-ui.card title="Field rules">
                    <div class="text-caption text-muted">Which fields are required, suggested or not applicable is configuration, editable per category.</div>
                    <a href="{{ route('admin.settings.field-rules') }}" class="mt-12 inline-block text-meta text-muted hover:text-gold">Edit field rules</a>
                </x-ui.card>
            @endcan
        </div>
    </div>
</div>
