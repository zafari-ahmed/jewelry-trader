<?php

namespace App\Providers;

use App\Services\AI\AiProviderResolver;
use App\Services\AI\Contracts\AiSearchProvider;
use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Contracts\AiVisionProvider;
use App\Services\AI\Providers\NullAiSearchProvider;
use App\Services\AI\Providers\NullAiTextProvider;
use App\Services\AI\Providers\NullAiVisionProvider;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Bound lazily: the settings table is read when a contract is resolved, so
     * flipping a capability in the admin panel takes effect on the next request
     * with no deploy (rule 3.3).
     */
    public function register(): void
    {
        $bindings = [
            AiVisionProvider::class => [NullAiVisionProvider::class, 'ai.vision'],
            AiTextProvider::class => [NullAiTextProvider::class, 'ai.description'],
            AiSearchProvider::class => [NullAiSearchProvider::class, 'ai.search'],
        ];

        foreach ($bindings as $contract => [$null, $flag]) {
            $this->app->bind($contract, fn ($app) => $app->make(AiProviderResolver::class)->resolve($contract, $null, $flag));
        }
    }
}
