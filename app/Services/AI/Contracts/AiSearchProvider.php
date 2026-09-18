<?php

namespace App\Services\AI\Contracts;

use Illuminate\Support\Collection;

/**
 * Natural-language product search. Module 7's ProductSearchService keeps the
 * same contract, so switching to this in Phase 2 changes no calling code.
 */
interface AiSearchProvider
{
    public function search(string $naturalLanguageQuery): Collection;
}
