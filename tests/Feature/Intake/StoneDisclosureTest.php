<?php

namespace Tests\Feature\Intake;

use App\Livewire\Inventory\ProductIntake;
use App\Models\GemstoneDetail;
use App\Models\Location;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Inventory\ProductIntakeService;
use App\Services\Inventory\StoneDisclosureService;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * What has to be said about a stone, and whether it can be left unsaid.
 *
 * The client's requirement was that the lab-grown label reach the customer
 * "no exceptions". These tests exist to prove the exceptions are closed: the
 * disclosure survives a description that never mentioned it, and survives one
 * that was emptied afterwards.
 */
class StoneDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private StoneDisclosureService $disclosure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);
        $this->seed(SettingsSeeder::class);

        $location = Location::factory()->create();
        $this->staff = User::factory()->create(['location_id' => $location->id]);
        $this->staff->assignRole('inventory-specialist');

        $this->disclosure = app(StoneDisclosureService::class);
    }

    private function productWithStone(array $stone, array $attributes = []): Product
    {
        $product = Product::factory()->create($attributes);

        GemstoneDetail::create(['product_id' => $product->id] + $stone);

        return $product->fresh();
    }

    public function test_a_lab_grown_stone_is_disclosed_from_the_record(): void
    {
        $product = $this->productWithStone([
            'stone_type' => 'Diamond',
            'origin' => 'Lab-Grown',
            'estimated_weight_ct' => 1.5,
        ]);

        $statements = $this->disclosure->statements($product);

        $this->assertCount(1, $statements);
        $this->assertStringContainsStringIgnoringCase('lab-grown', $statements[0]);
    }

    /** The whole point: the copy is not what carries the disclosure. */
    public function test_the_disclosure_survives_a_description_that_never_mentioned_it(): void
    {
        $product = $this->productWithStone(
            ['stone_type' => 'Diamond', 'origin' => 'Lab-Grown'],
            ['customer_description' => 'A beautiful ring with a brilliant centre stone.'],
        );

        $this->assertNotEmpty($this->disclosure->statements($product));

        // And survives the description being emptied afterwards.
        $product->update(['customer_description' => null]);

        $this->assertNotEmpty($this->disclosure->statements($product->fresh()));
    }

    public function test_a_natural_stone_produces_no_disclosure(): void
    {
        $product = $this->productWithStone(['stone_type' => 'Diamond', 'origin' => 'Natural']);

        $this->assertSame([], $this->disclosure->statements($product));
    }

    /** Three lab-grown stones in one ring is one claim, not three. */
    public function test_repeated_stones_collapse_to_one_statement(): void
    {
        $product = Product::factory()->create();

        foreach (range(1, 3) as $ignored) {
            GemstoneDetail::create([
                'product_id' => $product->id,
                'stone_type' => 'Diamond',
                'origin' => 'Lab-Grown',
            ]);
        }

        $this->assertCount(1, $this->disclosure->statements($product->fresh()));
    }

    public function test_an_untreated_ruby_blocks_listing_until_its_treatment_is_recorded(): void
    {
        $product = $this->productWithStone(
            ['stone_type' => 'Ruby', 'estimated_weight_ct' => 2.0],
            ['status' => 'approved'],
        );

        $this->assertNotEmpty($this->disclosure->blockers($product));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/treatment/i');

        app(ProductIntakeService::class)->list($product);
    }

    public function test_recording_the_treatment_releases_the_block(): void
    {
        $product = $this->productWithStone(
            ['stone_type' => 'Ruby', 'treatment' => 'Heated'],
            ['status' => 'approved'],
        );

        $this->assertSame([], $this->disclosure->blockers($product));
        $this->assertSame('listed', app(ProductIntakeService::class)->list($product)->status);
    }

    /** "Unknown" is a disclosure. Silence is not. */
    public function test_unknown_counts_as_a_recorded_treatment(): void
    {
        $product = $this->productWithStone(['stone_type' => 'Emerald', 'treatment' => 'Unknown']);

        $this->assertSame([], $this->disclosure->blockers($product));
    }

    public function test_a_diamond_needs_no_treatment_disclosure(): void
    {
        $product = $this->productWithStone(['stone_type' => 'Natural Diamond']);

        $this->assertSame([], $this->disclosure->blockers($product));
    }

    /** Which stones must be disclosed is a setting, not an enum (rule 3.1). */
    public function test_the_disclosure_list_is_configurable(): void
    {
        $product = $this->productWithStone(['stone_type' => 'Tanzanite']);

        $this->assertSame([], $this->disclosure->blockers($product));

        Setting::set('reference.treatment_disclosure_required', ['tanzanite'], $this->staff->id);

        $this->assertNotEmpty($this->disclosure->blockers($product->fresh()));
    }

    /**
     * The chart, the 4Cs and the estate cuts are on the screen where a stone
     * is entered — which is the whole of the client's placement request.
     */
    public function test_the_stones_section_carries_its_reference_material(): void
    {
        $this->actingAs($this->staff);

        $html = Livewire::test(ProductIntake::class)
            ->set('step', 2)
            ->call('addStone')
            ->set('stones.0.stone_type', 'Ruby')
            ->html();

        foreach ([
            'Stones',
            'Carat is weight, not size',
            'Reference chart',
            'jewelers-reference-chart.jpg',
            'Old European',
            'Rose Cut',
            'Lab-Grown',
            'before this item can be listed',
        ] as $expected) {
            $this->assertStringContainsString($expected, $html, "The stones section is missing: {$expected}");
        }
    }

    public function test_stones_entered_on_the_intake_form_are_saved(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(ProductIntake::class)
            ->set('values.sku', 'EST-9001')
            ->set('values.title', 'Lab-Grown Solitaire')
            ->call('addStone')
            ->set('stones.0.stone_type', 'Lab-Grown Diamond')
            ->set('stones.0.origin', 'Lab-Grown')
            ->set('stones.0.cut', 'Round Brilliant')
            ->set('stones.0.estimated_weight_ct', '1.25')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $stone = Product::where('sku', 'EST-9001')->firstOrFail()->gemstones()->firstOrFail();

        $this->assertSame('Lab-Grown', $stone->origin);
        $this->assertSame('Round Brilliant', $stone->cut);
        $this->assertSame(1.25, (float) $stone->estimated_weight_ct);
        $this->assertTrue($stone->is_primary);
    }

    /** A row somebody opened and never filled is not an empty stone. */
    public function test_a_blank_stone_row_is_discarded(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(ProductIntake::class)
            ->set('values.sku', 'EST-9002')
            ->set('values.title', 'Plain Gold Band')
            ->call('addStone')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertSame(0, Product::where('sku', 'EST-9002')->firstOrFail()->gemstones()->count());
    }

    public function test_removing_a_stone_deletes_it(): void
    {
        $this->actingAs($this->staff);

        $component = Livewire::test(ProductIntake::class)
            ->set('values.sku', 'EST-9003')
            ->set('values.title', 'Three Stone Ring')
            ->call('addStone')
            ->set('stones.0.stone_type', 'Sapphire')
            ->set('stones.0.treatment', 'Heated')
            ->call('addStone')
            ->set('stones.1.stone_type', 'Natural Diamond')
            ->call('saveDraft');

        $product = Product::where('sku', 'EST-9003')->firstOrFail();
        $this->assertSame(2, $product->gemstones()->count());

        $component->call('removeStone', 1)->call('saveDraft')->assertHasNoErrors();

        $this->assertSame(1, $product->fresh()->gemstones()->count());
        $this->assertSame('Sapphire', $product->fresh()->gemstones()->first()->stone_type);
    }
}
