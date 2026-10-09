<?php

namespace Tests\Feature\Quality;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\QualityControlCheck;
use App\Models\Setting;
use App\Models\User;
use App\Services\Quality\QualityCheckRegistry;
use App\Services\Quality\QualityControlService;
use App\Services\Quality\QualityStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The quality gate.
 *
 * The design that matters: the system derives everything it can see, and only
 * asks a person about what it cannot. A gauge built on fifty manual ticks
 * gets ticked without being read, and then it looks authoritative while
 * meaning nothing.
 */
class QualityControlTest extends TestCase
{
    use RefreshDatabase;

    private QualityControlService $qc;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->qc = app(QualityControlService::class);
        $this->location = Location::factory()->create();
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create($attributes);
    }

    private function photograph(Product $product, int $count): void
    {
        foreach (range(1, $count) as $i) {
            ProductImage::create([
                'product_id' => $product->id,
                'type' => 'front',
                'file_path' => "products/{$product->id}/{$i}.jpg",
                'is_primary' => $i === 1,
                'sort_order' => $i,
                'uploaded_at' => now(),
            ]);
        }
    }

    private function statusOf(Product $product, string $key): ?string
    {
        return QualityControlCheck::where('product_id', $product->id)
            ->where('check_key', $key)
            ->value('status');
    }

    /** Most checks answer themselves from the record. */
    public function test_most_checks_are_derived_rather_than_asked(): void
    {
        [$automatic, $manual] = collect(app(QualityCheckRegistry::class)->all())
            ->partition(fn ($check) => ! $check->needsAPerson());

        $this->assertGreaterThan(35, $automatic->count());
        $this->assertLessThan(16, $manual->count());
    }

    public function test_a_bare_record_derives_what_it_can_and_waits_for_the_rest(): void
    {
        $product = $this->product(['sku' => 'EST-1000', 'title' => 'A ring']);

        $this->qc->evaluate($product);

        $this->assertSame(QualityStatus::PASSED, $this->statusOf($product, '1.5'));
        $this->assertSame(QualityStatus::PENDING, $this->statusOf($product, '2.1'));
    }

    public function test_photographs_pass_once_the_minimum_is_met(): void
    {
        $product = $this->product();

        $this->photograph($product, 5);

        $this->qc->evaluate($product->fresh());

        $this->assertSame(QualityStatus::PASSED, $this->statusOf($product, '2.1'));
        $this->assertSame(QualityStatus::PASSED, $this->statusOf($product, '2.2'));
    }

    /** The minimum is a setting, not a number buried in code. */
    public function test_the_photograph_minimum_is_configurable(): void
    {
        Setting::set('qc.minimum_photos', 2);

        $product = $this->product();
        $this->photograph($product, 2);

        $this->qc->evaluate($product->fresh());

        $this->assertSame(QualityStatus::PASSED, $this->statusOf($product, '2.1'));
    }

    /**
     * "No stones" and "not looked at yet" are different answers.
     *
     * A plain gold band must not sit amber forever waiting for gemstones it
     * does not have.
     */
    public function test_a_piece_with_no_stones_is_marked_not_applicable_rather_than_pending(): void
    {
        $waiting = $this->product();
        $this->qc->evaluate($waiting);
        $this->assertSame(QualityStatus::PENDING, $this->statusOf($waiting, '2.5'));

        $plainBand = $this->product(['attributes' => ['has_gemstones' => false]]);
        $this->qc->evaluate($plainBand);
        $this->assertSame(QualityStatus::NOT_APPLICABLE, $this->statusOf($plainBand, '2.5'));
    }

    /** Transfer checks do not apply to a piece that has never moved. */
    public function test_transfer_checks_are_not_applicable_without_a_transfer(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        foreach (['6.1', '6.2', '6.3', '6.4'] as $key) {
            $this->assertSame(QualityStatus::NOT_APPLICABLE, $this->statusOf($product, $key));
        }
    }

    /** Nor do sale checks, until it sells. */
    public function test_sale_checks_are_not_applicable_until_the_piece_sells(): void
    {
        $product = $this->product(['status' => 'listed']);
        $this->qc->evaluate($product);

        $this->assertSame(QualityStatus::NOT_APPLICABLE, $this->statusOf($product, '8.1'));
        $this->assertSame(QualityStatus::NOT_APPLICABLE, $this->statusOf($product, '9.4'));
    }

    /** Two records for one piece is how stock goes missing on paper. */
    public function test_a_duplicate_record_fails_its_check(): void
    {
        $first = $this->product(['title' => 'Art Deco Platinum Ring', 'brand' => 'Cartier', 'metal_type' => '950 Platinum']);
        $second = $this->product(['title' => 'Art Deco Platinum Ring', 'brand' => 'Cartier', 'metal_type' => '950 Platinum']);

        $this->qc->evaluate($second);

        $this->assertSame(QualityStatus::FAILED, $this->statusOf($second, '3.7'));
        $this->assertStringContainsString(
            $first->sku,
            QualityControlCheck::where('product_id', $second->id)->where('check_key', '3.7')->value('detail'),
        );
    }

    /** The score is unweighted: passed over applicable. */
    public function test_the_score_counts_rather_than_weights(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        $ids = QualityControlCheck::where('product_id', $product->id)->pluck('id');

        QualityControlCheck::whereIn('id', $ids)->update(['status' => QualityStatus::NOT_APPLICABLE]);
        QualityControlCheck::whereIn('id', $ids->take(4))->update(['status' => QualityStatus::PASSED]);
        QualityControlCheck::whereIn('id', $ids->slice(4, 1))->update(['status' => QualityStatus::PENDING]);

        $score = $this->qc->score($product);

        $this->assertSame(5, $score['applicable']);
        $this->assertSame(4, $score['passed']);
        $this->assertSame(80, $score['score']);
    }

    /** A failed critical check blocks, whatever else passed. */
    public function test_a_critical_check_blocks_advancement(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        QualityControlCheck::where('product_id', $product->id)->update(['status' => QualityStatus::PASSED]);
        $this->assertFalse($this->qc->score($product)['blocked']);

        $critical = QualityControlCheck::where('product_id', $product->id)->where('check_type', 'critical')->first();
        $critical->update(['status' => QualityStatus::FAILED]);

        $score = $this->qc->score($product);

        $this->assertTrue($score['blocked']);
        $this->assertCount(1, $score['blockers']);
    }

    /** An optional check that fails does not block anything. */
    public function test_an_optional_check_never_blocks(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        QualityControlCheck::where('product_id', $product->id)->update(['status' => QualityStatus::PASSED]);
        QualityControlCheck::where('product_id', $product->id)
            ->where('check_type', 'optional')
            ->update(['status' => QualityStatus::FAILED]);

        $this->assertFalse($this->qc->score($product)['blocked']);
    }

    /** A person's answer is not undone by the next evaluation. */
    public function test_re_evaluating_does_not_overwrite_a_human_answer(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('inventory-specialist');

        $product = $this->product();
        $this->qc->evaluate($product);

        $this->qc->record($product, '3.3', QualityStatus::PASSED, $reviewer, 'Read under 10x loupe');
        $this->qc->evaluate($product->fresh());

        $row = QualityControlCheck::where('product_id', $product->id)->where('check_key', '3.3')->first();

        $this->assertSame(QualityStatus::PASSED, $row->status);
        $this->assertSame($reviewer->id, $row->verified_by);
        $this->assertSame('Read under 10x loupe', $row->notes);
    }

    /** Overruling what the system derived is an override, and shows blue. */
    public function test_contradicting_a_derived_check_is_recorded_as_an_override(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('store-manager');

        $product = $this->product(['sku' => 'EST-2000']);
        $this->qc->evaluate($product);

        $this->assertSame(QualityStatus::PASSED, $this->statusOf($product, '1.5'));

        $row = $this->qc->record($product, '1.5', QualityStatus::FAILED, $manager, 'Clashes with an archived piece');

        $this->assertTrue($row->is_override);
        $this->assertSame('blue', $row->colour());

        $this->qc->evaluate($product->fresh());
        $this->assertSame(QualityStatus::FAILED, $this->statusOf($product, '1.5'));
    }

    public function test_the_gauge_gives_one_colour_per_stage(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        $gauge = $this->qc->gauge($product);

        $this->assertSame(array_keys(QualityCheckRegistry::STAGES), array_keys($gauge));
        $this->assertSame('Cataloguing', $gauge['cataloguing']['label']);
        $this->assertSame('gray', $gauge['in_transit']['colour']);
    }

    public function test_a_stage_turns_green_only_when_everything_applicable_passed(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        QualityControlCheck::where('product_id', $product->id)
            ->where('stage', 'inception')
            ->update(['status' => QualityStatus::PASSED]);

        $this->assertSame('green', $this->qc->gauge($product)['inception']['colour']);

        QualityControlCheck::where('product_id', $product->id)
            ->where('stage', 'inception')->first()
            ->update(['status' => QualityStatus::PENDING]);

        $this->assertSame('yellow', $this->qc->gauge($product)['inception']['colour']);
    }

    /** A Super Admin decides what blocks a sale, not this codebase. */
    public function test_a_check_can_be_re_ranked_or_switched_off_from_settings(): void
    {
        Setting::set('qc.check_types', ['2.4' => 'critical', '5.5' => 'disabled']);

        $registry = app(QualityCheckRegistry::class);

        $this->assertSame('critical', $registry->find('2.4')->type);
        $this->assertNull($registry->find('5.5'));

        $product = $this->product();
        $this->qc->evaluate($product);

        $this->assertNull($this->statusOf($product, '5.5'));
    }

    /** A check dropped from the registry does not haunt an old gauge. */
    public function test_a_retired_check_is_cleaned_up(): void
    {
        $product = $this->product();
        $this->qc->evaluate($product);

        QualityControlCheck::create([
            'product_id' => $product->id,
            'stage' => 'cataloguing',
            'check_key' => '99.9',
            'check_type' => 'standard',
            'status' => QualityStatus::PENDING,
        ]);

        $this->qc->evaluate($product->fresh());

        $this->assertNull($this->statusOf($product, '99.9'));
    }
}
