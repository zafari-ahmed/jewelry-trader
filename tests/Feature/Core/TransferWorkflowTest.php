<?php

namespace Tests\Feature\Core;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\TransferRequest;
use App\Models\User;
use App\Services\Inventory\TransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Location $from;

    private Location $to;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->from = Location::factory()->create(['name' => 'Madison Ave']);
        $this->to = Location::factory()->create(['name' => 'Greenwich']);
        $this->product = Product::factory()->listed()->create();
        InventoryStock::factory()->create(['product_id' => $this->product->id, 'location_id' => $this->from->id]);
    }

    private function manager(Location $location): User
    {
        $user = User::factory()->create(['location_id' => $location->id]);
        $user->assignRole('store-manager');
        $user->revokePermissionTo('view-all-locations');
        $user->syncRoles([]);
        $user->syncPermissions(['manage-inventory-transfers', 'approve-inventory-transfers', 'view-products']);

        return $user->fresh();
    }

    public function test_a_transfer_moves_stock_only_once_it_is_completed(): void
    {
        $service = app(TransferService::class);
        $staff = User::factory()->create(['location_id' => $this->from->id]);

        $transfer = $service->request($this->product, $this->from->id, $this->to->id, $staff);
        $this->assertSame('pending', $transfer->status);
        $this->assertSame($this->from->id, InventoryStock::where('product_id', $this->product->id)->value('location_id'));

        // Approving marks it in transit so it cannot also be sold.
        $service->approve($transfer, $this->manager($this->from));
        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame('transferred', InventoryStock::where('product_id', $this->product->id)->value('status'));

        $service->complete($transfer->fresh());

        $stock = InventoryStock::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame($this->to->id, $stock->location_id);
        $this->assertSame('in_stock', $stock->status);
        $this->assertSame(1, InventoryStock::where('product_id', $this->product->id)->count(), 'stock moves, it is not duplicated');
    }

    public function test_the_sending_locations_manager_approves(): void
    {
        $transfer = app(TransferService::class)->request($this->product, $this->from->id, $this->to->id);

        $sendingManager = $this->manager($this->from);
        $receivingManager = $this->manager($this->to);

        $this->assertTrue(Gate::forUser($sendingManager)->allows('approve', $transfer));
        $this->assertFalse(Gate::forUser($receivingManager)->allows('approve', $transfer));
    }

    public function test_an_item_cannot_be_transferred_twice_at_once(): void
    {
        $service = app(TransferService::class);
        $service->request($this->product, $this->from->id, $this->to->id);

        $this->expectExceptionMessage('already has a transfer in progress');

        $service->request($this->product->fresh(), $this->from->id, $this->to->id);
    }

    public function test_a_sold_item_cannot_be_transferred(): void
    {
        InventoryStock::where('product_id', $this->product->id)->update(['status' => 'sold']);

        $this->expectExceptionMessage('not available at the sending location');

        app(TransferService::class)->request($this->product, $this->from->id, $this->to->id);
    }

    public function test_a_transfer_needs_two_different_locations(): void
    {
        $this->expectExceptionMessage('two different locations');

        app(TransferService::class)->request($this->product, $this->from->id, $this->from->id);
    }

    public function test_a_rejected_transfer_leaves_stock_where_it_was(): void
    {
        $service = app(TransferService::class);
        $transfer = $service->request($this->product, $this->from->id, $this->to->id);

        $service->reject($transfer, $this->manager($this->from));

        $this->assertSame('rejected', $transfer->fresh()->status);
        $this->assertSame($this->from->id, InventoryStock::where('product_id', $this->product->id)->value('location_id'));
        $this->assertSame('in_stock', InventoryStock::where('product_id', $this->product->id)->value('status'));
    }

    public function test_transfers_are_audited(): void
    {
        app(TransferService::class)->request($this->product, $this->from->id, $this->to->id);

        $this->assertDatabaseHas('audit_logs', ['action' => 'transfer_request.created']);
    }
}
