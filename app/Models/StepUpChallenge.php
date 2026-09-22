<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class StepUpChallenge extends Model
{
    use Auditable;

    protected $fillable = ['label', 'symbol_path', 'symbol_text', 'answer_hash', 'is_active'];

    protected $hidden = ['answer_hash'];

    protected $casts = ['is_active' => 'boolean'];

    /** The answer is never stored or echoed in the clear (rule 3.2). */
    public array $auditRedacted = ['answer_hash'];

    public function setAnswer(string $answer): void
    {
        $this->answer_hash = Hash::make($this->normalise($answer));
    }

    public function matches(string $answer): bool
    {
        return Hash::check($this->normalise($answer), $this->answer_hash);
    }

    private function normalise(string $answer): string
    {
        return strtolower(trim($answer));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
