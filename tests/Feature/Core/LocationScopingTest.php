<?php

namespace Tests\Feature\Core;

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
 * CLAUDE.md Module 4 acceptance: a Sales Staff user at Location A cannot see or
 * edit Location B's inventory without an explicit cross-location permission.
 *
 * Per docs/DECISIONS.md the business wants staff to see other locations, so
 * sales-staff hold view-all-locations by default. These tests prove the
 * scoping is real by testing a user without it — otherwise the permission
 * would be decorative.
 */
class LocationScopingTest extends TestCase
{
    use RefreshDatabase;

    private Location $madison;

    private Location $greenwich;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->madison = Location::factory()->create(['name' => 'Madison Ave']);
        $this->greenwich = Location::factory()->create(['name' => 'Greenwich']);
    }

    private function staffAt(Location $location, bool $crossLocation): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->assignRole('sales-staff');

        if (! $crossLocation) {
            $user->revokePermissionTo('view-all-locations');
            // Role-granted permissions survive a direct revoke, so drop the
            // role's grant for this user by giving them the narrower set.
            $user->syncRoles([]);
            $user->syncPermissions(['view-products', 'manage-products', 'view-orders', 'manage-orders']);
        }

        return $user->fresh();
    }

    private function productAt(Location $location): Product
    {
        $product = Product::factory()->listed()->create();
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $location->id]);

        return $product;
    }

    public function test_a_scoped_user_sees_only_their_own_locations_inventory(): void
    {
        $mine = $this->productAt($this->madison);
        $theirs = $this->productAt($this->greenwich);

        $user = $this->staffAt($this->madison, crossLocation: false);

        $visible = Product::query()->visibleTo($user)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id));
    }

    public function test_a_scoped_user_cannot_view_or_edit_another_locations_item(): void
    {
        $theirs = $this->productAt($this->greenwich);
        $user = $this->staffAt($this->madison, crossLocation: false);

        $this->assertFalse(Gate::forUser($user)->allows('view', $theirs));
        $this->assertFalse(Gate::forUser($user)->allows('update', $theirs));
    }

    public function test_a_scoped_user_can_view_and_edit_their_own_locations_item(): void
    {
        $mine = $this->productAt($this->madison);
        $user = $this->staffAt($this->madison, crossLocation: false);

        $this->assertTrue(Gate::forUser($user)->allows('view', $mine));
        $this->assertTrue(Gate::forUser($user)->allows('update', $mine));
    }

    public function test_the_cross_location_permission_lifts_the_scope(): void
    {
        $theirs = $this->productAt($this->greenwich);
        $user = $this->staffAt($this->madison, crossLocation: true);

        $this->assertTrue(Gate::forUser($user)->allows('view', $theirs));
        $this->assertTrue(Product::query()->visibleTo($user)->pluck('id')->contains($theirs->id));
    }

    public function test_orders_are_scoped_the_same_way(): void
    {
        $mine = Order::create(['order_number' => Order::nextOrderNumber(), 'location_id' => $this->madison->id, 'channel' => 'pos']);
        $theirs = Order::create(['order_number' => Order::nextOrderNumber(), 'location_id' => $this->greenwich->id, 'channel' => 'pos']);

        $user = $this->staffAt($this->madison, crossLocation: false);

        $visible = Order::query()->visibleTo($user)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id));
        $this->assertFalse(Gate::forUser($user)->allows('view', $theirs));
    }

    public function test_a_user_with_no_location_and_no_cross_location_permission_sees_nothing(): void
    {
        $this->productAt($this->madison);

        $user = User::factory()->create(['location_id' => null]);
        $user->syncPermissions(['view-products']);

        $this->assertSame(0, Product::query()->visibleTo($user->fresh())->count());
    }
}
