<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';

    public ?int $editingId = null;

    public array $form = ['name' => '', 'email' => '', 'phone' => '', 'notes' => ''];

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        Gate::authorize('view', $customer);

        $this->editingId = $customer->id;
        $this->form = $customer->only(['name', 'email', 'phone', 'notes']);
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'form']);
    }

    public function save(): void
    {
        $customer = $this->editingId ? Customer::findOrFail($this->editingId) : null;

        $customer ? Gate::authorize('update', $customer) : Gate::authorize('create', Customer::class);

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255', 'unique:customers,email'.($this->editingId ? ','.$this->editingId : '')],
            'form.phone' => ['nullable', 'string', 'max:32'],
            'form.notes' => ['nullable', 'string'],
        ])['form'];

        $customer ? $customer->update($data) : Customer::create($data);

        $this->cancel();
    }

    public function delete(int $id): void
    {
        $customer = Customer::findOrFail($id);
        Gate::authorize('delete', $customer);

        $customer->delete();
    }

    public function render()
    {
        return view('livewire.customers.customer-list', [
            'customers' => Customer::query()
                ->withCount('orders')
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%")))
                ->orderBy('name')
                ->paginate(15),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Customers',
            'heading' => 'Customers',
            'subheading' => Customer::count().' records · shared by POS and the storefront',
        ]);
    }
}
