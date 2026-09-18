<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Models\Setting;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves which implementation backs an AI contract, by reading the settings
 * table at runtime — never a compiled config file (rule 3.3).
 *
 * A provider row carries the class that implements it, so activating a provider
 * in Phase 2 is a data row plus a class. When no row, no class, or no enabled
 * capability applies, the Null implementation is returned: calling it throws
 * rather than silently doing nothing.
 */
class AiProviderResolver
{
    public function __construct(private Container $container) {}

    /**
     * @param  class-string  $contract
     * @param  class-string  $nullImplementation
     * @param  string  $capabilityFlag  e.g. ai.vision
     */
    public function resolve(string $contract, string $nullImplementation, string $capabilityFlag): object
    {
        if (! Setting::enabled('ai.enabled') || ! Setting::enabled($capabilityFlag)) {
            return $this->container->make($nullImplementation);
        }

        $driver = $this->driverFor($contract);

        if ($driver === null) {
            return $this->container->make($nullImplementation);
        }

        return $this->container->make($driver);
    }

    /** The configured provider's driver class, when it implements the contract. */
    private function driverFor(string $contract): ?string
    {
        $slug = Setting::get('ai.provider');

        if (! $slug) {
            return null;
        }

        $driver = AiProvider::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->value('driver_class');

        if (! $driver || ! class_exists($driver)) {
            return null;
        }

        return is_subclass_of($driver, $contract) || in_array($contract, class_implements($driver) ?: [], true)
            ? $driver
            : null;
    }
}
