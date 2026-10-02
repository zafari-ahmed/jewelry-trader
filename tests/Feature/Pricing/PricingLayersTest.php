<?php

namespace Tests\Feature\Pricing;

use App\Models\Setting;
use App\Services\Pricing\Contracts\MetalRateProvider;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Layer 2 — a live metal feed, switchable, over the base rate table.
 *
 * The thing being proved here is mostly what happens when it goes wrong: a
 * feed that is off, down, slow or nonsensical must leave the business pricing
 * pieces exactly as it did before.
 */
class PricingLayersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Cache::flush();
    }

    private function rates(): MetalRateProvider
    {
        return app(MetalRateProvider::class);
    }

    public function test_the_base_table_answers_while_the_layer_is_off(): void
    {
        Http::fake();

        $this->assertSame(28.50, $this->rates()->ratePerGram('950 Platinum'));
        $this->assertSame('base rate table', $this->rates()->sourceLabel());

        // Nothing was asked of anybody: the switch is off.
        Http::assertNothingSent();
    }

    public function test_a_live_feed_prices_the_alloy_rather_than_the_fine_metal(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');
        Setting::set('pricing.live_rates_path', 'rates');

        // $3,110.35/oz of fine gold ≈ $100/g; 18k is 75% of that.
        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.34768]])]);

        $provider = $this->rates();

        $this->assertSame(75.0, round((float) $provider->ratePerGram('18K Yellow Gold'), 2));
        $this->assertStringContainsString('live feed', $provider->sourceLabel());
    }

    public function test_a_feed_that_is_down_falls_back_to_the_table(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');

        Http::fake(['feed.test/*' => Http::response('', 503)]);

        $provider = $this->rates();

        $this->assertSame(28.50, $provider->ratePerGram('950 Platinum'));
        $this->assertStringContainsString('base rate table', $provider->sourceLabel());
    }

    public function test_a_feed_that_throws_never_reaches_the_person_cataloguing(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');

        Http::fake(fn () => throw new \RuntimeException('connection refused'));

        $provider = $this->rates();

        $this->assertSame(28.50, $provider->ratePerGram('950 Platinum'));
    }

    public function test_a_metal_the_feed_does_not_quote_falls_back_to_the_table(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');
        Setting::set('pricing.live_rates_path', 'rates');

        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.0]])]);

        $provider = $this->rates();

        $this->assertSame(0.85, $provider->ratePerGram('Sterling Silver'));
        $this->assertStringContainsString('no rate for this metal', $provider->sourceLabel());
    }

    /** The feed is asked once, not once per piece catalogued. */
    public function test_rates_are_cached_rather_than_fetched_per_piece(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');
        Setting::set('pricing.live_rates_path', 'rates');

        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.0]])]);

        $provider = $this->rates();

        foreach (range(1, 5) as $ignored) {
            $provider->ratePerGram('18k gold');
        }

        Http::assertSentCount(1);
    }

    /** A feed quoting grams directly is a setting, not a code change. */
    public function test_a_feed_quoting_per_gram_is_read_as_such(): void
    {
        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');
        Setting::set('pricing.live_rates_path', 'rates');
        Setting::set('pricing.live_rates_quoted_per_ounce', false);

        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 100.0]])]);

        $this->assertSame(75.0, round((float) $this->rates()->ratePerGram('18k gold'), 2));
    }
}
