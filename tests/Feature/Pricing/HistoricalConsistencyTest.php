<?php

namespace Tests\Feature\Pricing;

use App\Models\Product;
use App\Models\QualityControlCheck;
use App\Models\Setting;
use App\Services\Pricing\HistoricalConsistency;
use App\Services\Pricing\PricingEngine;
use App\Services\Quality\QualityControlService;
use App\Services\Quality\QualityStatus;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Could this piece have existed?
 *
 * The thing worth testing hardest is the silence: a false flag against a
 * genuine piece costs the business more than a missed check, so anything the
 * tables do not cover must produce no opinion at all.
 */
class HistoricalConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalConsistency $history;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);

        $this->history = app(HistoricalConsistency::class);
    }

    public function test_a_maker_founded_after_the_period_ended_is_flagged(): void
    {
        $conflict = $this->history->check('Van Cleef & Arpels', 'Georgian');

        $this->assertNotNull($conflict);
        $this->assertStringContainsString('founded in 1906', $conflict['problem']);
        $this->assertStringContainsString('ended in 1837', $conflict['problem']);
    }

    public function test_a_maker_that_closed_before_the_period_began_is_flagged(): void
    {
        Setting::set('pricing.maker_years', ['gone & co' => ['founded' => 1820, 'dissolved' => 1900]]);

        $conflict = $this->history->check('Gone & Co', 'Mid-Century');

        $this->assertNotNull($conflict);
        $this->assertStringContainsString('ceased trading in 1900', $conflict['problem']);
    }

    public function test_a_plausible_combination_passes(): void
    {
        $this->assertNull($this->history->check('Cartier', 'Art Deco'));
        $this->assertNull($this->history->check('Tiffany & Co.', 'Late Victorian'));
    }

    /** A maker founded during the period is fine, not marginal. */
    public function test_a_maker_founded_inside_the_period_passes(): void
    {
        // Van Cleef founded 1906; Edwardian runs 1901–1915.
        $this->assertNull($this->history->check('Van Cleef & Arpels', 'Edwardian'));
    }

    /**
     * Silence where the tables cannot speak.
     *
     * A false accusation against a genuine piece costs more than a missed
     * check, so an unknown maker or period produces no opinion.
     */
    public function test_an_unknown_maker_or_period_is_never_flagged(): void
    {
        $this->assertNull($this->history->check('A Workshop Nobody Catalogued', 'Georgian'));
        $this->assertNull($this->history->check('Cartier', 'Some Period We Invented'));
        $this->assertNull($this->history->check(null, 'Georgian'));
        $this->assertNull($this->history->check('Cartier', null));
    }

    /** "Unsigned" is not a claim about a workshop, so it cannot conflict. */
    public function test_pieces_that_name_no_maker_are_never_flagged(): void
    {
        foreach (['Unsigned', 'Unsigned (attributed)', 'Retailer mark only', 'Unmarked'] as $maker) {
            $this->assertNull($this->history->check($maker, 'Georgian'), "{$maker} should not flag");
        }
    }

    /** The dates are a setting, so the appraiser can correct them. */
    public function test_the_dates_are_configuration(): void
    {
        $this->assertNotNull($this->history->check('Graff', 'Victorian'));

        Setting::set('pricing.maker_years', ['graff' => ['founded' => 1860, 'dissolved' => null]]);

        $this->assertNull($this->history->check('Graff', 'Victorian'));
    }

    // ---- how it reaches the people who need it -------------------------

    public function test_the_price_carries_the_warning_but_still_produces_a_figure(): void
    {
        $suggestion = app(PricingEngine::class)->suggest([
            'metal_type' => '18k', 'weight_grams' => 12, 'labor_cost_cents' => 15000,
            'brand' => 'Van Cleef & Arpels', 'style_period' => 'Georgian',
        ], []);

        // Flagged, not blocked: the number is still there for a person to judge.
        $this->assertGreaterThan(0, $suggestion->retailCents);
        $this->assertTrue($suggestion->needsReview());
        $this->assertStringContainsString('founded in 1906', implode(' ', $suggestion->warnings));
    }

    public function test_the_reviewer_sees_it_as_a_failed_critical_check(): void
    {
        $product = Product::factory()->create([
            'brand' => 'Van Cleef & Arpels',
            'style_period' => 'Georgian',
        ]);

        app(QualityControlService::class)->evaluate($product);

        $check = QualityControlCheck::where('product_id', $product->id)->where('check_key', '3.9')->sole();

        $this->assertSame(QualityStatus::FAILED, $check->status);
        $this->assertSame('critical', $check->check_type);
        $this->assertTrue(app(QualityControlService::class)->score($product)['blocked']);
    }

    public function test_a_consistent_piece_passes_the_reviewer_check(): void
    {
        $product = Product::factory()->create(['brand' => 'Cartier', 'style_period' => 'Art Deco']);

        app(QualityControlService::class)->evaluate($product);

        $this->assertSame(
            QualityStatus::PASSED,
            QualityControlCheck::where('product_id', $product->id)->where('check_key', '3.9')->value('status'),
        );
    }
}
