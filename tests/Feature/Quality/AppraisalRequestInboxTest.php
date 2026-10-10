<?php

namespace Tests\Feature\Quality;

use App\Livewire\Settings\QualityControl;
use App\Livewire\Shop\AppraisalRequests;
use App\Models\AppraisalRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The inbox behind the public promise, and the screen that ranks the checks.
 *
 * Both are rendered here rather than only exercised underneath: a customer
 * form writing into a table nobody can read is a half-finished feature, and
 * a settings page can be broken while every service behind it passes.
 */
class AppraisalRequestInboxTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('customer-service');
    }

    private function request(array $overrides = []): AppraisalRequest
    {
        return AppraisalRequest::create(array_merge([
            'product_id' => Product::factory()->create()->id,
            'name' => 'A Buyer',
            'email' => 'buyer@example.test',
            'message' => 'Could I see the gemstone certificate?',
            'status' => 'open',
        ], $overrides));
    }

    public function test_the_inbox_shows_an_open_request(): void
    {
        $product = Product::factory()->create(['sku' => 'EST-7001']);
        $this->request(['product_id' => $product->id]);

        Livewire::actingAs($this->staff)
            ->test(AppraisalRequests::class)
            ->assertOk()
            ->assertSee('A Buyer')
            ->assertSee('buyer@example.test')
            ->assertSee('EST-7001')
            ->assertSee('gemstone certificate');
    }

    public function test_a_request_can_be_closed_and_reopened(): void
    {
        $request = $this->request();

        Livewire::actingAs($this->staff)
            ->test(AppraisalRequests::class)
            ->call('markHandled', $request->id)
            ->assertSee('Marked as sent');

        $request->refresh();
        $this->assertSame('handled', $request->status);
        $this->assertSame($this->staff->id, $request->handled_by);

        Livewire::actingAs($this->staff)
            ->test(AppraisalRequests::class)
            ->set('filter', 'handled')
            ->call('reopen', $request->id);

        $this->assertSame('open', $request->fresh()->status);
        $this->assertNull($request->fresh()->handled_by);
    }

    public function test_the_filter_separates_open_from_sent(): void
    {
        $this->request(['name' => 'Still Waiting']);
        $this->request(['name' => 'Already Sent', 'status' => 'handled']);

        Livewire::actingAs($this->staff)
            ->test(AppraisalRequests::class)
            ->assertSee('Still Waiting')
            ->assertDontSee('Already Sent')
            ->set('filter', 'handled')
            ->assertSee('Already Sent')
            ->assertDontSee('Still Waiting');
    }

    /** A request outlives the piece it was about. */
    public function test_a_deleted_piece_does_not_break_the_inbox(): void
    {
        $request = $this->request();
        Product::whereKey($request->product_id)->delete();

        Livewire::actingAs($this->staff)
            ->test(AppraisalRequests::class)
            ->assertOk();
    }

    public function test_sales_staff_cannot_open_the_inbox(): void
    {
        $other = User::factory()->create();
        $other->assignRole('sales-staff');

        Livewire::actingAs($other)
            ->test(AppraisalRequests::class)
            ->assertForbidden();
    }

    // ---- the quality settings screen -----------------------------------

    public function test_the_quality_settings_screen_lists_every_check(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($admin)
            ->test(QualityControl::class)
            ->assertOk()
            ->assertSee('Photographs taken')
            ->assertSee('Hallmarks verified under magnification')
            ->assertSee('needs a person')
            ->assertSee('read from the record');
    }

    /** Whether a missing hallmark blocks a sale is the business's call. */
    public function test_a_check_can_be_re_ranked_from_the_screen(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($admin)
            ->test(QualityControl::class)
            ->set('ranks.2_4', 'critical')
            ->set('state.minimum_photos', 8)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('critical', Setting::get('qc.check_types')['2.4']);
        $this->assertSame(8, (int) Setting::get('qc.minimum_photos'));
    }

    public function test_an_impossible_photograph_minimum_is_refused(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        Livewire::actingAs($admin)
            ->test(QualityControl::class)
            ->set('state.minimum_photos', 0)
            ->call('save')
            ->assertHasErrors('state.minimum_photos');
    }
}
