<?php

namespace App\Livewire\Settings;

use App\Models\Location;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Locations extends Component
{
    public ?int $editingId = null;

    public array $form = [
        'name' => '', 'street' => '', 'city' => '', 'state' => '', 'postal_code' => '',
        'tax_rate' => '0', 'phone' => '', 'timezone' => 'America/New_York', 'is_active' => true,
    ];

    public function mount(): void
    {
        Gate::authorize('manage-locations');
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-locations');

        $location = Location::findOrFail($id);
        $this->editingId = $location->id;
        $this->form = [
            'name' => $location->name,
            'street' => $location->street ?? '',
            'city' => $location->city ?? '',
            'state' => $location->state ?? '',
            'postal_code' => $location->postal_code ?? '',
            // Stored as a rate (0.08875); shown as a percentage (8.875).
            'tax_rate' => (string) round($location->tax_rate * 100, 4),
            'phone' => $location->phone ?? '',
            'timezone' => $location->timezone,
            'is_active' => $location->is_active,
        ];
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'form']);
    }

    public function save(): void
    {
        Gate::authorize('manage-locations');

        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.street' => ['nullable', 'string', 'max:255'],
            'form.city' => ['nullable', 'string', 'max:255'],
            'form.state' => ['nullable', 'string', 'size:2'],
            'form.postal_code' => ['nullable', 'string', 'max:16'],
            'form.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.phone' => ['nullable', 'string', 'max:32'],
            'form.timezone' => ['required', 'timezone'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $data['state'] = $data['state'] ? strtoupper($data['state']) : null;
        $data['tax_rate'] = $data['tax_rate'] / 100;
        $data['slug'] = Str::slug($data['name']);

        if ($this->editingId) {
            Location::findOrFail($this->editingId)->update($data);
        } else {
            $this->validate(['form.name' => [Rule::unique('locations', 'name')]]);
            Location::create($data);
        }

        $this->cancel();
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('manage-locations');

        $location = Location::findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);
    }

    public function render()
    {
        return view('livewire.settings.locations', [
            'locations' => Location::query()->orderBy('name')->get(),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Locations',
            'heading' => 'Locations',
            'subheading' => 'Store locations, tax profile and transfer endpoints',
        ]);
    }
}
