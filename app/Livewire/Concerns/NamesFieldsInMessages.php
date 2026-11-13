<?php

namespace App\Livewire\Concerns;

/**
 * Keeps the property path out of the validation message.
 *
 * Livewire validates nested state — `form.name`, `state.restock_fee_percent`,
 * `stones.0.treatment` — and Laravel has no name for those, so it falls back
 * to the key itself. The customer form told people "The form.name field is
 * required", which names a variable in our code rather than the box they are
 * looking at.
 *
 * The last segment of the path is almost always the right word, so it is
 * derived rather than listed: a new field gets a readable message without
 * anybody remembering to add one. Where the derived word is poor — a key named
 * for how it is stored rather than what it is — a component overrides
 * `fieldNames()`.
 *
 * `validate()` is overridden rather than `validationAttributes()` because half
 * these components pass their rules inline, and an inline call never consults
 * the attributes the component declares.
 */
trait NamesFieldsInMessages
{
    public function validate($rules = null, $messages = [], $attributes = [])
    {
        $rules ??= $this->getRules();

        // Caller's names win, then the component's own, then anything it
        // already declared the Livewire way, then what we can derive.
        $declared = method_exists($this, 'validationAttributes') ? $this->validationAttributes() : [];

        $attributes = $attributes
            + $this->fieldNames()
            + $declared
            + $this->derivedFieldNames(array_keys($rules ?? []));

        return parent::validate($rules, $messages, $attributes);
    }

    /**
     * Names this component wants to set itself, keyed by rule path.
     *
     * @return array<string, string>
     */
    protected function fieldNames(): array
    {
        return [];
    }

    /**
     * `state.restock_fee_percent` → "restock fee percent".
     * `state.default_tiers.0.threshold` → "threshold".
     * `stones.*.stone_type` → "stone type".
     *
     * @param  array<int, string>  $paths
     * @return array<string, string>
     */
    private function derivedFieldNames(array $paths): array
    {
        $names = [];

        foreach ($paths as $path) {
            $segments = array_filter(
                explode('.', (string) $path),
                // Indices and wildcards name a position, never a field.
                fn (string $segment) => $segment !== '*' && ! ctype_digit($segment),
            );

            $last = end($segments);

            if ($last === false || $last === '') {
                continue;
            }

            $names[$path] = str_replace('_', ' ', $last);
        }

        return $names;
    }
}
