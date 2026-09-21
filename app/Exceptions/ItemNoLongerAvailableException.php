<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an item is claimed between adding it to a cart and paying —
 * the one-of-a-kind race the sold-once guarantee exists to prevent.
 */
class ItemNoLongerAvailableException extends RuntimeException
{
    public static function for(string $sku): self
    {
        return new self("{$sku} is no longer available — another sale claimed it first.");
    }
}
