<?php

namespace Tests\Feature\Commission;

use App\Livewire\Admin\CommissionReport;
use App\Models\Commission;
use App\Models\Location;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Commission\PayrollExporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayrollExportTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->seller = tap(User::factory()->create(['name' => 'A. Whitfield']))->assignRole('sales-staff')->fresh();
    }

    private function commission(int $amountCents = 320770): Commission
    {
        $order = Order::create([
            'order_number' => Order::nextOrderNumber(),
            'location_id' => Location::factory()->create()->id,
            'channel' => 'pos',
            'status' => 'paid',
            'paid_at' => now(),
            'total_cents' => 6214000,
        ]);

        return Commission::create([
            'order_id' => $order->id,
            'user_id' => $this->seller->id,
            'amount_cents' => $amountCents,
            'commissionable_cents' => 6214000,
            'status' => 'approved',
            'calculated_at' => now(),
        ]);
    }

    public function test_the_column_mapping_is_configuration(): void
    {
        $commission = $this->commission();

        $csv = app(PayrollExporter::class)->toCsv(collect([$commission->load('user', 'order')]), '2026-09-30');

        $this->assertStringContainsString('employee_id,employee_name,period_end,amount', $csv);
        $this->assertStringContainsString('A. Whitfield', $csv);
        $this->assertStringContainsString('3207.70', $csv);

        // A different payroll system wants different columns — a settings
        // change, not a deploy.
        Setting::set('commission.payroll_export_column_mapping', [
            'staff_ref' => 'user.email',
            'gross' => 'commissionable',
            'commission_cents' => 'amount_cents',
            'state' => 'status',
        ]);

        $csv = app(PayrollExporter::class)->toCsv(collect([$commission->fresh(['user', 'order'])]), '2026-09-30');

        $this->assertStringContainsString('staff_ref,gross,commission_cents,state', $csv);
        $this->assertStringContainsString($this->seller->email, $csv);
        $this->assertStringContainsString('320770', $csv);
        $this->assertStringContainsString('approved', $csv);
    }

    public function test_an_unmapped_source_is_reported_not_silently_blank(): void
    {
        Setting::set('commission.payroll_export_column_mapping', ['mystery' => 'not.a.real.field']);

        $csv = app(PayrollExporter::class)->toCsv(collect([$this->commission()->load('user', 'order')]));

        $this->assertStringContainsString('[unmapped: not.a.real.field]', $csv);
    }

    public function test_staff_see_only_their_own_figures(): void
    {
        $mine = $this->commission(1000);

        $someoneElse = User::factory()->create();
        $theirs = $this->commission(9999);
        $theirs->update(['user_id' => $someoneElse->id]);

        $visible = Livewire::actingAs($this->seller)->test(CommissionReport::class)->get('commissions');

        $this->assertSame(1, $visible->count());
        $this->assertSame($mine->id, $visible->first()->id);
    }

    public function test_a_manager_sees_every_salespersons_figures(): void
    {
        $this->commission(1000);

        $other = User::factory()->create();
        $theirs = $this->commission(2000);
        $theirs->update(['user_id' => $other->id]);

        $manager = tap(User::factory()->create())->assignRole('store-manager')->fresh();

        $this->assertSame(2, Livewire::actingAs($manager)->test(CommissionReport::class)->get('commissions')->count());
    }

    public function test_only_a_user_with_export_payroll_can_download(): void
    {
        $this->commission();

        // Sales staff may see their own numbers but not export payroll.
        Livewire::actingAs($this->seller)->test(CommissionReport::class)->call('exportCsv')->assertForbidden();

        $accountant = tap(User::factory()->create())->assignRole('accountant')->fresh();

        Livewire::actingAs($accountant)->test(CommissionReport::class)->call('exportCsv')->assertOk();
    }

    public function test_someone_with_no_commission_permission_cannot_open_the_report(): void
    {
        $specialist = tap(User::factory()->create())->assignRole('inventory-specialist')->fresh();

        Livewire::actingAs($specialist)->test(CommissionReport::class)->assertForbidden();
    }
}
