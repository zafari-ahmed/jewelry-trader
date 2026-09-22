<?php

namespace Tests\Feature\Intake;

use App\Livewire\Inventory\ProductIntake;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\ProductIntakeService;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductIntakeTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);

        $this->location = Location::factory()->create();
        $this->staff = User::factory()->create(['location_id' => $this->location->id]);
        $this->staff->assignRole('sales-staff');
    }

    /** @return array<string, string> a complete ring */
    private function completeRing(): array
    {
        return [
            'values.sku' => 'EST-4412',
            'values.title' => 'Edwardian Diamond Cluster Ring',
            'values.category' => 'rings',
            'values.metal_type' => '950 Platinum',
            'values.measurements' => '17.2mm × 14.8mm',
            'values.ring_size' => '6 ¼',
            'values.condition_notes' => 'Original millegrain crisp.',
            'values.acquisition_value' => '3100.00',
            'values.retail_price' => '6800.00',
            'values.location_id' => (string) $this->location->id,
        ];
    }

    public function test_submission_is_blocked_while_a_red_field_is_empty(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach (array_diff_key($this->completeRing(), ['values.metal_type' => null]) as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('submitForReview');

        $this->assertContains('metal_type', $component->get('missing'));
        $this->assertSame('draft', Product::where('sku', 'EST-4412')->value('status'));
        $this->assertNull(Product::where('sku', 'EST-4412')->value('submitted_for_review_at'));
    }

    public function test_the_block_is_enforced_server_side_not_only_in_the_browser(): void
    {
        // Bypass the UI entirely: call the service as a crafted request would.
        $product = Product::factory()->create(['category' => 'rings', 'status' => 'draft']);

        $this->expectExceptionMessage('Complete every required field first');

        app(ProductIntakeService::class)->submitForReview($product, ['category' => 'rings', 'title' => 'Only a title']);
    }

    public function test_a_complete_item_submits_and_records_the_timestamp(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('submitForReview')->assertHasNoErrors();

        $product = Product::where('sku', 'EST-4412')->firstOrFail();

        $this->assertSame('pending_review', $product->status);
        $this->assertNotNull($product->submitted_for_review_at);
        $this->assertNotNull($product->intakeMinutes());
    }

    public function test_intake_is_achievable_in_well_under_five_minutes(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        $started = microtime(true);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('submitForReview')->assertHasNoErrors();

        $elapsed = microtime(true) - $started;

        // The workflow itself must not be the bottleneck: ten field writes and
        // a submit, server-side, in a fraction of the five-minute budget.
        $this->assertLessThan(60, $elapsed, 'The intake round-trips are too slow to hit the five-minute target.');

        $product = Product::where('sku', 'EST-4412')->firstOrFail();
        $this->assertLessThan(5, $product->intakeMinutes());
    }

    public function test_category_specific_required_fields_are_enforced(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach (array_diff_key($this->completeRing(), ['values.ring_size' => null]) as $field => $value) {
            $component->set($field, $value);
        }

        // A ring without a size cannot be submitted…
        $this->assertContains('ring_size', $component->get('missing'));

        // …but the same record as a brooch can: ring size is not applicable.
        $component->set('values.category', 'brooches');

        $this->assertNotContains('ring_size', $component->get('missing'));
    }

    public function test_a_field_with_no_column_is_stored_as_an_item_attribute(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('saveDraft')->assertHasNoErrors();

        $product = Product::where('sku', 'EST-4412')->firstOrFail();

        // ring_size has no products column; the rules are editable, so values
        // for new fields must persist without a migration.
        $this->assertSame('6 ¼', $product->attributes['ring_size']);
    }

    public function test_pricing_is_written_to_the_price_history_in_cents(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('saveDraft');

        $product = Product::where('sku', 'EST-4412')->firstOrFail();

        $this->assertSame(680000, $product->currentPricing->retail_price_cents);
        $this->assertSame(310000, $product->currentPricing->acquisition_value_cents);
        $this->assertSame('in_stock', $product->stock()->value('status'));
    }

    public function test_photos_are_stored_and_tagged_by_type(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        $component->call('saveDraft')
            // fake()->image() needs GD, which this host does not have; a fake
            // file with an image mime type exercises the same code path.
            ->set('photos', [
                UploadedFile::fake()->create('front.jpg', 120, 'image/jpeg'),
                UploadedFile::fake()->create('hallmark.jpg', 120, 'image/jpeg'),
            ])
            ->set('photoTypes', ['front', 'hallmark'])
            ->call('storePhotos')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'EST-4412')->firstOrFail();

        $this->assertSame(2, $product->images()->count());
        $this->assertSame(['front', 'hallmark'], $product->images()->pluck('type')->all());
        // The first photo becomes the hero used on the storefront card.
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_the_five_to_fifteen_photo_range_is_guidance_not_a_server_rule(): void
    {
        $component = Livewire::actingAs($this->staff)->test(ProductIntake::class);

        foreach ($this->completeRing() as $field => $value) {
            $component->set($field, $value);
        }

        // No photos at all, yet submission succeeds (docs/DECISIONS.md).
        $component->call('submitForReview')->assertHasNoErrors();

        $this->assertSame('pending_review', Product::where('sku', 'EST-4412')->value('status'));
    }
}
