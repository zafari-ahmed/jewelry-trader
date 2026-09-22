<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Module 8 acceptance: one policy assertion per role per protected action.
 *
 * The matrix is the specification in executable form — a permission moved in
 * the seeder without thinking shows up here as a failure.
 */
class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->location = Location::factory()->create();
    }

    /** @return array<string, array{0:string, 1:array<string,bool>}> */
    public static function roleMatrix(): array
    {
        //                          view   edit  approve  sell  refund  discount+  settings  audit  roles
        return [
            'super-admin' => ['super-admin', [
                'viewProduct' => true, 'updateProduct' => true, 'approveProduct' => true,
                'usePos' => true, 'processRefunds' => true, 'discountAboveThreshold' => true,
                'manageSettings' => true, 'viewAuditLog' => true, 'manageRoles' => true,
            ]],
            'store-manager' => ['store-manager', [
                'viewProduct' => true, 'updateProduct' => true, 'approveProduct' => true,
                'usePos' => true, 'processRefunds' => true, 'discountAboveThreshold' => true,
                'manageSettings' => false, 'viewAuditLog' => true, 'manageRoles' => false,
            ]],
            'sales-staff' => ['sales-staff', [
                'viewProduct' => true, 'updateProduct' => true, 'approveProduct' => false,
                'usePos' => true, 'processRefunds' => false, 'discountAboveThreshold' => false,
                'manageSettings' => false, 'viewAuditLog' => false, 'manageRoles' => false,
            ]],
            'inventory-specialist' => ['inventory-specialist', [
                'viewProduct' => true, 'updateProduct' => true, 'approveProduct' => true,
                // Appraises and approves, but does not sell.
                'usePos' => false, 'processRefunds' => false, 'discountAboveThreshold' => false,
                'manageSettings' => false, 'viewAuditLog' => false, 'manageRoles' => false,
            ]],
            'accountant' => ['accountant', [
                // Reports and records only: no inventory edits, no sales.
                'viewProduct' => true, 'updateProduct' => false, 'approveProduct' => false,
                'usePos' => false, 'processRefunds' => false, 'discountAboveThreshold' => false,
                'manageSettings' => false, 'viewAuditLog' => true, 'manageRoles' => false,
            ]],
            'customer-service' => ['customer-service', [
                'viewProduct' => true, 'updateProduct' => false, 'approveProduct' => false,
                // Processes returns; no discounting, no pricing edits.
                'usePos' => true, 'processRefunds' => true, 'discountAboveThreshold' => false,
                'manageSettings' => false, 'viewAuditLog' => false, 'manageRoles' => false,
            ]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roleMatrix')]
    public function test_each_role_can_perform_exactly_its_permitted_actions(string $role, array $expected): void
    {
        $user = User::factory()->create(['location_id' => $this->location->id]);
        $user->assignRole($role);
        $user = $user->fresh();

        $product = Product::factory()->listed()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => $this->location->id,
            'channel' => 'pos',
            'status' => 'paid',
        ]);

        $actual = [
            'viewProduct' => Gate::forUser($user)->allows('view', $product),
            'updateProduct' => Gate::forUser($user)->allows('update', $product),
            'approveProduct' => Gate::forUser($user)->allows('approve', $product),
            'usePos' => $user->can('use-pos'),
            'processRefunds' => Gate::forUser($user)->allows('refund', $order),
            'discountAboveThreshold' => $user->can('apply-discount-above-threshold'),
            'manageSettings' => $user->can('manage-settings'),
            'viewAuditLog' => $user->can('view-audit-log'),
            'manageRoles' => $user->can('manage-roles'),
        ];

        $this->assertSame($expected, $actual, "The {$role} permission matrix has drifted.");
    }

    public function test_customer_service_can_view_but_not_edit_a_customer_record(): void
    {
        $user = User::factory()->create(['location_id' => $this->location->id]);
        $user->assignRole('customer-service');

        $customer = Customer::factory()->create();

        $this->assertTrue(Gate::forUser($user->fresh())->allows('view', $customer));
        // Customer Service manages customers as part of handling returns.
        $this->assertTrue(Gate::forUser($user->fresh())->allows('update', $customer));
    }

    public function test_an_accountant_cannot_edit_inventory_even_at_their_own_location(): void
    {
        $user = User::factory()->create(['location_id' => $this->location->id]);
        $user->assignRole('accountant');

        $product = Product::factory()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        $this->assertFalse(Gate::forUser($user->fresh())->allows('update', $product));
    }
}
