<?php

namespace Tests\Feature\Quality;

use App\Livewire\Quality\Gauge;
use App\Models\Product;
use App\Models\QualityControlCheck;
use App\Models\User;
use App\Services\Quality\QualityStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The gauge as staff actually meet it.
 *
 * Rendered here rather than only unit-tested underneath, because a page can
 * be broken while every service behind it passes — which has happened on
 * this build before.
 */
class GaugePageTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->staff = User::factory()->create();
        $this->staff->assignRole('inventory-specialist');
    }

    public function test_the_gauge_renders_every_stage(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->staff)
            ->test(Gauge::class, ['product' => $product])
            ->assertOk()
            ->assertSee('Inception')
            ->assertSee('Cataloguing')
            ->assertSee('Post-sale');
    }

    /** The work list is the point: only what a person still has to do. */
    public function test_outstanding_lists_critical_work_first(): void
    {
        $product = Product::factory()->create();

        $component = Livewire::actingAs($this->staff)->test(Gauge::class, ['product' => $product]);

        $outstanding = $component->instance()->outstanding;

        $this->assertNotEmpty($outstanding);
        $this->assertSame('critical', $outstanding->first()->check_type);
    }

    public function test_a_person_can_record_a_check_against_their_name(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->staff)
            ->test(Gauge::class, ['product' => $product])
            ->call('mark', '3.3', QualityStatus::PASSED)
            ->assertSee('Recorded against your name');

        $row = QualityControlCheck::where('product_id', $product->id)->where('check_key', '3.3')->first();

        $this->assertSame(QualityStatus::PASSED, $row->status);
        $this->assertSame($this->staff->id, $row->verified_by);
    }

    public function test_a_blocked_piece_says_so_and_names_what_is_missing(): void
    {
        $product = Product::factory()->create();

        Livewire::actingAs($this->staff)
            ->test(Gauge::class, ['product' => $product])
            ->assertSee('Not ready to sell')
            ->assertSee('critical');
    }

    /** Someone who cannot see the piece cannot see its gauge. */
    public function test_someone_with_no_product_access_is_refused(): void
    {
        $outsider = User::factory()->create();

        Livewire::actingAs($outsider)
            ->test(Gauge::class, ['product' => Product::factory()->create()])
            ->assertForbidden();
    }
}
