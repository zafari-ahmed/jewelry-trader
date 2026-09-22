<?php

namespace Tests\Feature\Security;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\ProductIntakeService;
use App\Services\Inventory\TransferService;
use App\Services\Orders\OrderService;
use App\Services\Security\InventoryLockService;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 9 acceptance: each lock type blocks exactly its restricted actions —
 * every type tested against every action.
 */
class InventoryLockTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);

        $this->location = Location::factory()->create();
        $this->manager = User::factory()->create(['location_id' => $this->location->id]);
        $this->manager->assignRole('store-manager');
        $this->manager = $this->manager->fresh();
    }

    private function listedProduct(): Product
    {
        $product = Product::factory()->listed()->create();
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 100000]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        return $product;
    }

    /** @return array<string, array{0:string, 1:array<string,bool>}> lock type => action => blocked */
    public static function lockMatrix(): array
    {
        return [
            'full' => ['full', ['sell' => true, 'edit' => true, 'rent' => true, 'display' => true]],
            'sales' => ['sales', ['sell' => true, 'edit' => false, 'rent' => true, 'display' => false]],
            'rental' => ['rental', ['sell' => false, 'edit' => false, 'rent' => true, 'display' => false]],
            'edit' => ['edit', ['sell' => false, 'edit' => true, 'rent' => false, 'display' => false]],
            'view' => ['view', ['sell' => false, 'edit' => false, 'rent' => false, 'display' => true]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lockMatrix')]
    public function test_each_lock_type_blocks_exactly_its_actions(string $lockType, array $expected): void
    {
        $product = $this->listedProduct();

        app(InventoryLockService::class)->lock($product, $lockType, 'Test hold', $this->manager);

        $product = $product->fresh();

        $actual = [
            'sell' => $product->isLockedFor('sell'),
            'edit' => $product->isLockedFor('edit'),
            'rent' => $product->isLockedFor('rent'),
            'display' => $product->isLockedFor('display'),
        ];

        $this->assertSame($expected, $actual, "The {$lockType} lock blocks the wrong actions.");
    }

    public function test_a_sales_lock_stops_a_sale_at_the_service_layer(): void
    {
        $product = $this->listedProduct();
        app(InventoryLockService::class)->lock($product, 'sales', 'Valuation hold', $this->manager);

        $order = app(OrderService::class)->create(
            [['product_id' => $product->id, 'price_cents' => 100000]],
            $this->location->id,
            'pos',
        );

        $this->expectExceptionMessage('is locked: Valuation hold');

        app(OrderService::class)->markPaid($order);
    }

    public function test_a_sales_locked_piece_is_still_editable(): void
    {
        $product = $this->listedProduct();
        app(InventoryLockService::class)->lock($product, 'sales', 'Re-photography', $this->manager);

        // Sales lock: cannot be sold, can still be worked on internally.
        $updated = app(ProductIntakeService::class)->save($product->fresh(), ['title' => 'Re-catalogued'], $this->manager->id);

        $this->assertSame('Re-catalogued', $updated->title);
    }

    public function test_an_edit_lock_stops_an_edit_but_not_a_sale(): void
    {
        $product = $this->listedProduct();
        app(InventoryLockService::class)->lock($product, 'edit', 'Consignment dispute', $this->manager);

        $order = app(OrderService::class)->create(
            [['product_id' => $product->id, 'price_cents' => 100000]],
            $this->location->id,
            'pos',
        );

        // Still sellable…
        $this->assertSame('paid', app(OrderService::class)->markPaid($order)->status);

        // …but frozen for editing.
        $this->expectExceptionMessage('locked for editing');
        app(ProductIntakeService::class)->save($product->fresh(), ['title' => 'Nope'], $this->manager->id);
    }

    public function test_a_view_lock_hides_the_piece_from_the_storefront(): void
    {
        $product = $this->listedProduct();

        $this->assertSame(1, Product::query()->publiclyVisible()->count());

        app(InventoryLockService::class)->lock($product, 'view', 'Legal hold', $this->manager);

        $this->assertSame(0, Product::query()->publiclyVisible()->count());
        // It still exists internally.
        $this->assertSame(1, Product::query()->count());
    }

    public function test_a_locked_piece_cannot_be_transferred(): void
    {
        $product = $this->listedProduct();
        $destination = Location::factory()->create();

        app(InventoryLockService::class)->lock($product, 'full', 'Investigation', $this->manager);

        $this->expectExceptionMessage('is locked: Investigation');

        app(TransferService::class)->request($product->fresh(), $this->location->id, $destination->id, $this->manager);
    }

    public function test_unlocking_restores_the_piece(): void
    {
        $product = $this->listedProduct();
        $lock = app(InventoryLockService::class)->lock($product, 'full', 'Appraisal', $this->manager);

        $this->assertTrue($product->fresh()->isLockedFor('sell'));

        app(InventoryLockService::class)->unlock($lock, $this->manager);

        $this->assertFalse($product->fresh()->isLockedFor('sell'));
        $this->assertSame(1, Product::query()->publiclyVisible()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.unlocked']);
    }

    public function test_a_lock_needs_a_reason(): void
    {
        $this->expectExceptionMessage('needs a reason');

        app(InventoryLockService::class)->lock($this->listedProduct(), 'full', '   ', $this->manager);
    }

    public function test_locking_is_audited_as_a_security_event(): void
    {
        app(InventoryLockService::class)->lock($this->listedProduct(), 'sales', 'Valuation hold', $this->manager);

        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.locked', 'category' => 'security']);
    }
}
