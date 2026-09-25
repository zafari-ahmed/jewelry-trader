<?php

namespace App\Services\AI\Providers;

use App\Models\Setting;
use App\Services\AI\Contracts\AiSearchProvider;
use App\Services\Search\KeywordProductSearch;
use Illuminate\Support\Collection;

/**
 * Natural-language search: the customer's sentence is turned into the filters
 * the catalogue already understands, then the existing search runs.
 *
 * Interpreting rather than ranking keeps the guarantee that only listed,
 * in-stock, unlocked pieces can ever be returned — the model never selects
 * which records a customer sees.
 */
class HttpSearchProvider extends HttpChatProvider implements AiSearchProvider
{
    public function __construct(private KeywordProductSearch $keyword) {}

    public function search(string $naturalLanguageQuery): Collection
    {
        $this->assertEnabled('ai.search');
        $this->assertConfigured();

        $filters = $this->interpret($naturalLanguageQuery);

        return $this->keyword->search($filters['terms'] ?? '', $filters);
    }

    /** @return array<string, mixed> */
    public function interpret(string $query): array
    {
        $model = (string) Setting::get('ai.search_model', '');

        if ($model === '' || trim($query) === '') {
            return ['terms' => $query];
        }

        try {
            $response = $this->send([
                ['role' => 'system', 'content' =>
                    'Turn a shopper\'s sentence into catalogue filters for an antique and estate jewelry dealer. '.
                    'Reply with a JSON object using only these keys: terms (free text worth matching), category, '.
                    'style_period, metal_type, min_price, max_price (whole currency units), sort '.
                    '(newest, price_asc or price_desc). Omit anything the sentence does not state.',
                ],
                ['role' => 'user', 'content' => $query],
            ], $model);

            $decoded = $this->decode($this->content($response));
        } catch (\Throwable) {
            // A search box must never fail because a model is slow or down.
            return ['terms' => $query];
        }

        if (! is_array($decoded)) {
            return ['terms' => $query];
        }

        return array_intersect_key($decoded, array_flip([
            'terms', 'category', 'style_period', 'metal_type', 'min_price', 'max_price', 'sort',
        ]));
    }
}
