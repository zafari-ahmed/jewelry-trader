<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LocationSeeder::class,
            SettingsSeeder::class,
            PaymentGatewaySeeder::class,
            AiProviderSeeder::class,
            FieldColorRuleSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $user = User::query()->firstOrCreate(
                ['email' => 'admin@jewelrytrader.test'],
                [
                    'name' => 'M. Renner',
                    'password' => 'password',
                    'location_id' => Location::query()->where('slug', 'madison-ave')->value('id'),
                ],
            );

            $user->syncRoles([RolesAndPermissionsSeeder::SUPER_ADMIN]);
        }
    }
}
