<?php

namespace App\Services\AI\Providers;

use App\Exceptions\FeatureNotEnabledException;
use App\Services\AI\Contracts\AiSearchProvider;
use Illuminate\Support\Collection;

class NullAiSearchProvider implements AiSearchProvider
{
    public function search(string $naturalLanguageQuery): Collection
    {
        throw FeatureNotEnabledException::for('ai.search');
    }
}
