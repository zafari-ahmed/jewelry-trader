@php
    $cols = '1.4fr 1fr 0.9fr 0.8fr 1fr 0.7fr';
    $tone = ['red' => 'required', 'yellow' => 'suggested', 'green' => 'valid', 'gray' => 'na', 'blue' => 'override'];
@endphp
<div class="flex flex-col gap-18">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-badge px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <div class="grid gap-16 xl:grid-split">
        <div class="flex flex-col gap-16">
            <div class="flex flex-wrap items-center gap-10">
                <x-ui.eyebrow>Preview as category</x-ui.eyebrow>
                <x-ui.select wire:model.live="category" class="w-200">
                    <option value="">Defaults (no category)</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                    @endforeach
                </x-ui.select>
                <span class="text-caption text-muted">{{ $effective->where('is_required', true)->where('color', '!=', 'gray')->count() }} required fields for this category</span>
            </div>

            <x-ui.table>
                <x-ui.table-row head :cols="$cols">
                    <div>Field</div><div>Applies to</div><div>Colour</div><div>Required</div><div>Section</div><div>Actions</div>
                </x-ui.table-row>

                @foreach ($rules as $rule)
                    <x-ui.table-row :cols="$cols" wire:key="rule-{{ $rule->id }}">
                        <div>
                            <div class="font-mono text-caption-lg font-semibold">{{ $rule->field_name }}</div>
                            <div class="mt-3 text-caption text-muted">{{ $rule->humanLabel() }}</div>
                        </div>
                        <div class="text-caption-lg">{{ $rule->category ? str($rule->category)->headline() : 'All categories' }}</div>
                        <div>
                            <span class="flex items-center gap-7 text-caption-lg">
                                <span class="size-8 rounded-full bg-status-{{ $tone[$rule->color] }}"></span>{{ str($rule->color)->headline() }}
                            </span>
                        </div>
                        <div class="text-caption-lg">{{ $rule->is_required ? 'Yes' : '—' }}</div>
                        <div class="text-caption-lg text-muted">{{ str($rule->section)->headline() }}</div>
                        <div class="flex gap-10">
                            <button type="button" wire:click="edit({{ $rule->id }})" class="cursor-pointer text-meta hover:text-gold">Edit</button>
                            <button type="button" wire:click="delete({{ $rule->id }})" wire:confirm="Remove this rule?" class="cursor-pointer text-meta text-muted hover:text-status-required">Delete</button>
                        </div>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table>
        </div>

        <x-ui.card :title="$editingId ? 'Edit rule' : 'Add rule'">
            <form wire:submit="save" class="flex flex-col gap-15">
                <div>
                    <label class="mb-5 block text-label font-semibold">Field name</label>
                    <x-ui.input wire:model="form.field_name" mono placeholder="ring_size" :status="$errors->has('form.field_name') ? 'red' : null" />
                    <div class="mt-4 text-caption text-muted">A name with no product column is stored as an item attribute — no migration needed.</div>
                    @error('form.field_name')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Applies to</label>
                    <x-ui.select wire:model="form.category">
                        <option value="">All categories (default)</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->slug }}">{{ $cat->name }} only</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Colour</label>
                    <x-ui.select wire:model.live="form.color">
                        <option value="red">Red — required, blocks submission</option>
                        <option value="yellow">Yellow — AI-suggested, verify</option>
                        <option value="green">Green — optional, complete when filled</option>
                        <option value="gray">Gray — not applicable</option>
                        <option value="blue">Blue — human override</option>
                    </x-ui.select>
                </div>

                @if ($form['color'] !== 'gray')
                    <label class="flex cursor-pointer items-center gap-8 text-body-sm">
                        <input type="checkbox" wire:model="form.is_required" class="size-15 accent-navy" />Required before submission
                    </label>
                @endif

                <div>
                    <label class="mb-5 block text-label font-semibold">Section</label>
                    <x-ui.input wire:model="form.section" placeholder="materials" />
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Label</label>
                    <x-ui.input wire:model="form.label" placeholder="Ring size" />
                </div>

                <div>
                    <label class="mb-5 block text-label font-semibold">Helper text</label>
                    <x-ui.input wire:model="form.help" />
                </div>

                <div class="flex flex-wrap gap-9 border-t border-rule pt-15">
                    <x-ui.button type="submit" variant="primary">{{ $editingId ? 'Save rule' : 'Add rule' }}</x-ui.button>
                    @if ($editingId)
                        <x-ui.button type="button" wire:click="cancel">Cancel</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    </div>
</div>
