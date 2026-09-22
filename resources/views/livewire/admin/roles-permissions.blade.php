@php $cols = '1.6fr repeat('.$roles->count().', 1fr)'; @endphp
<div class="flex flex-col gap-18">
    @if ($flash)
        <div class="rounded-surface border border-status-valid bg-status-valid-badge px-18 py-11 text-body-sm text-status-valid">{{ $flash }}</div>
    @endif

    <x-ui.table>
        <x-ui.table-row head :cols="$cols">
            <div>Permission</div>
            @foreach ($roles as $role)
                <div>{{ str($role->name)->headline() }}</div>
            @endforeach
        </x-ui.table-row>

        @foreach ($permissions as $permission)
            <x-ui.table-row :cols="$cols" wire:key="perm-{{ $permission->id }}">
                <div>
                    <div class="font-semibold">{{ str($permission->name)->headline() }}</div>
                    <div class="mt-3 font-mono text-caption text-muted">{{ $permission->name }}</div>
                </div>
                @foreach ($roles as $role)
                    <div class="{{ $role->permissions->contains('name', $permission->name) ? 'text-status-valid' : 'text-disabled' }}">
                        {{ $role->permissions->contains('name', $permission->name) ? '●' : '—' }}
                    </div>
                @endforeach
            </x-ui.table-row>
        @endforeach
    </x-ui.table>

    <div class="grid gap-16 xl:grid-split">
        <x-ui.card title="Edit a role">
            <div class="flex flex-wrap gap-9">
                @foreach ($roles as $role)
                    <button type="button" wire:click="edit({{ $role->id }})"
                        class="cursor-pointer rounded-surface border px-16 py-9 text-body transition-colors
                        {{ $editingRoleId === $role->id ? 'border-gold bg-gold-pressed font-semibold' : 'border-border-field bg-surface hover:border-gold' }}">
                        {{ str($role->name)->headline() }}
                    </button>
                @endforeach
            </div>

            @if ($editingRoleId)
                <form wire:submit="save" class="mt-18 border-t border-rule pt-15">
                    @foreach ($grouped as $group => $groupPermissions)
                        <div class="mb-15">
                            <x-ui.eyebrow class="mb-10">{{ str($group)->headline() }}</x-ui.eyebrow>
                            <div class="grid gap-10 md:grid-cols-2">
                                @foreach ($groupPermissions as $permission)
                                    <label class="flex cursor-pointer items-center gap-8 text-body-sm" wire:key="grant-{{ $permission->id }}">
                                        <input type="checkbox" wire:model="granted" value="{{ $permission->name }}" class="size-15 accent-navy" />
                                        {{ str($permission->name)->headline() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex flex-wrap gap-9 border-t border-rule pt-15">
                        <x-ui.button type="submit" variant="primary">Save role</x-ui.button>
                        <x-ui.button type="button" wire:click="cancel">Cancel</x-ui.button>
                    </div>
                </form>
            @endif
        </x-ui.card>

        <x-ui.card title="Add a role">
            <form wire:submit="createRole" class="flex flex-col gap-15">
                <div>
                    <label class="mb-5 block text-label font-semibold">Role name</label>
                    <x-ui.input wire:model="newRole" mono placeholder="workshop-lead" :status="$errors->has('newRole') ? 'red' : null" />
                    @error('newRole')<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    <div class="mt-4 text-caption text-muted">Nothing checks a role by name, so a custom role behaves exactly like a seeded one.</div>
                </div>
                <x-ui.button type="submit" variant="primary">Create role</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</div>
