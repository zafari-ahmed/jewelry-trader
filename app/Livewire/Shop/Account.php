<?php

namespace App\Livewire\Shop;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Customer accounts run on their own guard: staff and customers are different
 * populations, and a customer must never hold a staff session.
 */
class Account extends Component
{
    public string $mode = 'login';       // login | register

    public array $form = ['email' => '', 'password' => '', 'name' => '', 'password_confirmation' => ''];

    public ?string $error = null;

    #[Computed]
    public function customer(): ?Customer
    {
        return Auth::guard('customer')->user();
    }

    #[Computed]
    public function orders()
    {
        return $this->customer
            ? Order::query()
                ->where('customer_id', $this->customer->id)
                ->whereIn('status', ['paid', 'fulfilled', 'refunded', 'partially_refunded'])
                ->with('items')
                ->latest('id')
                ->get()
            : collect();
    }

    public function login(): void
    {
        $this->reset('error');

        $credentials = $this->validate([
            'form.email' => ['required', 'email'],
            'form.password' => ['required', 'string'],
        ])['form'];

        if (! Auth::guard('customer')->attempt($credentials)) {
            throw ValidationException::withMessages(['form.email' => 'Those details do not match our records.']);
        }

        session()->regenerate();

        unset($this->customer, $this->orders);
        $this->reset('form');
    }

    public function register(): void
    {
        $this->reset('error');

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255'],
            'form.password' => ['required', 'string', 'min:8', 'confirmed'],
        ])['form'];

        $existing = Customer::where('email', $data['email'])->first();

        if ($existing?->password) {
            throw ValidationException::withMessages(['form.email' => 'An account already exists for that email.']);
        }

        // A guest who bought before keeps their order history: the record is
        // claimed rather than duplicated (docs/DECISIONS.md).
        $customer = $existing ?? new Customer(['email' => $data['email']]);
        $customer->fill(['name' => $data['name'], 'email' => $data['email']]);
        $customer->password = Hash::make($data['password']);
        $customer->save();

        Auth::guard('customer')->login($customer);
        session()->regenerate();

        unset($this->customer, $this->orders);
        $this->reset('form');
    }

    public function logout(): void
    {
        Auth::guard('customer')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirectRoute('shop.home', navigate: true);
    }

    public function render()
    {
        return view('livewire.shop.account')->layout('layouts.storefront-livewire', ['title' => 'Account']);
    }
}
