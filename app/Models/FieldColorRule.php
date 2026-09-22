<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * One rule per field (optionally per category). Cached because the intake
 * form reads every rule on every render; the cache is dropped on any write so
 * a Settings change takes effect immediately (Module 5 acceptance).
 */
class FieldColorRule extends Model
{
    use Auditable;

    public const CACHE_KEY = 'field_color_rules.all';

    protected $fillable = [
        'model', 'field_name', 'category', 'color', 'is_required',
        'section', 'label', 'help', 'sort_order',
    ];

    protected $casts = ['is_required' => 'boolean'];

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    public static function cached(): \Illuminate\Support\Collection
    {
        return collect(Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (self $r) => $r->only([
                'id', 'model', 'field_name', 'category', 'color',
                'is_required', 'section', 'label', 'help', 'sort_order',
            ]))
            ->all()));
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public function humanLabel(): string
    {
        return $this->label ?: str($this->field_name)->headline()->toString();
    }
}
