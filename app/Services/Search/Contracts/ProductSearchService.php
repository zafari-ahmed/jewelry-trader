<?php

namespace App\Services\Search\Contracts;

use Illuminate\Support\Collection;

/**
 * The storefront's search contract. Phase 1 resolves to keyword search;
 * Module 2's AiSearchProvider becomes an alternate implementation in Phase 2,
 * so the endpoint contract never changes (CLAUDE.md Module 7).
 *
 * @param  array{category?:string, style_period?:string, metal_type?:string, min_price?:int, max_price?:int, sort?:string}  $filters
 */
interface ProductSearchService
{
    public function search(string $query, array $filters = []): Collection;
}
