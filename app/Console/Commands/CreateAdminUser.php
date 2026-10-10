<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Create the first Super Admin on a fresh installation.
 *
 * The database seeder deliberately creates its demo account only in local and
 * testing, so a production install has no way in until this is run. Without
 * it the first deployment is a locked door.
 *
 * The password is prompted for rather than passed as an argument: an argument
 * ends up in the shell history and in the process list, where anyone else on
 * the server can read it.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'jt:create-admin
                            {--name= : The person\'s name}
                            {--email= : Their email address}
                            {--location= : Location slug to attach them to}';

    protected $description = 'Create a Super Admin account';

    public function handle(): int
    {
        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email address', required: true);

        if (User::where('email', $email)->exists()) {
            $this->error("A user with {$email} already exists.");

            return self::FAILURE;
        }

        $plain = password('Password', required: true);
        $confirmation = password('Confirm the password', required: true);

        if ($plain !== $confirmation) {
            $this->error('Those passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plain],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $location = $this->option('location')
            ? Location::where('slug', $this->option('location'))->first()
            : Location::query()->orderBy('id')->first();

        if (! Role::where('name', RolesAndPermissionsSeeder::SUPER_ADMIN)->exists()) {
            $this->error('Roles have not been seeded yet. Run: php artisan db:seed --class=RolesAndPermissionsSeeder');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
            'location_id' => $location?->id,
            'is_active' => true,
        ]);

        $user->assignRole(RolesAndPermissionsSeeder::SUPER_ADMIN);

        $this->newLine();
        $this->info("Super Admin created: {$email}");
        $this->line('Two-factor enrolment is required on first sign-in; have an authenticator app ready.');

        return self::SUCCESS;
    }
}
