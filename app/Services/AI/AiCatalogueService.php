<?php

namespace App\Services\AI;

use App\Models\AiCorrection;
use App\Models\Product;
use App\Models\Setting;
use App\Services\AI\Contracts\AiTextProvider;
use App\Services\AI\Contracts\AiVisionProvider;
use App\Services\Pricing\PricingEngine;
use Illuminate\Support\Facades\DB;

/**
 * The cataloguing assistant: photographs in, suggested record out.
 *
 * Three rules hold throughout:
 *   · nothing is decided — every value lands as a suggestion for a person;
 *   · a suggestion is marked as one, and stays yellow until accepted;
 *   · what a person changes is recorded, so the corrections become a
 *     training signal rather than being lost.
 */
class AiCatalogueService
{
    /** Analysis keys that map onto a product field of the same name. */
    private const DIRECT_FIELDS = [
        'title', 'category', 'subcategory', 'style_period', 'metal_type',
        'measurements', 'hallmark_text', 'brand', 'condition_notes',
    ];

    public function __construct(
        private AiVisionProvider $vision,
        private AiTextProvider $text,
        private PricingEngine $pricing,
    ) {}

    /**
     * Read the photographs and return suggested field values.
     *
     * @return array{values: array<string, mixed>, result: AiAnalysisResult, gemstones: array}
     */
    public function analysePhotos(Product $product): array
    {
        $paths = $product->images()->orderBy('sort_order')->pluck('file_path')->all();

        $result = $this->vision->analyze($paths, 'full');

        $result->persistFor($product->id, 'full');

        $minimum = (int) Setting::get('ai.min_confidence', 40);

        if ($result->confidenceScore < $minimum) {
            // Too uncertain to be worth a person's time correcting.
            return ['values' => [], 'result' => $result, 'gemstones' => []];
        }

        $raw = $result->rawResponse;
        $values = [];

        foreach (self::DIRECT_FIELDS as $field) {
            if (filled($raw[$field] ?? null)) {
                $values[$field] = is_array($raw[$field]) ? implode(', ', $raw[$field]) : $raw[$field];
            }
        }

        if (filled($raw['weight_estimate_grams'] ?? null)) {
            $values['weight_grams'] = (float) $raw['weight_estimate_grams'];
        }

        if (filled($raw['condition_grade'] ?? null)) {
            $values['condition_grade'] = $raw['condition_grade'];
        }

        return [
            'values' => $values,
            'result' => $result,
            'gemstones' => is_array($raw['gemstones'] ?? null) ? $raw['gemstones'] : [],
        ];
    }

    /**
     * Draft the five descriptions from attributes a person has in front of
     * them — never straight from the photographs, so the copy can only
     * describe what the record actually claims.
     *
     * @return array<string, string>
     */
    public function describe(array $attributes): array
    {
        $audiences = [
            'customer_description' => 'A description for the shop\'s own website: two or three sentences, warm but factual.',
            'seo_description' => 'A single search-engine meta description under 160 characters.',
            'marketplace_description' => 'A listing description for a third-party marketplace: factual, specification-led.',
            'social_description' => 'One short social post, under 280 characters, no hashtag spam.',
            'internal_description' => 'A terse internal note for staff: what it is, what to watch for.',
        ];

        $prompt = "Write the following, returning a JSON object whose keys are exactly: ".
            implode(', ', array_keys($audiences)).".\n\n";

        foreach ($audiences as $key => $instruction) {
            $prompt .= "- {$key}: {$instruction}\n";
        }

        $reply = $this->text->generate($prompt, $attributes + ['expects_json' => true]);

        $decoded = json_decode($reply, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter(
            array_intersect_key($decoded, $audiences),
            fn ($value) => is_string($value) && trim($value) !== '',
        );
    }

    /** A suggested price, with the workings, from the rate table. */
    public function suggestPrice(array $attributes, array $gemstones = []): \App\Services\Pricing\PricingSuggestion
    {
        return $this->pricing->suggest($attributes, $gemstones);
    }

    /**
     * Mark fields as holding unverified suggestions, so they render yellow.
     *
     * @param  string[]  $fields
     */
    public function markSuggested(Product $product, array $fields): void
    {
        $existing = $product->ai_suggested_fields ?? [];

        $product->update([
            'ai_suggested_fields' => array_values(array_unique(array_merge($existing, $fields))),
        ]);
    }

    /** A person accepted the suggestion as it stood: it becomes their answer. */
    public function acceptSuggestion(Product $product, string $field): void
    {
        $product->update([
            'ai_suggested_fields' => array_values(array_diff($product->ai_suggested_fields ?? [], [$field])),
        ]);
    }

    /**
     * A person replaced a suggestion. The field becomes a human override, and
     * the change is logged — this is the training signal Phase 2 depends on.
     */
    public function recordCorrection(Product $product, string $field, mixed $original, mixed $final, ?int $userId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($product, $field, $original, $final, $userId, $reason) {
            AiCorrection::create([
                'product_id' => $product->id,
                'field_name' => $field,
                'original_value' => is_scalar($original) ? (string) $original : json_encode($original),
                'final_value' => is_scalar($final) ? (string) $final : json_encode($final),
                'corrected_by' => $userId,
                'reason' => $reason,
            ]);

            $product->update([
                'ai_suggested_fields' => array_values(array_diff($product->ai_suggested_fields ?? [], [$field])),
                'manually_overridden_fields' => array_values(array_unique(
                    array_merge($product->manually_overridden_fields ?? [], [$field]),
                )),
            ]);
        });
    }

    public function isAvailable(): bool
    {
        return Setting::enabled('ai.enabled')
            && filled(Setting::get('ai.endpoint'))
            && filled(Setting::get('ai.api_key'));
    }
}
