<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsage;
use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\Contracts\AiTextProvider;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What the reading service costs.
 *
 * The client wants a pilot judged on real spend before committing to a
 * supplier. That requires the spend to be recorded against the work it did —
 * cost per piece catalogued, not an undifferentiated monthly bill.
 */
class AiUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        Setting::set('ai.enabled', true);
        Setting::set('ai.description', true);
        Setting::set('ai.endpoint', 'https://service.test/v1');
        Setting::set('ai.api_key', 'test-key');
        Setting::set('ai.description_model', 'a-model');

        // Supplier price list, in the per-million terms suppliers quote.
        Setting::set('ai.cost_per_million_input', '2.50');
        Setting::set('ai.cost_per_million_output', '10.00');
    }

    private function fakeReply(array $usage = ['prompt_tokens' => 1000, 'completion_tokens' => 500]): void
    {
        Http::fake(['service.test/*' => Http::response([
            'model' => 'a-model',
            'usage' => $usage,
            'choices' => [['message' => ['content' => 'Some copy.']]],
        ])]);
    }

    public function test_a_call_is_costed_from_the_rates_in_settings(): void
    {
        $this->fakeReply();

        app(AiTextProvider::class)->generate('Describe this piece.');

        $row = AiUsage::sole();

        $this->assertSame('description', $row->capability);
        $this->assertSame(1000, $row->input_tokens);
        $this->assertSame(500, $row->output_tokens);

        // 1,000 input at $2.50/M = $0.0025; 500 output at $10/M = $0.005.
        // $0.0075 → 1 cent.
        $this->assertSame(1, $row->cost_cents);
        $this->assertTrue($row->succeeded);
    }

    public function test_a_failed_call_is_recorded_too(): void
    {
        Http::fake(['service.test/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);

        try {
            app(AiTextProvider::class)->generate('Describe this piece.');
        } catch (\Throwable) {
            // The failure is the point; the record of it is what we are testing.
        }

        $this->assertFalse(AiUsage::latest('id')->first()->succeeded);
    }

    public function test_spend_is_reported_per_piece_catalogued(): void
    {
        $product = Product::factory()->create();

        AiUsage::create(['capability' => 'vision', 'cost_cents' => 400, 'product_id' => $product->id, 'created_at' => now()]);
        AiUsage::create(['capability' => 'description', 'cost_cents' => 100, 'product_id' => $product->id, 'created_at' => now()]);

        $summary = AiUsage::summaryFor(now());

        $this->assertSame(500, $summary['total_cents']);
        $this->assertSame(2, $summary['calls']);
        $this->assertSame(1, $summary['pieces']);
        $this->assertSame(500, $summary['cost_per_piece_cents']);
        $this->assertSame(400, $summary['by_capability']['vision']['cost_cents']);
    }

    public function test_a_month_with_no_calls_reports_nothing_rather_than_dividing_by_zero(): void
    {
        $summary = AiUsage::summaryFor(now());

        $this->assertSame(0, $summary['total_cents']);
        $this->assertNull($summary['cost_per_piece_cents']);
    }

    /** Bookkeeping must never be the thing that stops a piece being catalogued. */
    public function test_cataloguing_survives_the_usage_log_failing(): void
    {
        $this->fakeReply();

        \Illuminate\Support\Facades\Schema::drop('ai_usage_log');

        $this->assertSame('Some copy.', app(AiTextProvider::class)->generate('Describe this piece.'));
    }

    public function test_recording_can_be_switched_off(): void
    {
        Setting::set('ai.track_usage', false);
        $this->fakeReply();

        app(AiTextProvider::class)->generate('Describe this piece.');

        $this->assertSame(0, AiUsage::count());
    }
}
