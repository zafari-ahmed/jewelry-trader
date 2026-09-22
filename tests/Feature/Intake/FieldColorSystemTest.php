<?php

namespace Tests\Feature\Intake;

use App\Models\FieldColorRule;
use App\Models\Product;
use App\Services\Inventory\FieldColorResolver;
use Database\Seeders\FieldColorRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldColorSystemTest extends TestCase
{
    use RefreshDatabase;

    private FieldColorResolver $colors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FieldColorRuleSeeder::class);
        $this->colors = app(FieldColorResolver::class);
    }

    private function rule(string $field, ?string $category = null): array
    {
        return $this->colors->rulesFor($category)[$field];
    }

    public function test_required_fields_differ_by_category(): void
    {
        // A ring needs a size; a brooch does not.
        $this->assertSame('red', $this->rule('ring_size', 'rings')['color']);
        $this->assertTrue($this->rule('ring_size', 'rings')['is_required']);

        $this->assertSame('gray', $this->rule('ring_size', 'brooches')['color']);
        $this->assertFalse($this->rule('ring_size', 'brooches')['is_required']);

        $this->assertSame('red', $this->rule('chain_length', 'necklaces')['color']);
    }

    public function test_a_required_field_is_red_when_empty_and_green_when_filled(): void
    {
        $rule = $this->rule('metal_type');

        $this->assertSame('red', $this->colors->colorFor($rule, ''));
        $this->assertSame('red', $this->colors->colorFor($rule, null));
        $this->assertSame('green', $this->colors->colorFor($rule, '950 Platinum'));
    }

    public function test_a_suggested_field_is_yellow_until_a_value_is_entered(): void
    {
        $rule = $this->rule('style_period');

        $this->assertSame('yellow', $this->colors->colorFor($rule, ''));
        $this->assertSame('green', $this->colors->colorFor($rule, 'Edwardian'));
    }

    public function test_an_empty_optional_field_reports_no_status_rather_than_reading_as_complete(): void
    {
        // Green is computed from a value, never configured: an untouched
        // optional field must not look finished.
        $rule = $this->rule('insurance_value');

        $this->assertSame('neutral', $this->colors->colorFor($rule, ''));
        $this->assertSame('green', $this->colors->colorFor($rule, '7400.00'));
    }

    public function test_a_not_applicable_field_stays_gray_whatever_the_value(): void
    {
        $rule = $this->rule('seo_description');

        $this->assertSame('gray', $this->colors->colorFor($rule, ''));
        $this->assertSame('gray', $this->colors->colorFor($rule, 'anything at all'));
    }

    public function test_an_overridden_field_is_blue(): void
    {
        $rule = $this->rule('retail_price');

        // Phase 2 writes this list; Module 5 already reads it, so no screen
        // changes are needed when AI suggestions arrive.
        $this->assertSame('blue', $this->colors->colorFor($rule, '6800.00', ['retail_price']));
    }

    public function test_marking_a_field_overridden_records_it_on_the_product(): void
    {
        $product = Product::factory()->create();

        $this->colors->markOverridden($product, 'retail_price');
        $this->colors->markOverridden($product, 'retail_price');

        $this->assertSame(['retail_price'], $product->fresh()->manually_overridden_fields);
    }

    public function test_completeness_counts_every_colour(): void
    {
        $counts = $this->colors->completeness('rings', [
            'title' => 'Edwardian Diamond Cluster Ring',
            'sku' => 'EST-4412',
            'category' => 'rings',
            'metal_type' => '950 Platinum',
        ], ['retail_price']);

        $this->assertSame(4, $counts['complete']);
        $this->assertGreaterThan(0, $counts['optional']);
        $this->assertGreaterThan(0, $counts['missing']);
        $this->assertSame(1, $counts['overridden']);
        $this->assertGreaterThan(0, $counts['not_applicable']);
        $this->assertGreaterThan(0, $counts['percent']);
    }

    public function test_a_rule_change_in_settings_takes_effect_immediately(): void
    {
        $this->assertTrue($this->colors->rulesFor(null)['brand']['color'] === 'yellow');

        FieldColorRule::query()
            ->where('field_name', 'brand')
            ->whereNull('category')
            ->update(['color' => 'red', 'is_required' => true]);

        FieldColorRule::flushCache();

        // No deploy, no restart: the next read sees the new rule.
        $this->assertSame('red', $this->colors->rulesFor(null)['brand']['color']);
        $this->assertContains('brand', $this->colors->missingRequired(null, []));
    }
}
