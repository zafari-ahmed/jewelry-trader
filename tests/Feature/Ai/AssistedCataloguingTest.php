<?php

namespace Tests\Feature\Ai;

use App\Livewire\Inventory\ProductIntake;
use App\Models\AiAnalysisRecord;
use App\Models\AiCorrection;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AiCatalogueService;
use App\Services\Inventory\FieldColorResolver;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The assisted cataloguing workflow: photographs in, suggestions out, a person
 * deciding. The AI service is faked at the HTTP boundary, so the whole path is
 * exercised without a key or a network.
 */
class AssistedCataloguingTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);

        $this->location = Location::factory()->create();
        $this->staff = tap(User::factory()->create(['location_id' => $this->location->id]))
            ->assignRole('sales-staff')->fresh();

        // Configure the assistant the way a Super Admin would.
        Setting::set('ai.enabled', true);
        Setting::set('ai.vision', true);
        Setting::set('ai.description', true);
        Setting::set('ai.endpoint', 'https://ai.example.test/v1');
        Setting::set('ai.api_key', 'test-key');
        Setting::set('ai.vision_model', 'vision-model');
        Setting::set('ai.description_model', 'text-model');

        Storage::fake('public');
    }

    private function productWithPhoto(): Product
    {
        $product = Product::factory()->create(['status' => 'draft', 'category' => null, 'title' => '']);

        Storage::disk('public')->put("products/{$product->id}/front.jpg", 'not-really-an-image');

        ProductImage::create([
            'product_id' => $product->id,
            'type' => 'front',
            'file_path' => "products/{$product->id}/front.jpg",
            'is_primary' => true,
        ]);

        return $product->fresh('images');
    }

    private function fakeAnalysis(array $payload, int $confidence = 88): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'vision-model',
            'choices' => [['message' => ['content' => json_encode($payload + [
                'confidence' => $confidence,
                'reasoning' => 'Rose-cut surround and millegrain edges visible.',
            ])]]],
        ])]);
    }

    public function test_photographs_produce_suggested_field_values(): void
    {
        $product = $this->productWithPhoto();

        $this->fakeAnalysis([
            'title' => 'Edwardian Diamond Cluster Ring',
            'category' => 'rings',
            'style_period' => 'Edwardian',
            'metal_type' => '950 Platinum',
            'measurements' => '17.2mm × 14.8mm',
            'gemstones' => [['stone_type' => 'diamond', 'estimated_weight_ct' => 1.42]],
        ]);

        $analysis = app(AiCatalogueService::class)->analysePhotos($product);

        $this->assertSame('Edwardian Diamond Cluster Ring', $analysis['values']['title']);
        $this->assertSame('rings', $analysis['values']['category']);
        $this->assertSame(88, $analysis['result']->confidenceScore);

        // The analysis is kept, with its confidence and reasoning.
        $record = AiAnalysisRecord::where('product_id', $product->id)->firstOrFail();
        $this->assertSame(88, $record->confidence_score);
        $this->assertNotEmpty($record->reasoning_summary);
    }

    public function test_a_low_confidence_reading_suggests_nothing(): void
    {
        $product = $this->productWithPhoto();

        Setting::set('ai.min_confidence', 60);
        $this->fakeAnalysis(['title' => 'Possibly a ring'], confidence: 25);

        $analysis = app(AiCatalogueService::class)->analysePhotos($product);

        // Better to say nothing than to send someone correcting guesswork.
        $this->assertSame([], $analysis['values']);
        $this->assertSame(1, AiAnalysisRecord::count(), 'the reading is still recorded');
    }

    public function test_a_suggested_field_renders_yellow_until_it_is_accepted(): void
    {
        $product = $this->productWithPhoto();
        $colors = app(FieldColorResolver::class);
        $rule = $colors->rulesFor('rings')['title'];

        app(AiCatalogueService::class)->markSuggested($product, ['title']);
        $product->refresh();

        // Filled in, but not yet anybody's answer.
        $this->assertSame('yellow', $colors->colorFor($rule, 'Edwardian Ring', [], $product->ai_suggested_fields));

        app(AiCatalogueService::class)->acceptSuggestion($product, 'title');
        $product->refresh();

        $this->assertSame('green', $colors->colorFor($rule, 'Edwardian Ring', [], $product->ai_suggested_fields));
    }

    public function test_replacing_a_suggestion_turns_the_field_blue_and_is_logged(): void
    {
        $product = $this->productWithPhoto();
        $catalogue = app(AiCatalogueService::class);

        $catalogue->markSuggested($product, ['style_period']);
        $catalogue->recordCorrection($product->fresh(), 'style_period', 'Victorian', 'Edwardian', $this->staff->id);

        $product->refresh();

        $this->assertContains('style_period', $product->manually_overridden_fields);
        $this->assertNotContains('style_period', $product->ai_suggested_fields);

        $correction = AiCorrection::where('product_id', $product->id)->firstOrFail();
        $this->assertSame('Victorian', $correction->original_value);
        $this->assertSame('Edwardian', $correction->final_value);
        $this->assertSame($this->staff->id, $correction->corrected_by);

        $rule = app(FieldColorResolver::class)->rulesFor('rings')['style_period'];
        $this->assertSame('blue', app(FieldColorResolver::class)
            ->colorFor($rule, 'Edwardian', $product->manually_overridden_fields, $product->ai_suggested_fields));
    }

    public function test_descriptions_are_drafted_for_each_audience(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'text-model',
            'choices' => [['message' => ['content' => json_encode([
                'customer_description' => 'A finely pierced platinum mount…',
                'seo_description' => 'Edwardian platinum diamond cluster ring, c. 1905.',
                'marketplace_description' => 'Platinum, 1.42 ctw, size 6¼.',
                'social_description' => 'Edwardian, and still crisp after 120 years.',
                'internal_description' => 'Check shank for import mark before listing.',
            ])]]],
        ])]);

        $descriptions = app(AiCatalogueService::class)->describe([
            'title' => 'Edwardian Diamond Cluster Ring',
            'metal_type' => '950 Platinum',
        ]);

        $this->assertCount(5, $descriptions);
        $this->assertArrayHasKey('customer_description', $descriptions);
        $this->assertArrayHasKey('seo_description', $descriptions);
    }

    public function test_the_whole_intake_runs_from_the_screen(): void
    {
        $this->fakeAnalysis([
            'title' => 'Edwardian Diamond Cluster Ring',
            'category' => 'rings',
            'metal_type' => '950 Platinum',
            'measurements' => '17.2mm',
            'style_period' => 'Edwardian',
        ]);

        // A title is required to save a draft at all, so the assistant is
        // judged on the fields left blank.
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class)
            ->set('values.sku', 'EST-7001')
            ->set('values.title', 'Untitled intake')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'EST-7001')->firstOrFail();
        Storage::disk('public')->put("products/{$product->id}/front.jpg", 'photo');
        ProductImage::create([
            'product_id' => $product->id, 'type' => 'front',
            'file_path' => "products/{$product->id}/front.jpg", 'is_primary' => true,
        ]);

        $component->call('analysePhotos')->assertSet('error', null);

        $values = $component->get('values');

        $this->assertSame('rings', $values['category']);
        $this->assertSame('950 Platinum', $values['metal_type']);
        $this->assertSame('Edwardian', $values['style_period']);

        $suggested = $product->fresh()->ai_suggested_fields;
        $this->assertContains('category', $suggested);
        $this->assertContains('metal_type', $suggested);

        // The title someone typed is left alone.
        $this->assertSame('Untitled intake', $values['title']);
    }

    public function test_the_assistant_never_overwrites_what_a_person_wrote(): void
    {
        $this->fakeAnalysis(['title' => 'Machine title', 'category' => 'rings']);

        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class)
            ->set('values.sku', 'EST-7002')
            ->set('values.title', 'The title I typed myself')
            ->call('saveDraft');

        $product = Product::where('sku', 'EST-7002')->firstOrFail();
        Storage::disk('public')->put("products/{$product->id}/front.jpg", 'photo');
        ProductImage::create([
            'product_id' => $product->id, 'type' => 'front',
            'file_path' => "products/{$product->id}/front.jpg", 'is_primary' => true,
        ]);

        $component->call('analysePhotos');

        $this->assertSame('The title I typed myself', $component->get('values')['title']);
        $this->assertNotContains('title', $product->fresh()->ai_suggested_fields ?? []);
    }

    public function test_an_unreadable_reply_is_reported_not_swallowed(): void
    {
        $product = $this->productWithPhoto();

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'sorry, I cannot help']]]])]);

        $this->expectExceptionMessage('could not read');

        app(AiCatalogueService::class)->analysePhotos($product);
    }

    public function test_a_service_failure_surfaces_as_a_readable_message(): void
    {
        $product = $this->productWithPhoto();

        Http::fake(['*' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        $this->expectExceptionMessage('quota exceeded');

        app(AiCatalogueService::class)->analysePhotos($product);
    }

    public function test_photographs_are_sent_inline_so_they_need_no_public_url(): void
    {
        $product = $this->productWithPhoto();
        $this->fakeAnalysis(['title' => 'A ring', 'category' => 'rings']);

        app(AiCatalogueService::class)->analysePhotos($product);

        Http::assertSent(function ($request) {
            $content = $request['messages'][1]['content'];

            return str_starts_with($content[1]['image_url']['url'], 'data:');
        });
    }
}
