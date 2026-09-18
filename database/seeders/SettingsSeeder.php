<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsRegistry;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /** Creates any missing setting at its default. Never overwrites a stored value. */
    public function run(): void
    {
        foreach (SettingsRegistry::all() as $path => $meta) {
            [$group, $key] = explode('.', $path, 2);

            $exists = Setting::query()->where('group', $group)->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            Setting::query()->create([
                'group' => $group,
                'key' => $key,
                'type' => $meta['type'],
                'is_encrypted' => $meta['type'] === 'encrypted_string',
                'value' => null,
            ]);

            if ($meta['default'] !== null) {
                Setting::set($path, $meta['default']);
            }
        }

        Setting::flushCache();
    }
}
