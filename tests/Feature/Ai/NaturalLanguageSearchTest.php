<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Catalog;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Search\Contracts\ProductSearchService;
use App\Services\AI\Providers\HttpSearchProvider;
use App\Services\Search\KeywordProductSearch;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class NaturalLanguageSearchTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->location = Location::factory()->create();
    }

    private function listed(string $title, array $attributes, int $priceCents): Product
    {
        $product = Product::factory()->listed()->create(['title' => $title] + $attributes);
        Pricing::factory()->create(['product_id' => $product->id, 'retail_price_cents' => $priceCents]);
        InventoryStock::factory()->create(['product_id' => $product->id, 'location_id' => $this->location->id]);

        return $product;
    }

    private function enableAiSearch(): void
    {
        Setting::set('ai.enabled', true);
        Setting::set('ai.search', true);
        Setting::set('ai.endpoint', 'https://ai.example.test/v1');
        Setting::set('ai.api_key', 'test-key');
        Setting::set('ai.search_model', 'search-model');
    }

    public function test_keyword_search_is_used_until_the_capability_is_switched_on(): void
    {
        $this->assertInstanceOf(KeywordProductSearch::class, app(ProductSearchService::class));

        $this->enableAiSearch();

        $this->assertInstanceOf(HttpSearchProvider::class, app(ProductSearchService::class));
    }

    public function test_a_sentence_is_turned_into_filters(): void
    {
        $this->enableAiSearch();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'terms' => 'sapphire',
                'style_period' => 'Art Deco',
                'max_price' => 8000,
            ])]]],
        ])]);

        $filters = app(HttpSearchProvider::class)->interpret('art deco sapphire under 8000');

        $this->assertSame('Art Deco', $filters['style_period']);
        $this->assertSame(8000, $filters['max_price']);
    }

    public function test_the_sentence_narrows_the_catalogue(): void
    {
        $this->enableAiSearch();

        $wanted = $this->listed('Sapphire Line Bracelet', ['style_period' => 'Art Deco'], 700000);
        $tooDear = $this->listed('Sapphire Deco Necklace', ['style_period' => 'Art Deco'], 1900000);
        $wrongEra = $this->listed('Victorian Sapphire Brooch', ['style_period' => 'Victorian'], 300000);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'terms' => 'sapphire', 'style_period' => 'Art Deco', 'max_price' => 8000,
            ])]]],
        ])]);

        Livewire::test(Catalog::class)
            ->set('q', 'art deco sapphire under 8000')
            ->assertSee('Sapphire Line Bracelet')
            ->assertDontSee('Sapphire Deco Necklace')
            ->assertDontSee('Victorian Sapphire Brooch');
    }

    public function test_the_shopper_is_shown_what_was_understood(): void
    {
        $this->enableAiSearch();

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['terms' => 'ring', 'style_period' => 'Edwardian'])]]],
        ])]);

        Livewire::test(Catalog::class)
            ->set('q', 'edwardian rings')
            ->assertSee('Understood as')
            ->assertSee('Edwardian');
    }

    public function test_a_failing_service_falls_back_to_keyword_search(): void
    {
        $this->enableAiSearch();

        $this->listed('Edwardian Cluster Ring', ['style_period' => 'Edwardian'], 500000);

        Http::fake(['*' => Http::response([], 500)]);

        // A search box must never break because a model is down.
        Livewire::test(Catalog::class)
            ->set('q', 'Edwardian')
            ->assertSee('Edwardian Cluster Ring');
    }

    public function test_it_can_never_surface_a_piece_that_is_not_for_sale(): void
    {
        $this->enableAiSearch();

        $draft = Product::factory()->create(['status' => 'draft', 'title' => 'Hidden Draft Ring']);
        Pricing::factory()->create(['product_id' => $draft->id, 'retail_price_cents' => 100000]);
        InventoryStock::factory()->create(['product_id' => $draft->id, 'location_id' => $this->location->id]);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['terms' => 'hidden draft'])]]],
        ])]);

        // Interpretation only fills filters; the visibility rule still decides.
        $this->assertSame(0, app(HttpSearchProvider::class)->search('hidden draft ring')->count());
    }
}
