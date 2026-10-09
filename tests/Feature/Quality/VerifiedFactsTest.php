<?php

namespace Tests\Feature\Quality;

use App\Livewire\Shop\VerifiedFacts;
use App\Models\AppraisalRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Quality\QualityControlService;
use App\Services\Quality\QualityStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What a customer is told — and what they are deliberately not told.
 *
 * The internal score measures record completeness. A customer would read it
 * as a judgement about the piece, and a fair-condition piece with a complete
 * record would show full marks. That gap is a liability in a trade where
 * buyers rely on dealer representations, so none of it reaches the page.
 */
class VerifiedFactsTest extends TestCase
{
    use RefreshDatabase;

    private QualityControlService $qc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->qc = app(QualityControlService::class);
    }

    private function appraiser(bool $named = false): User
    {
        $user = User::factory()->create([
            'name' => 'Sarah Chen',
            'job_title' => 'Senior Appraiser',
            'show_name_publicly' => $named,
        ]);
        $user->assignRole('inventory-specialist');

        return $user;
    }

    private function verifiedProduct(User $appraiser): Product
    {
        $product = Product::factory()->create(['status' => 'listed', 'metal_type' => '950 Platinum']);

        $this->qc->evaluate($product);
        $this->qc->record($product, '3.3', QualityStatus::PASSED, $appraiser);
        $this->qc->record($product, '4.7', QualityStatus::PASSED, $appraiser);

        return $product->fresh();
    }

    /** No score, no stars, anywhere on the customer page. */
    public function test_the_customer_panel_shows_no_score_and_no_stars(): void
    {
        $product = $this->verifiedProduct($this->appraiser());

        $rendered = Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->assertSee('Hallmarks confirmed')
            ->assertSee('Appraisal on file')
            ->assertDontSee('★')
            ->assertDontSee('Quality Score')
            ->html();

        $this->assertDoesNotMatchRegularExpression('/\b\d{1,3}%/', $rendered);
        $this->assertStringNotContainsString('out of 5', $rendered);
    }

    /** Role and date by default; the name stays in the record. */
    public function test_a_claim_is_credited_to_a_role_rather_than_a_name(): void
    {
        $product = $this->verifiedProduct($this->appraiser(named: false));

        Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->assertSee('Senior Appraiser')
            ->assertDontSee('Sarah Chen');
    }

    /** Unless that person has opted in. */
    public function test_a_staff_member_can_opt_into_being_named(): void
    {
        $product = $this->verifiedProduct($this->appraiser(named: true));

        Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->assertSee('Sarah Chen, Senior Appraiser');
    }

    /** A claim that has not been verified is not made. */
    public function test_an_unverified_claim_is_simply_absent(): void
    {
        $product = Product::factory()->create(['status' => 'listed']);
        $this->qc->evaluate($product);

        Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->assertDontSee('Hallmarks confirmed')
            ->assertDontSee('Appraisal on file');
    }

    public function test_the_panel_can_be_switched_off_entirely(): void
    {
        Setting::set('qc.show_customer_panel', false);

        $product = $this->verifiedProduct($this->appraiser());

        $this->assertSame([], Livewire::test(VerifiedFacts::class, ['product' => $product])->instance()->claims);
    }

    /** A promise with no inbox behind it is worse than no promise. */
    public function test_requesting_the_full_appraisal_reaches_the_team(): void
    {
        $product = $this->verifiedProduct($this->appraiser());

        Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->set('name', 'A Buyer')
            ->set('email', 'buyer@example.test')
            ->set('message', 'Could I see the gemstone certificate?')
            ->call('requestAppraisal')
            ->assertHasNoErrors()
            ->assertSee('we have your request');

        $request = AppraisalRequest::sole();

        $this->assertSame($product->id, $request->product_id);
        $this->assertSame('buyer@example.test', $request->email);
        $this->assertSame('open', $request->status);
    }

    public function test_a_request_without_an_email_is_refused(): void
    {
        $product = $this->verifiedProduct($this->appraiser());

        Livewire::test(VerifiedFacts::class, ['product' => $product])
            ->set('name', 'A Buyer')
            ->set('email', 'not-an-email')
            ->call('requestAppraisal')
            ->assertHasErrors('email');

        $this->assertSame(0, AppraisalRequest::count());
    }
}
