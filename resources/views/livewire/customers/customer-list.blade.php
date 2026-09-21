@php $cols = '1.6fr 1.6fr 1fr 0.6fr 0.8fr'; @endphp
<div class="grid gap-16 xl:grid-split">
    <div class="flex flex-col gap-16">
        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search name, email, or phone…" class="w-230" />

        @if ($customers->isEmpty())
            <x-ui.empty-state title="No customers yet" description="Walk-in sales do not need a customer record; one is created when an email is captured." />
        @else
            <x-ui.table>
                <x-ui.table-row head :cols="$cols">
                    <div>Name</div><div>Email</div><div>Phone</div><div>Orders</div><div>Actions</div>
                </x-ui.table-row>

                @foreach ($customers as $customer)
                    <x-ui.table-row :cols="$cols" wire:key="customer-{{ $customer->id }}">
                        <div class="font-semibold">{{ $customer->name }}</div>
                        <div class="text-caption-lg text-muted">{{ $customer->email ?? '—' }}</div>
                        <div class="text-caption-lg">{{ $customer->phone ?? '—' }}</div>
                        <div>{{ $customer->orders_count }}</div>
                        <div class="flex gap-10">
                            @can('update', $customer)
                                <button type="button" wire:click="edit({{ $customer->id }})" class="cursor-pointer text-meta hover:text-gold">Edit</button>
                            @endcan
                            @can('delete', $customer)
                                <button type="button" wire:click="delete({{ $customer->id }})" wire:confirm="Delete this customer?" class="cursor-pointer text-meta text-muted hover:text-status-required">Delete</button>
                            @endcan
                        </div>
                    </x-ui.table-row>
                @endforeach
            </x-ui.table>

            <div>{{ $customers->links() }}</div>
        @endif
    </div>

    @can('create', App\Models\Customer::class)
        <x-ui.card :title="$editingId ? 'Edit customer' : 'Add customer'">
            <form wire:submit="save" class="flex flex-col gap-15">
                @foreach ([['name', 'Name', true], ['email', 'Email', false], ['phone', 'Phone', false]] as [$field, $label, $required])
                    <div>
                        <label class="mb-5 block text-label font-semibold">{{ $label }}@if ($required)<span class="text-status-required">*</span>@endif</label>
                        <x-ui.input wire:model="form.{{ $field }}" :status="$errors->has('form.'.$field) ? 'red' : null" />
                        @error('form.'.$field)<div class="mt-4 text-caption text-status-required">{{ $message }}</div>@enderror
                    </div>
                @endforeach

                <div>
                    <label class="mb-5 block text-label font-semibold">Notes</label>
                    <x-ui.textarea wire:model="form.notes" rows="3" />
                </div>

                <div class="flex flex-wrap gap-9 border-t border-rule pt-15">
                    <x-ui.button type="submit" variant="primary">{{ $editingId ? 'Save customer' : 'Add customer' }}</x-ui.button>
                    @if ($editingId)
                        <x-ui.button type="button" wire:click="cancel">Cancel</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    @endcan
</div>
