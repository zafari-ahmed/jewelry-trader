<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Locations as LocationsComponent;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        return $user;
    }

    public function test_a_location_is_created_with_its_tax_rate_stored_as_a_rate(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(LocationsComponent::class)
            ->set('form.name', 'Santa Monica')
            ->set('form.city', 'Santa Monica')
            ->set('form.state', 'ca')
            ->set('form.tax_rate', '10.25')
            ->set('form.timezone', 'America/Los_Angeles')
            ->call('save')
            ->assertHasNoErrors();

        $location = Location::query()->where('name', 'Santa Monica')->firstOrFail();

        $this->assertSame('CA', $location->state);
        $this->assertSame('0.102500', (string) $location->tax_rate);
        $this->assertSame('santa-monica', $location->slug);
    }

    public function test_creating_a_location_is_audited(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(LocationsComponent::class)
            ->set('form.name', 'Pasadena')
            ->set('form.tax_rate', '9.5')
            ->set('form.timezone', 'America/Los_Angeles')
            ->call('save');

        $this->assertDatabaseHas('audit_logs', ['action' => 'location.created']);

        $log = AuditLog::query()->where('action', 'location.created')->latest('id')->firstOrFail();

        $this->assertSame('Pasadena', $log->new_values['name']);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(LocationsComponent::class)
            ->set('form.name', 'Nowhere')
            ->set('form.tax_rate', '5')
            ->set('form.timezone', 'Mars/Olympus')
            ->call('save')
            ->assertHasErrors('form.timezone');
    }
}
