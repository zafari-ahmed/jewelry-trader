<?php

namespace App\Services\Inventory;

use App\Models\FieldColorRule;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * One source of truth for the traffic-light field system (CLAUDE.md Module 5).
 *
 * A field's *rule* is configuration (field_color_rules); a field's *colour* is
 * the rule plus the current value:
 *
 *   gray   rule says not applicable — stays gray whatever the value
 *   blue   a human replaced a suggested value (products.manually_overridden_fields)
 *   green  a required or suggested field that now holds a valid value
 *   red    required and still empty — blocks submission, server-side
 *   yellow suggested and still empty
 *
 * Phase 2 changes only what writes manually_overridden_fields; this mapping is
 * already what the screens read, so no screen changes when AI arrives.
 */
class FieldColorResolver
{
    public const MODEL = 'App\\Models\\Product';

    /**
     * Rules that apply to a category: defaults, with category rows overriding.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public function rulesFor(?string $category, string $model = self::MODEL): Collection
    {
        $rules = FieldColorRule::cached()->where('model', $model);

        $defaults = $rules->whereNull('category')->keyBy('field_name');
        $specific = $category ? $rules->where('category', $category)->keyBy('field_name') : collect();

        return $defaults
            ->merge($specific)
            ->sortBy('sort_order');
    }

    /** Rules grouped by section, for rendering the form. */
    public function sectionsFor(?string $category, string $model = self::MODEL): Collection
    {
        return $this->rulesFor($category, $model)->groupBy('section');
    }

    /**
     * The colour a field should render in right now.
     *
     * @param  array<string, mixed>  $rule
     */
    public function colorFor(array $rule, mixed $value, array $overriddenFields = [], array $suggestedFields = []): string
    {
        if ($rule['color'] === 'gray') {
            return 'gray';
        }

        if (in_array($rule['field_name'], $overriddenFields, true)) {
            return 'blue';
        }

        // An unverified suggestion stays yellow however complete it looks, so
        // nobody mistakes the machine's answer for a person's.
        if (in_array($rule['field_name'], $suggestedFields, true)) {
            return 'yellow';
        }

        if ($this->hasValue($value)) {
            return 'green';
        }

        if ($rule['is_required']) {
            return 'red';
        }

        // An empty optional field has no status to report.
        return $rule['color'] === 'green' ? 'neutral' : $rule['color'];
    }

    /**
     * Required fields still empty. Submission is blocked while this is
     * non-empty — checked server-side, never only in the browser.
     *
     * A suggestion counts as filled: the reviewer is the gate on accuracy,
     * not the submit button.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string> field names
     */
    public function missingRequired(?string $category, array $values, string $model = self::MODEL): array
    {
        return $this->rulesFor($category, $model)
            ->filter(fn (array $rule) => $rule['is_required'] && $rule['color'] !== 'gray')
            ->reject(fn (array $rule) => $this->hasValue($values[$rule['field_name']] ?? null))
            ->pluck('field_name')
            ->values()
            ->all();
    }

    public function canSubmit(?string $category, array $values, string $model = self::MODEL): bool
    {
        return $this->missingRequired($category, $values, $model) === [];
    }

    /**
     * Completeness counts for the progress panel.
     *
     * @return array{complete:int, missing:int, awaiting:int, overridden:int, not_applicable:int, optional:int, percent:int}
     */
    public function completeness(?string $category, array $values, array $overriddenFields = [], string $model = self::MODEL, array $suggestedFields = []): array
    {
        $counts = ['complete' => 0, 'missing' => 0, 'awaiting' => 0, 'overridden' => 0, 'not_applicable' => 0, 'optional' => 0];

        $rules = $this->rulesFor($category, $model);

        foreach ($rules as $rule) {
            $color = $this->colorFor($rule, $values[$rule['field_name']] ?? null, $overriddenFields, $suggestedFields);

            match ($color) {
                'green' => $counts['complete']++,
                'red' => $counts['missing']++,
                'yellow' => $counts['awaiting']++,
                'blue' => $counts['overridden']++,
                'gray' => $counts['not_applicable']++,
                'neutral' => $counts['optional']++,
            };
        }

        $scored = max(1, $rules->count() - $counts['not_applicable']);
        $counts['percent'] = (int) round((($counts['complete'] + $counts['overridden']) / $scored) * 100);

        return $counts;
    }

    /**
     * Record that a human replaced a value, so Phase 2's correction logging
     * has data from day one.
     */
    public function markOverridden(Product $product, string $fieldName): void
    {
        $fields = $product->manually_overridden_fields ?? [];

        if (in_array($fieldName, $fields, true)) {
            return;
        }

        $fields[] = $fieldName;

        $product->update(['manually_overridden_fields' => array_values($fields)]);
    }

    private function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && trim((string) $value) !== '';
    }
}
