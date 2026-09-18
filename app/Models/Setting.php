<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Every Super-Admin-configurable value (CLAUDE.md rule 3.1).
 *
 *   Setting::get('payments.active_gateway', 'stripe');
 *   Setting::set('payments.active_gateway', 'stripe', $userId);
 *
 * Encrypted settings decrypt on read and are never rendered back into a form —
 * use masked() for display (rule 3.2).
 */
class Setting extends Model
{
    use Auditable;

    protected const CACHE_KEY = 'settings.all';

    protected $fillable = ['group', 'key', 'value', 'is_encrypted', 'type', 'updated_by'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Only encrypted values are masked in the audit log — a gateway switch or a
     * changed tax rate should stay readable in the trail (Module 1 acceptance).
     */
    public function auditRedactedAttributes(): array
    {
        return $this->is_encrypted ? ['value'] : [];
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $row = static::all_cached()->get($path);

        if (! $row) {
            return $default;
        }

        $value = $row['value'];

        if ($row['is_encrypted']) {
            if ($value === null || $value === '') {
                return $default;
            }

            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                // A key rotation or corrupted row must not take checkout down.
                report(new \RuntimeException("Unable to decrypt setting [{$path}]."));

                return $default;
            }
        }

        return static::castValue($value, $row['type'], $default);
    }

    public static function set(string $path, mixed $value, ?int $userId = null): self
    {
        [$group, $key] = static::splitPath($path);

        $existing = static::query()->where('group', $group)->where('key', $key)->first();
        $type = $existing?->type ?? static::inferType($value);
        $encrypted = $existing?->is_encrypted ?? ($type === 'encrypted_string');

        $stored = match (true) {
            $value === null => null,
            $type === 'json' => json_encode($value),
            $type === 'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        if ($encrypted && $stored !== null) {
            $stored = Crypt::encryptString($stored);
        }

        $setting = static::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $stored, 'type' => $type, 'is_encrypted' => $encrypted, 'updated_by' => $userId],
        );

        static::flushCache();

        return $setting;
    }

    /**
     * Masked form of a stored secret, for display only: sk_live_••••4417.
     * Returns null when nothing is stored yet.
     */
    public static function masked(string $path): ?string
    {
        $value = static::get($path);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $prefix = '';
        if (preg_match('/^([a-z]+_[a-z]+_)/i', $value, $m)) {
            $prefix = $m[1];
        }

        return $prefix.'••••'.substr($value, -4);
    }

    public static function forGroup(string $group): array
    {
        return static::all_cached()
            ->filter(fn (array $row) => $row['group'] === $group)
            ->mapWithKeys(fn (array $row, string $path) => [$row['key'] => static::get($path)])
            ->all();
    }

    /** True when a Phase 2 feature flag is on. Flags default OFF (rule 3.4). */
    public static function enabled(string $path): bool
    {
        return (bool) static::get($path, false);
    }

    /**
     * @return \Illuminate\Support\Collection<string, array{group:string,key:string,value:?string,is_encrypted:bool,type:string}>
     */
    protected static function all_cached()
    {
        // Cached as a plain array: serialising an Eloquent-backed Collection into
        // the cache store breaks on unserialize in some runtimes.
        $rows = Cache::rememberForever(static::CACHE_KEY, function () {
            return static::query()
                ->get(['group', 'key', 'value', 'is_encrypted', 'type'])
                ->mapWithKeys(fn (self $s) => [
                    "{$s->group}.{$s->key}" => [
                        'group' => $s->group,
                        'key' => $s->key,
                        'value' => $s->value,
                        'is_encrypted' => (bool) $s->is_encrypted,
                        'type' => $s->type,
                    ],
                ])
                ->all();
        });

        return collect($rows);
    }

    public static function flushCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    protected static function splitPath(string $path): array
    {
        if (! str_contains($path, '.')) {
            throw new \InvalidArgumentException("Setting path [{$path}] must be in group.key form.");
        }

        return explode('.', $path, 2);
    }

    protected static function castValue(?string $value, string $type, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($value, true) ?? $default,
            default => $value,
        };
    }

    protected static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
