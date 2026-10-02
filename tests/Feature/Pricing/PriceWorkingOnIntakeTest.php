<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Inventory\ProductIntake;
use App\Models\Location;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The working, where staff actually see it.
 *
 * "When a customer asks why this costs $6,945, the answer should be visible,
 * auditable and explainable." Visible is this screen; auditable is the copy
 * kept with the price.
 */
class PriceWorkingOnIntakeTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->location = Location::factory()->create();
        $this->staff = User::factory()->create(['location_id' => $this->location->id]);
        $this->staff->assignRole('sales-staff');
    }

    public function test_every_step_of_the_formula_is_shown_on_the_screen(): void
    {
        Livewire::actingAs($this->staff)
            ->test(ProductIntake::class)
            ->set('step', 2)
            ->set('values.sku', 'EST-9001')
            ->set('values.title', 'Art Deco Platinum Diamond Ring')
            ->set('values.category', 'rings')
            ->set('values.metal_type', '950 Platinum')
            ->set('values.weight_grams', '10')
            ->set('values.labor_cost', '180')
            ->call('suggestPrice')
            ->assertHasNoErrors()
            ->assertSee('Step 1 · Determining factors')
            ->assertSee('Step 2 · Basic price')
            ->assertSee('Step 3 · Wholesale price')
            ->assertSee('Step 4 · Retail price')
            ->assertSee('100% − 15%')
            ->assertSee('The working');
    }

    /** Cost fields entered on the form reach the record as cents. */
    public function test_labour_and_material_costs_are_kept_on_the_item(): void
    {
        Livewire::actingAs($this->staff)
            ->test(ProductIntake::class)
            ->set('values.sku', 'EST-9002')
            ->set('values.title', 'Victorian Gold Brooch')
            ->set('values.category', 'brooches')
            ->set('values.labor_cost', '85.50')
            ->set('values.material_cost', '1200')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'EST-9002')->sole();

        $this->assertSame(8550, $product->labor_cost_cents);
        $this->assertSame(120000, $product->material_cost_cents);

        // And they come back to the form in dollars, as a person typed them.
        Livewire::actingAs($this->staff)
            ->test(ProductIntake::class, ['product' => $product])
            ->assertSet('values.labor_cost', '85.50');
    }

    /**
     * A price keeps its own arithmetic.
     *
     * Rates move. Without this, a price set last year could never be
     * explained again, because the table that produced it has changed.
     */
    public function test_the_working_is_filed_with_the_price_it_produced(): void
    {
        Livewire::actingAs($this->staff)
            ->test(ProductIntake::class)
            ->set('values.sku', 'EST-9003')
            ->set('values.title', 'Edwardian Platinum Ring')
            ->set('values.category', 'rings')
            ->set('values.metal_type', '950 Platinum')
            ->set('values.weight_grams', '10')
            ->set('values.labor_cost', '180')
            ->set('values.location_id', (string) $this->location->id)
            ->call('suggestPrice')
            ->call('applySuggestedPrice')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'EST-9003')->sole();
        $pricing = Pricing::where('product_id', $product->id)->latest('id')->sole();

        $this->assertNotNull($pricing->working);
        $this->assertSame($pricing->retail_price_cents, $pricing->working['retail_cents']);

        $labels = array_column($pricing->working['lines'], 'label');
        $this->assertContains('Step 4 · Retail price', $labels);

        // The percentages that produced it are kept too, so a later change to
        // the settings cannot silently rewrite the explanation.
        $this->assertEquals(10, $pricing->working['percentages']['overhead']);
    }
}
