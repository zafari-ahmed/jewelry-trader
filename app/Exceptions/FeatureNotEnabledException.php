<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when code reaches a capability that is not active in this phase.
 * Feature flags should stop callers well before this point; hitting it means
 * the seam held but a flag check was missed.
 */
class FeatureNotEnabledException extends RuntimeException
{
    public static function for(string $capability): self
    {
        return new self("The [{$capability}] capability is not enabled. Turn it on in Settings → AI once a provider is configured.");
    }
}
