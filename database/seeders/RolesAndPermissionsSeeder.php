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
                'view-products', 'manage-products', 'approve-products',
                'view-customers', 'manage-customers',
                'view-orders', 'manage-orders', 'process-refunds',
                'manage-inventory-transfers', 'approve-inventory-transfers',
                'view-all-locations',
                'use-pos', 'apply-discount-above-threshold',
                'view-audit-log',
            ],
            'sales-staff' => [
                'view-products', 'manage-products',
                'view-customers', 'manage-customers',
                'view-orders', 'manage-orders',
                'manage-inventory-transfers',
                // The business wants staff to see other locations' stock
                // (docs/DECISIONS.md); the scoping exists for roles without it.
                'view-all-locations',
                // Sells at the register, but cannot discount past the ceiling.
                'use-pos',
            ],
            'inventory-specialist' => [
                'view-products', 'manage-products', 'approve-products',
                'manage-inventory-transfers',
                'view-all-locations',
            ],
            'accountant' => [
                'view-settings',
                'view-products', 'view-orders', 'view-customers',
                'view-all-locations',
                // Financial records are their remit, so the trail is too.
                'view-audit-log',
            ],
            'customer-service' => [
                'view-products', 'view-orders', 'view-customers',
                'manage-customers', 'process-refunds',
                // Processes returns at the register; no discounting, no pricing.
                'use-pos',
            ],
        ];
    }

    /** Permissions introduced by Modules 1 and 4. Later modules add their own. */
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

            // Module 4 — core platform
            'view-products',
            'manage-products',
            'approve-products',
            'view-customers',
            'manage-customers',
            'view-orders',
            'manage-orders',
            'process-refunds',
            'manage-inventory-transfers',
            'approve-inventory-transfers',
            // Cross-location visibility; without it a user sees only their own
            // location's inventory and orders (rule 3.7).
            'view-all-locations',

            // Module 5 — colour-coded field system
            'manage-field-rules',

            // Module 6 — point of sale
            'use-pos',
            'apply-discount-above-threshold',

            // Module 8 — security
            'view-audit-log',
            'manage-roles',
            'manage-mfa-settings',
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
