<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The way into a fresh installation.
 *
 * The seeder's demo account exists only in local and testing, so without
 * this command a production deployment is a locked door.
 */
class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('jt:create-admin', ['--name' => 'M. Renner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password', 'Correct-Horse-9')
            ->expectsQuestion('Confirm the password', 'Correct-Horse-9')
            ->assertSuccessful();

        $user = User::where('email', 'owner@example.test')->sole();

        $this->assertTrue($user->hasRole(RolesAndPermissionsSeeder::SUPER_ADMIN));
        $this->assertTrue($user->is_active);
    }

    public function test_it_refuses_a_duplicate_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create(['email' => 'owner@example.test']);

        $this->artisan('jt:create-admin', ['--name' => 'Someone', '--email' => 'owner@example.test'])
            ->assertFailed();
    }

    public function test_it_refuses_mismatched_passwords(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('jt:create-admin', ['--name' => 'M. Renner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password', 'Correct-Horse-9')
            ->expectsQuestion('Confirm the password', 'Something-Else-9')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'owner@example.test')->count());
    }

    /** A weak password on the account that can do everything is not allowed. */
    public function test_it_refuses_a_weak_password(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->artisan('jt:create-admin', ['--name' => 'M. Renner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm the password', 'password')
            ->assertFailed();

        $this->assertSame(0, User::where('email', 'owner@example.test')->count());
    }

    /** Running it before the roles exist should say so, not fail obscurely. */
    public function test_it_explains_itself_when_roles_are_missing(): void
    {
        $this->artisan('jt:create-admin', ['--name' => 'M. Renner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password', 'Correct-Horse-9')
            ->expectsQuestion('Confirm the password', 'Correct-Horse-9')
            ->expectsOutputToContain('RolesAndPermissionsSeeder')
            ->assertFailed();
    }
}
