<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Settings\RateProposals;
use App\Models\RateChangeProposal;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pricing\RateProposalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The AI proposes; the appraiser approves.
 *
 * This is the one place where a single judgement could move every price at
 * once, so it is the one place that is deliberately slow.
 */
class RateProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $appraiser;

    private RateProposalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->appraiser = User::factory()->create();
        $this->appraiser->assignRole('inventory-specialist');

        $this->service = app(RateProposalService::class);
    }

    private function proposeBridal(float $value = 1.11): RateChangeProposal
    {
        Setting::set('pricing.category_demand', ['bridal' => 1.08]);

        $this->service->propose([[
            'table_key' => 'pricing.category_demand',
            'entry_key' => 'bridal',
            'proposed_value' => $value,
            'reason' => 'Rising demand',
            'confidence' => 91,
        ]]);

        return RateChangeProposal::sole();
    }

    public function test_a_proposal_changes_nothing_until_it_is_approved(): void
    {
        $this->proposeBridal();

        // The queue has it; the rate table does not.
        $this->assertSame('pending', RateChangeProposal::sole()->status);
        $this->assertSame(1.08, (float) Setting::get('pricing.category_demand')['bridal']);
    }

    public function test_approving_writes_the_rate(): void
    {
        $proposal = $this->proposeBridal();

        $result = $this->service->approve([$proposal->id], $this->appraiser->id);

        $this->assertSame(1, $result['applied']);
        $this->assertSame(1.11, (float) Setting::get('pricing.category_demand')['bridal']);
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertSame($this->appraiser->id, $proposal->fresh()->decided_by);
    }

    public function test_rejecting_leaves_the_rate_alone_but_keeps_the_record(): void
    {
        $proposal = $this->proposeBridal();

        $this->service->reject([$proposal->id], $this->appraiser->id, 'Not what we are seeing');

        $this->assertSame(1.08, (float) Setting::get('pricing.category_demand')['bridal']);
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('Not what we are seeing', $proposal->fresh()->decision_note);
    }

    /**
     * A person's later edit outranks the machine's earlier opinion.
     *
     * Without this, approving a stale batch would silently undo a deliberate
     * change somebody made in the meantime.
     */
    public function test_a_rate_edited_since_the_proposal_is_not_overwritten(): void
    {
        $proposal = $this->proposeBridal();

        // The appraiser edits the rate by hand before clearing the queue.
        Setting::set('pricing.category_demand', ['bridal' => 1.20]);

        $result = $this->service->approve([$proposal->id], $this->appraiser->id);

        $this->assertSame(0, $result['applied']);
        $this->assertSame(1, $result['stale']);
        $this->assertSame(1.20, (float) Setting::get('pricing.category_demand')['bridal']);
        $this->assertSame('stale', $proposal->fresh()->status);
    }

    /** A proposal that could not be a sane rate never reaches the queue. */
    public function test_an_absurd_proposal_is_refused_rather_than_queued(): void
    {
        Setting::set('pricing.category_demand', ['bridal' => 1.08]);

        $this->service->propose([
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'bridal', 'proposed_value' => 400],
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'estate', 'proposed_value' => 0],
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'watches', 'proposed_value' => 1.05],
        ]);

        $this->assertSame(1, RateChangeProposal::count());
        $this->assertSame('watches', RateChangeProposal::sole()->entry_key);
    }

    public function test_a_table_nobody_configured_cannot_be_proposed_against(): void
    {
        $this->expectExceptionMessage('is not a rate table');

        $this->service->propose([[
            'table_key' => 'payments.stripe_secret_key',
            'entry_key' => 'anything',
            'proposed_value' => 1.0,
        ]]);
    }

    public function test_a_batch_of_many_rates_is_one_decision(): void
    {
        Setting::set('pricing.category_demand', ['bridal' => 1.08, 'estate' => 1.00, 'watches' => 1.05]);

        $this->service->propose([
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'bridal', 'proposed_value' => 1.11],
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'estate', 'proposed_value' => 0.95],
            ['table_key' => 'pricing.category_demand', 'entry_key' => 'watches', 'proposed_value' => 1.07],
        ]);

        $result = $this->service->approve(RateChangeProposal::pluck('id')->all(), $this->appraiser->id);

        $this->assertSame(3, $result['applied']);

        $table = Setting::get('pricing.category_demand');
        $this->assertSame(1.11, (float) $table['bridal']);
        $this->assertSame(0.95, (float) $table['estate']);
        $this->assertSame(1.07, (float) $table['watches']);
    }

    public function test_the_review_screen_shows_what_is_waiting(): void
    {
        $this->proposeBridal();

        Livewire::actingAs($this->appraiser)
            ->test(RateProposals::class)
            ->assertOk()
            ->assertSee('Bridal')
            ->assertSee('Rising demand')
            ->assertSee('Nothing changes until you approve it.');
    }

    public function test_the_appraiser_can_clear_a_batch_from_the_screen(): void
    {
        $proposal = $this->proposeBridal();

        Livewire::actingAs($this->appraiser)
            ->test(RateProposals::class)
            ->call('toggleAll')
            ->call('approveSelected')
            ->assertSee('1 rate updated');

        $this->assertSame(1.11, (float) Setting::get('pricing.category_demand')['bridal']);
    }

    public function test_sales_staff_cannot_open_the_queue(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('sales-staff');

        Livewire::actingAs($staff)
            ->test(RateProposals::class)
            ->assertForbidden();
    }

    /** A rate change is a money change, so it is audited like one (rule 3.5). */
    public function test_approving_is_audit_logged_against_the_appraiser(): void
    {
        $this->actingAs($this->appraiser);

        $proposal = $this->proposeBridal();

        $this->service->approve([$proposal->id], $this->appraiser->id);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Setting::class,
            'user_id' => $this->appraiser->id,
        ]);

        // And the settings row itself names who moved it.
        $this->assertSame(
            $this->appraiser->id,
            Setting::where('key', 'category_demand')->value('updated_by'),
        );
    }
}
