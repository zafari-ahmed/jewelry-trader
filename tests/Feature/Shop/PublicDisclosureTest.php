<?php

namespace Tests\Feature\Shop;

use App\Models\GemstoneDetail;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Pricing;
use App\Models\Product;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a customer is told, on the page itself.
 *
 * The disclosure is built from the stone record each time the page renders,
 * so these tests check the rendered page rather than the service — the
 * service being right is no use if the view forgets to call it.
 */
class PublicDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->location = Location::factory()->create(['state' => 'NY', 'tax_rate' => 0.08875]);
    }

    private function listedProduct(array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes + ['status' => 'listed']);

        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => 250000]);
        InventoryStock::factory()->create([
            'product_id' => $product->id,
            'location_id' => $this->location->id,
            'status' => 'in_stock',
        ]);

        return $product->fresh();
    }

    public function test_a_lab_grown_stone_is_disclosed_on_the_public_page(): void
    {
        $product = $this->listedProduct(['customer_description' => 'An elegant solitaire.']);

        GemstoneDetail::create([
            'product_id' => $product->id,
            'stone_type' => 'Diamond',
            'origin' => 'Lab-Grown',
            'estimated_weight_ct' => 1.5,
        ]);

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertSeeText('lab-grown diamond');
    }

    /**
     * The requirement was "no exceptions". A description that never mentioned
     * it, and a description emptied afterwards, are the two ways copy fails.
     */
    public function test_the_disclosure_does_not_depend_on_the_description(): void
    {
        $product = $this->listedProduct(['customer_description' => null]);

        GemstoneDetail::create([
            'product_id' => $product->id,
            'stone_type' => 'Diamond',
            'origin' => 'Lab-Grown',
        ]);

        $this->get(route('shop.product', $product))->assertOk()->assertSeeText('lab-grown diamond');
    }

    public function test_a_natural_stone_adds_no_disclosure(): void
    {
        $product = $this->listedProduct(['customer_description' => 'A classic solitaire.']);

        GemstoneDetail::create([
            'product_id' => $product->id,
            'stone_type' => 'Diamond',
            'origin' => 'Natural',
        ]);

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertDontSeeText('this piece contains');
    }

    /** The internal cheat sheet must never reach a customer. */
    public function test_the_reference_chart_is_not_on_the_public_page(): void
    {
        $product = $this->listedProduct();

        $this->get(route('shop.product', $product))
            ->assertOk()
            ->assertDontSee('jewelers-reference-chart');
    }
}
