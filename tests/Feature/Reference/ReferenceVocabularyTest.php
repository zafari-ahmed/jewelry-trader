<?php

namespace Tests\Feature\Reference;

use App\Models\GemstoneDetail;
use App\Models\Location;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The stone vocabulary, and the chart it came from.
 *
 * The client's reference chart is a modern retail chart. The cuts that carry
 * the highest rates in the appraiser's own table — Old European and rose —
 * are not on it. A dropdown built from the chart alone would quietly push
 * antique stones into cheaper modern equivalents, so the list is pinned here
 * against the rate table rather than against the chart.
 */
class ReferenceVocabularyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);
    }

    /** @return string[] */
    private function cuts(): array
    {
        return array_map('strtolower', Setting::get('reference.diamond_cuts', []));
    }

    public function test_the_cut_list_carries_the_estate_cuts_the_chart_omits(): void
    {
        foreach (['old european', 'old mine', 'rose cut'] as $cut) {
            $this->assertContains($cut, $this->cuts(), "The cut list has lost {$cut}, which the rate table prices above every modern cut.");
        }
    }

    /**
     * A cut only earns its rate if it is spelled the way the rate table is
     * keyed. A dropdown that offers "Old Euro" prices as a plain round.
     */
    public function test_every_rate_table_cut_can_be_selected_on_the_form(): void
    {
        $offered = $this->cuts();

        foreach (array_keys(Setting::get('pricing.diamond_cut_rates', [])) as $priced) {
            $priced = strtolower((string) $priced);

            $matches = array_filter($offered, fn (string $option) => str_contains($option, $priced));

            $this->assertNotEmpty(
                $matches,
                "The rate table prices a {$priced} but no option on the form produces that spelling.",
            );
        }
    }

    public function test_origin_and_treatment_are_separate_vocabularies(): void
    {
        $treatments = array_map('strtolower', Setting::get('reference.stone_treatments', []));

        // Lab-grown in the treatment list is what caused a grown stone to
        // lose its heat treatment, or the reverse.
        foreach (['lab-grown', 'lab grown', 'synthetic'] as $origin) {
            $this->assertNotContains($origin, $treatments, 'Origin is back in the treatment list.');
        }

        $this->assertContains('lab-grown', array_map('strtolower', Setting::get('reference.stone_origins', [])));
    }

    /** A grown ruby that was also heated must be able to say both. */
    public function test_a_stone_can_carry_an_origin_and_a_treatment_at_once(): void
    {
        $product = Product::factory()->create();

        $stone = GemstoneDetail::create([
            'product_id' => $product->id,
            'stone_type' => 'Ruby',
            'origin' => 'Lab-Grown',
            'treatment' => 'Heated',
        ]);

        $this->assertSame('Lab-Grown', $stone->origin);
        $this->assertSame('Heated', $stone->treatment);
        $this->assertTrue($stone->isLabGrown());
    }

    /** The chart is an internal tool. It has no business on a public page. */
    public function test_the_chart_is_not_referenced_by_any_storefront_view(): void
    {
        $views = [];

        foreach (['shop', 'storefront'] as $surface) {
            $path = resource_path("views/livewire/{$surface}");

            if (! is_dir($path)) {
                continue;
            }

            $views = array_merge($views, glob("{$path}/*.blade.php") ?: []);
        }

        $views = array_merge($views, glob(resource_path('views/partials/storefront-*.blade.php')) ?: []);
        $views[] = resource_path('views/layouts/storefront.blade.php');

        foreach (array_filter($views, 'is_file') as $view) {
            $this->assertStringNotContainsString(
                'reference-chart',
                file_get_contents($view),
                basename($view).' shows the internal reference chart to customers.',
            );
        }
    }

    public function test_an_appraiser_can_reach_the_reference_library(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['location_id' => Location::factory()->create()->id]);
        $user->assignRole('inventory-specialist');

        $this->actingAs($user)->get(route('admin.resources'))->assertOk()->assertSee('reference chart', false);
    }
}
