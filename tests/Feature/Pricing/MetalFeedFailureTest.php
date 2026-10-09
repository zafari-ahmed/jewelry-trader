<?php

namespace Tests\Feature\Pricing;

use App\Models\MetalRateFetch;
use App\Models\Setting;
use App\Notifications\MetalFeedFailing;
use App\Services\Pricing\Contracts\MetalRateProvider;
use Database\Seeders\MetalRateProviderSeeder;
use Database\Seeders\OpeningRateTableSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * What the metals feed does when it goes wrong.
 *
 * Over a long enough period this is the common case, so it gets more
 * attention than the happy path. The shop must keep working whichever
 * behaviour the business chose.
 */
class MetalFeedFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(OpeningRateTableSeeder::class);
        $this->seed(MetalRateProviderSeeder::class);
        Cache::flush();

        Setting::set('pricing.layer.live_rates_enabled', true);
        Setting::set('pricing.live_rates_endpoint', 'https://feed.test/rates');
        Setting::set('pricing.live_rates_path', 'rates');
    }

    private function rates(): MetalRateProvider
    {
        Cache::forget('pricing.live_rates');

        return app(MetalRateProvider::class);
    }

    private function failTheFeed(): void
    {
        Http::fake(['feed.test/*' => Http::response('', 503)]);
    }

    public function test_the_default_is_to_fall_back_to_your_own_table(): void
    {
        $this->failTheFeed();

        $provider = $this->rates();

        $this->assertSame(63.90, $provider->ratePerGram('18k gold'));
        $this->assertStringContainsString('live feed unavailable', $provider->sourceLabel());
    }

    public function test_last_known_uses_the_rate_the_feed_did_return(): void
    {
        Setting::set('pricing.live_rates_failure_behaviour', 'last_known');

        // A good fetch yesterday: $100/g of fine gold.
        MetalRateFetch::create([
            'provider_slug' => 'custom', 'succeeded' => true,
            'rates' => ['gold' => 100.0], 'created_at' => now()->subDay(),
        ]);

        $this->failTheFeed();

        $provider = $this->rates();

        // 18k is 75% of fine, so $75/g — not the $63.90 base rate.
        $this->assertSame(75.0, round((float) $provider->ratePerGram('18k gold'), 2));
        $this->assertStringContainsString('last known live rate', $provider->sourceLabel());
    }

    public function test_last_known_falls_back_to_the_table_when_there_is_no_last_known(): void
    {
        Setting::set('pricing.live_rates_failure_behaviour', 'last_known');
        $this->failTheFeed();

        $this->assertSame(63.90, $this->rates()->ratePerGram('18k gold'));
    }

    /**
     * Hold refuses to price rather than quoting something stale.
     *
     * Returning null makes the engine report the rate as missing, which is
     * how every other unpriceable attribute behaves.
     */
    public function test_hold_refuses_to_price_the_metal(): void
    {
        Setting::set('pricing.live_rates_failure_behaviour', 'hold');
        $this->failTheFeed();

        $provider = $this->rates();

        $this->assertNull($provider->ratePerGram('18k gold'));
        $this->assertStringContainsString('held', $provider->sourceLabel());
    }

    /** A metal the feed simply does not quote is not a failure. */
    public function test_an_uncovered_metal_falls_back_quietly_without_alarm(): void
    {
        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.0]])]);
        Setting::set('pricing.live_rates_failure_behaviour', 'hold');

        $provider = $this->rates();

        // Nobody quotes spot for brass; the table answers and nothing is held.
        $this->assertSame(0.01, $provider->ratePerGram('brass'));
        $this->assertStringContainsString('no rate for this metal', $provider->sourceLabel());
    }

    public function test_every_call_is_logged_with_what_went_wrong(): void
    {
        $this->failTheFeed();
        $this->rates()->ratePerGram('18k gold');

        $fetch = MetalRateFetch::sole();

        $this->assertFalse($fetch->succeeded);
        $this->assertStringContainsString('503', $fetch->error);
        $this->assertNotNull($fetch->duration_ms);
    }

    public function test_a_successful_fetch_records_the_rates_it_read(): void
    {
        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.34768]])]);

        $this->rates()->ratePerGram('18k gold');

        $fetch = MetalRateFetch::lastSuccessful();

        $this->assertTrue($fetch->succeeded);
        $this->assertSame(100.0, round($fetch->rates['gold'], 2));
    }

    /** The feed only takes the metals the business asked for. */
    public function test_metals_outside_the_coverage_list_are_ignored(): void
    {
        Setting::set('pricing.live_rates_metals', ['gold']);
        Http::fake(['feed.test/*' => Http::response(['rates' => ['gold' => 3110.0, 'rhodium' => 150000.0]])]);

        $this->rates()->ratePerGram('18k gold');

        $this->assertSame(['gold'], array_keys(MetalRateFetch::lastSuccessful()->rates));
    }

    /**
     * One message per run of failures, not one per failure.
     *
     * A feed down all weekend should produce one email, not nine hundred.
     */
    public function test_the_alert_fires_once_when_the_threshold_is_crossed(): void
    {
        Notification::fake();

        Setting::set('pricing.live_rates_alert_after_failures', 3);
        Setting::set('pricing.live_rates_alert_recipients', 'manager@example.test');
        $this->failTheFeed();

        foreach (range(1, 6) as $ignored) {
            $this->rates()->ratePerGram('18k gold');
        }

        $this->assertSame(6, MetalRateFetch::count());
        Notification::assertSentTimes(MetalFeedFailing::class, 1);
    }

    public function test_no_alert_without_recipients(): void
    {
        Notification::fake();

        Setting::set('pricing.live_rates_alert_after_failures', 1);
        Setting::set('pricing.live_rates_alert_recipients', '');
        $this->failTheFeed();

        $this->rates()->ratePerGram('18k gold');

        Notification::assertNothingSent();
    }

    /** A failure run that has since recovered is not counted. */
    public function test_consecutive_failures_stop_at_the_last_success(): void
    {
        foreach ([false, false, true, false] as $succeeded) {
            MetalRateFetch::create(['succeeded' => $succeeded, 'created_at' => now()]);
        }

        $this->assertSame(1, MetalRateFetch::consecutiveFailures());
    }

    /** A connection test is not a production fetch and never triggers alerts. */
    public function test_a_connection_test_is_kept_out_of_the_failure_count(): void
    {
        MetalRateFetch::create(['succeeded' => false, 'was_test' => true, 'created_at' => now()]);
        MetalRateFetch::create(['succeeded' => false, 'was_test' => true, 'created_at' => now()]);

        $this->assertSame(0, MetalRateFetch::consecutiveFailures());
        $this->assertNull(MetalRateFetch::lastSuccessful());
    }
}
