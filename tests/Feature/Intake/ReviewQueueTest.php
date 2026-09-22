<?php

namespace Tests\Feature\Intake;

use App\Livewire\Inventory\ReviewQueue;
use App\Livewire\Settings\FieldRules;
use App\Models\FieldColorRule;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\ProductIntakeService;
use Database\Seeders\FieldColorRuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private User $salesStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FieldColorRuleSeeder::class);

        $location = Location::factory()->create();

        $this->reviewer = User::factory()->create(['location_id' => $location->id]);
        $this->reviewer->assignRole('inventory-specialist');

        $this->salesStaff = User::factory()->create(['location_id' => $location->id]);
        $this->salesStaff->assignRole('sales-staff');
    }

    private function submitted(): Product
    {
        return Product::factory()->create([
            'status' => 'pending_review',
            'category' => 'rings',
            'submitted_for_review_at' => now(),
            'created_at' => now()->subMinutes(3),
        ]);
    }

    public function test_approving_and_listing_are_two_deliberate_steps(): void
    {
        $product = $this->submitted();

        $component = Livewire::actingAs($this->reviewer)->test(ReviewQueue::class)->call('select', $product->id);

        $component->call('approve');

        // Approved for the record, but not yet published for sale.
        $this->assertSame('approved', $product->fresh()->status);
        $this->assertSame($this->reviewer->id, $product->fresh()->approved_by);

        $component->call('listItem');

        $this->assertSame('listed', $product->fresh()->status);
    }

    public function test_an_item_cannot_be_listed_before_it_is_approved(): void
    {
        $product = $this->submitted();

        $this->expectExceptionMessage('must be approved before it can be listed');

        app(ProductIntakeService::class)->list($product);
    }

    public function test_a_reviewer_can_return_an_item_to_the_submitter(): void
    {
        $product = $this->submitted();

        Livewire::actingAs($this->reviewer)
            ->test(ReviewQueue::class)
            ->call('select', $product->id)
            ->call('returnToSubmitter');

        $product->refresh();

        $this->assertSame('draft', $product->status);
        $this->assertNull($product->submitted_for_review_at);
    }

    public function test_sales_staff_cannot_approve(): void
    {
        $product = $this->submitted();

        // Sales Staff create and edit inventory subject to approval; they do
        // not approve it themselves.
        Livewire::actingAs($this->salesStaff)
            ->test(ReviewQueue::class)
            ->call('select', $product->id)
            ->call('approve')
            ->assertForbidden();

        $this->assertSame('pending_review', $product->fresh()->status);
    }

    public function test_the_intake_metric_is_surfaced_to_managers(): void
    {
        Product::factory()->create(['status' => 'pending_review', 'created_at' => now()->subMinutes(4), 'submitted_for_review_at' => now()]);
        Product::factory()->create(['status' => 'pending_review', 'created_at' => now()->subMinutes(2), 'submitted_for_review_at' => now()]);

        $metric = Livewire::actingAs($this->reviewer)->test(ReviewQueue::class)->get('intakeMetric');

        $this->assertNotNull($metric);
        $this->assertSame(2, $metric['count']);
        $this->assertLessThanOrEqual(5, $metric['median'], 'Median intake should sit inside the five-minute target.');
    }

    public function test_field_rules_are_editable_from_settings_and_apply_at_once(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($superAdmin)
            ->test(FieldRules::class)
            ->set('form.field_name', 'movement_type')
            ->set('form.category', 'watches')
            ->set('form.color', 'red')
            ->set('form.is_required', true)
            ->set('form.section', 'materials')
            ->call('save')
            ->assertHasNoErrors();

        $rules = app(\App\Services\Inventory\FieldColorResolver::class)->rulesFor('watches');

        $this->assertSame('red', $rules['movement_type']['color']);
        $this->assertTrue($rules['movement_type']['is_required']);
    }

    public function test_a_gray_rule_cannot_also_be_required(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($superAdmin)
            ->test(FieldRules::class)
            ->set('form.field_name', 'chain_length')
            ->set('form.category', 'rings')
            ->set('form.color', 'gray')
            ->set('form.is_required', true)
            ->call('save');

        $rule = FieldColorRule::where('field_name', 'chain_length')->where('category', 'rings')->firstOrFail();

        $this->assertFalse($rule->is_required, 'A field that is not applicable cannot block submission.');
    }

    public function test_sales_staff_cannot_edit_field_rules(): void
    {
        Livewire::actingAs($this->salesStaff)->test(FieldRules::class)->assertForbidden();
    }
}
