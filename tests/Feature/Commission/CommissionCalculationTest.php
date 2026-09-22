<?php

namespace Tests\Feature\Commission;

use App\Jobs\CalculateCommission;
use App\Models\Commission;
use App\Models\CommissionPlan;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaffCommissionAssignment;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\Orders\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->location = Location::factory()->create(['tax_rate' => 0.08875]);
        $this->seller = tap(User::factory()->create(['location_id' => $this->location->id]))->assignRole('sales-staff')->fresh();
    }

    private function paidOrder(int $priceCents = 100000, int $acquisitionCents = 45000, int $discountCents = 0): Order
    {
        $product = Product::factory()->listed()->create();
        Pricing::factory()->create([
            'product_id' => $product->id,
            'retail_price_cents' => $priceCents,
            'acquisition_value_cents' => $acquisitionCents,
        ]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        $order = app(OrderService::class)->create(
            [['product_id' => $product->id, 'price_cents' => $priceCents, 'discount_cents' => $discountCents]],
            $this->location->id,
            'pos',
            null,
            $this->seller->id,
            0.08875,
            'NY',
        );

        return app(OrderService::class)->markPaid($order);
    }

    private function assignPlan(string $type, array $config, ?User $user = null): CommissionPlan
    {
        $plan = CommissionPlan::create(['name' => ucfirst($type), 'type' => $type, 'config' => $config]);

        StaffCommissionAssignment::create([
            'user_id' => ($user ?? $this->seller)->id,
            'commission_plan_id' => $plan->id,
            'effective_from' => now()->subYear(),
            'terms_text' => 'Signed agreement on file.',
        ]);

        return $plan;
    }

    public function test_commission_is_queued_never_calculated_during_checkout(): void
    {
        Queue::fake();

        $this->paidOrder();

        Queue::assertPushed(CalculateCommission::class);
        $this->assertSame(0, Commission::count());
    }

    public function test_a_sales_based_plan_pays_a_flat_percentage_excluding_tax(): void
    {
        Setting::set('commission.default_type', 'sales_based');
        Setting::set('commission.default_rate_percent', '5');

        $order = $this->paidOrder(100000);
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $commission = Commission::where('user_id', $this->seller->id)->firstOrFail();

        // 5% of $1,000; the 8.875% tax was never the shop's money.
        $this->assertSame(5000, $commission->amount_cents);
        $this->assertSame(100000, $commission->commissionable_cents);
    }

    public function test_a_discount_reduces_the_commissionable_value(): void
    {
        Setting::set('commission.default_type', 'sales_based');
        Setting::set('commission.default_rate_percent', '5');

        $order = $this->paidOrder(100000, discountCents: 20000);
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $this->assertSame(4000, Commission::first()->amount_cents);
    }

    public function test_a_profit_based_plan_pays_on_margin(): void
    {
        $this->assignPlan('profit_based', ['rate' => 20]);

        $order = $this->paidOrder(100000, acquisitionCents: 45000);
        app(CommissionService::class)->calculateFor($order->fresh(['items.product.currentPricing']));

        // 20% of the $550 margin.
        $this->assertSame(11000, Commission::first()->amount_cents);
    }

    public function test_a_tiered_plan_uses_the_highest_threshold_reached(): void
    {
        $this->assignPlan('tiered', ['tiers' => [['threshold' => 0, 'rate' => 4], ['threshold' => 5000, 'rate' => 6]]]);

        $small = $this->paidOrder(100000);
        app(CommissionService::class)->calculateFor($small->fresh('items'));
        $this->assertSame(4000, Commission::where('order_id', $small->id)->firstOrFail()->amount_cents);

        $large = $this->paidOrder(800000);
        app(CommissionService::class)->calculateFor($large->fresh('items'));
        $this->assertSame(48000, Commission::where('order_id', $large->id)->firstOrFail()->amount_cents);
    }

    public function test_a_split_pays_each_person_their_share_and_sums_to_the_pool(): void
    {
        $second = User::factory()->create(['location_id' => $this->location->id]);

        $this->assignPlan('split', ['rate' => 5, 'shares' => [$this->seller->id => 60, $second->id => 40]]);

        $order = $this->paidOrder(100000);
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $this->assertSame(3000, Commission::where('user_id', $this->seller->id)->firstOrFail()->amount_cents);
        $this->assertSame(2000, Commission::where('user_id', $second->id)->firstOrFail()->amount_cents);
        $this->assertSame(5000, (int) Commission::sum('amount_cents'));
    }

    public function test_the_logged_in_salesperson_earns_the_commission(): void
    {
        $order = $this->paidOrder();
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $this->assertSame($this->seller->id, Commission::firstOrFail()->user_id);
    }

    public function test_a_web_sale_attributes_nothing(): void
    {
        $product = Product::factory()->listed()->create();
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 100000]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        $order = app(OrderService::class)->create(
            [['product_id' => $product->id, 'price_cents' => 100000]],
            $this->location->id,
            'web',
        );
        app(OrderService::class)->markPaid($order);

        $this->assertSame([], app(CommissionService::class)->calculateFor($order->fresh('items')));
        $this->assertSame(0, Commission::count());
    }

    public function test_a_refund_never_deducts_from_an_earned_commission(): void
    {
        Setting::set('commission.default_type', 'sales_based');
        Setting::set('commission.default_rate_percent', '5');

        $order = $this->paidOrder(100000);
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $before = Commission::firstOrFail()->amount_cents;

        app(OrderService::class)->restock($order->fresh(), 'refunded');
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        // §221: no automatic clawback — a reduction goes through an override.
        $this->assertSame($before, Commission::firstOrFail()->amount_cents);
        $this->assertSame(1, Commission::count());
    }

    public function test_the_settings_default_applies_without_an_assignment(): void
    {
        Setting::set('commission.default_type', 'sales_based');
        Setting::set('commission.default_rate_percent', '4.5');

        $order = $this->paidOrder(100000);
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $this->assertSame(4500, Commission::firstOrFail()->amount_cents);
        $this->assertNull(Commission::firstOrFail()->commission_plan_id);
    }

    public function test_an_assignment_records_the_written_agreement(): void
    {
        $plan = CommissionPlan::create(['name' => 'Standard', 'type' => 'sales_based', 'config' => ['rate' => 5]]);

        $withTerms = StaffCommissionAssignment::create([
            'user_id' => $this->seller->id,
            'commission_plan_id' => $plan->id,
            'effective_from' => now(),
            'terms_text' => 'Five percent of net sales, paid monthly.',
        ]);

        $withoutTerms = StaffCommissionAssignment::create([
            'user_id' => User::factory()->create()->id,
            'commission_plan_id' => $plan->id,
            'effective_from' => now(),
        ]);

        // §2751: the numeric plan is not the agreement.
        $this->assertTrue($withTerms->hasWrittenTerms());
        $this->assertFalse($withoutTerms->hasWrittenTerms());
    }

    public function test_recalculating_does_not_duplicate_a_commission(): void
    {
        $order = $this->paidOrder();

        app(CommissionService::class)->calculateFor($order->fresh('items'));
        app(CommissionService::class)->calculateFor($order->fresh('items'));

        $this->assertSame(1, Commission::count());
    }
}
