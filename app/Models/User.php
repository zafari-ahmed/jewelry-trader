<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Concerns\Auditable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'location_id', 'is_active', 'show_name_publicly', 'job_title'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            // Rule 3.2: secrets are encrypted at rest.
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_active' => 'boolean',
            'show_name_publicly' => 'boolean',
        ];
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * How this person is credited on a public page.
     *
     * Role and date by default; the name only where they have opted in.
     * Publishing a named individual's professional judgement on a commercial
     * page attached to a high-value sale is their decision, not the shop's —
     * and the full name is always in the record, produced on request.
     */
    public function publicCredit(): string
    {
        $title = $this->job_title
            ?: str($this->getRoleNames()->first() ?? 'staff')->replace('-', ' ')->title()->toString();

        return $this->show_name_publicly ? "{$this->name}, {$title}" : $title;
    }
}
