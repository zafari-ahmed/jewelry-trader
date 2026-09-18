<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The six roles from CLAUDE.md Module 8. Permissions are granular so a Super
 * Admin can compose custom roles later; nothing checks a role name directly.
 *
 * Modules 4–10 append their own permissions to this seeder.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const SUPER_ADMIN = 'super-admin';

    /** @return array<string, string[]> role => permissions */
    public static function matrix(): array
    {
        return [
            'super-admin' => ['*'],
            'store-manager' => [
                'view-settings', 'manage-locations',
            ],
            'sales-staff' => [],
            'inventory-specialist' => [],
            'accountant' => [
                'view-settings',
            ],
            'customer-service' => [],
        ];
    }

    /** Module 1's permissions. Later modules add their own. */
    public static function permissions(): array
    {
        return [
            'manage-settings',
            'view-settings',
            'manage-locations',
            'manage-payments-config',
            'manage-ai-config',
            'manage-security-config',
            'manage-commission-config',
            'manage-feature-flags',
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::matrix() as $roleName => $granted) {
            $role = Role::findOrCreate($roleName, 'web');

            // Super Admin holds every permission, including ones added by later modules.
            $role->syncPermissions($granted === ['*'] ? Permission::all() : $granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
