<?php

namespace App\Services\Pricing;

use App\Models\Setting;
use App\Support\SettingsRegistry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loading a pricing configuration from a file.
 *
 * The appraiser's rate table arrives as a document and leaves as settings.
 * Until now that journey ran through a developer editing a seeder, which
 * makes the person who maintains the rates dependent on the person who
 * maintains the code — exactly what rule 3.1 exists to prevent.
 *
 * Nothing is written until somebody has seen what would change. A rate table
 * is the business's money: replacing one silently from an uploaded file is
 * the kind of operation that should feel deliberate.
 */
class ConfigurationImport
{
    /**
     * Keys that may never arrive in a file.
     *
     * A key is a secret, not configuration. It is excluded from the export
     * and refused on the way back in, so a configuration file can be emailed
     * around without carrying credentials with it.
     */
    private const NEVER_IMPORTED = ['live_rates_api_key'];

    /**
     * Read a file and say what it would change. Writes nothing.
     *
     * @return array{changes: array<int, array{key:string, label:string, from:mixed, to:mixed, rows:?int}>, ignored: string[], exported_at: ?string}
     */
    public function preview(string $json): array
    {
        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('That file is not readable as JSON.');
        }

        $pricing = $payload['pricing'] ?? null;

        if (! is_array($pricing) || $pricing === []) {
            throw new RuntimeException('That file has no pricing configuration in it.');
        }

        $known = SettingsRegistry::group('pricing');
        $changes = [];
        $ignored = [];

        foreach ($pricing as $key => $incoming) {
            $path = 'pricing.'.$key;

            if (in_array($key, self::NEVER_IMPORTED, true)) {
                $ignored[] = $key.' (a key is a secret and is never imported)';

                continue;
            }

            if (! array_key_exists($path, $known)) {
                $ignored[] = $key.' (not a pricing setting)';

                continue;
            }

            $current = Setting::get($path);

            if ($this->same($current, $incoming)) {
                continue;
            }

            $changes[] = [
                'key' => $key,
                'label' => $known[$path]['label'] ?? $key,
                'from' => $current,
                'to' => $incoming,
                'rows' => is_array($incoming) ? count($incoming) : null,
            ];
        }

        return [
            'changes' => $changes,
            'ignored' => $ignored,
            'exported_at' => $payload['exported_at'] ?? null,
        ];
    }

    /**
     * Apply a previewed import.
     *
     * Every write goes through Setting::set, so each one is audited with its
     * before and after values — a rate table loaded from a file is as
     * traceable as one typed in by hand.
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function apply(array $changes, ?int $userId): int
    {
        return DB::transaction(function () use ($changes, $userId) {
            $applied = 0;

            foreach ($changes as $change) {
                if (in_array($change['key'], self::NEVER_IMPORTED, true)) {
                    continue;
                }

                Setting::set('pricing.'.$change['key'], $change['to'], $userId);
                $applied++;
            }

            Setting::flushCache();

            return $applied;
        });
    }

    /** Loose comparison: a rate stored as "1.45" equals one given as 1.45. */
    private function same(mixed $current, mixed $incoming): bool
    {
        return json_encode($this->normalise($current)) === json_encode($this->normalise($incoming));
    }

    private function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = array_map(fn ($v) => $this->normalise($v), $value);
            ksort($out);

            return $out;
        }

        return is_numeric($value) ? (string) (0 + $value) : $value;
    }
}
