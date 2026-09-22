<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A Super Admin composes roles from granular permissions here — nothing in the
 * codebase checks a role name, so a custom role works exactly like a seeded one.
 */
class RolesPermissions extends Component
{
    public ?int $editingRoleId = null;

    public array $granted = [];

    public string $newRole = '';

    public ?string $flash = null;

    public function mount(): void
    {
        Gate::authorize('manage-roles');
    }

    public function edit(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        $this->editingRoleId = $role->id;
        $this->granted = $role->permissions->pluck('name')->all();
    }

    public function cancel(): void
    {
        $this->reset(['editingRoleId', 'granted']);
    }

    public function save(): void
    {
        Gate::authorize('manage-roles');

        $role = Role::findOrFail($this->editingRoleId);

        // The Super Admin role cannot be narrowed: something must always be
        // able to restore the others.
        if ($role->name === \Database\Seeders\RolesAndPermissionsSeeder::SUPER_ADMIN) {
            $this->flash = 'Super Admin always holds every permission and cannot be narrowed.';

            return;
        }

        $role->syncPermissions($this->granted);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->flash = "Updated {$role->name}.";
        $this->cancel();
    }

    public function createRole(): void
    {
        Gate::authorize('manage-roles');

        $this->validate(['newRole' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9-]*$/', 'unique:roles,name']],
            messages: ['newRole.regex' => 'Use a lowercase, hyphenated name such as "workshop-lead".']);

        Role::findOrCreate($this->newRole, 'web');

        $this->flash = "Created {$this->newRole}.";
        $this->reset('newRole');
    }

    public function render()
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return view('livewire.admin.roles-permissions', [
            'roles' => Role::with('permissions')->orderBy('id')->get(),
            'permissions' => $permissions,
            // Grouped by the verb they gate, so a long list stays readable.
            'grouped' => $permissions->groupBy(fn ($p) => str($p->name)->after('-')->before('-')->toString()),
        ])->layout('layouts.admin-livewire', [
            'title' => 'Roles & Permissions',
            'heading' => 'Roles & Permissions',
            'subheading' => Role::count().' roles · '.$permissions->count().' permission scopes',
        ]);
    }
}
