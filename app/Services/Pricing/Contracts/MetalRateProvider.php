<?php

namespace App\Services\Pricing\Contracts;

/**
 * Where the price of a metal comes from.
 *
 * Two implementations, and which one answers is a setting (rule 3.3):
 * the rate table the business maintains, or a live market feed layered over
 * it. The table is always there as the fallback, so a feed that is switched
 * off, slow or unreachable can never stop a piece being priced.
 */
interface MetalRateProvider
{
    /** Dollars per gram for a metal description, or null when it is not known. */
    public function ratePerGram(string $metal): ?float;

    /** A short line for the working, e.g. "live feed · platinum up 3.75%". */
    public function sourceLabel(): string;
}
