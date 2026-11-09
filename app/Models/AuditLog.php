<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'category', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
        // Writable so entries can be backdated when importing or testing
        // retention; the trail itself is append-only in practice.
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The entry as a line of English. Actions are stored as `noun.verb`
     * (`product.updated`, `auth.login_failed`), which reads badly on a
     * dashboard but carries everything needed to say it properly.
     */
    public function describe(): string
    {
        [$subject, $verb] = array_pad(explode('.', $this->action, 2), 2, '');

        $sentence = trim(ucfirst(str_replace('_', ' ', $subject)).' '.str_replace('_', ' ', $verb));

        return ($name = $this->subjectName()) ? "{$sentence} — {$name}" : $sentence;
    }

    /**
     * What the record is called, when the record is still there. An entry
     * outlives the row it describes — that is rather the point of a trail —
     * so a missing subject is normal and not an error.
     */
    public function subjectName(): ?string
    {
        $model = $this->auditable;

        if (! $model) {
            return null;
        }

        foreach (['order_number', 'sku', 'title', 'name', 'key'] as $attribute) {
            if (filled($model->{$attribute} ?? null)) {
                return (string) $model->{$attribute};
            }
        }

        return null;
    }

    /** Which status colour the feed's dot takes. */
    public function dotTone(): string
    {
        return match (true) {
            str_contains($this->action, 'failed'),
            str_contains($this->action, 'deleted') => 'required',
            str_contains($this->action, 'override') => 'override',
            str_contains($this->action, 'created'),
            str_contains($this->action, 'approved'),
            str_contains($this->action, 'paid') => 'valid',
            default => 'suggested',
        };
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
