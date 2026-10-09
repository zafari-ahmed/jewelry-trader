<?php

namespace App\Services\Pricing;

/**
 * Reading a rate table entry, whatever shape it is in.
 *
 * A rate used to be a bare number. The appraiser's design gives each one a
 * confidence and a provenance as well — "×1.45, 92%, auction data" — because
 * a multiplier nobody can source is an opinion wearing a decimal point.
 *
 * Both shapes are accepted, so a table somebody has only ever typed numbers
 * into keeps working:
 *
 *     'cartier' => 1.45
 *     'cartier' => ['multiplier' => 1.45, 'confidence' => 92, 'source' => 'Auction data']
 *
 * Keys are matched loosely and most-specific-first, so "18K Yellow Gold"
 * finds the "18k" row and "fine Burmese sapphire" finds the Burmese tier
 * before the bare "sapphire" one.
 */
final class RateTable
{
    /** Field names accepted for the rate itself, in order of preference. */
    private const VALUE_KEYS = ['multiplier', 'value', 'rate', 'adjustment'];

    /** The rate alone, for callers that do not care where it came from. */
    public static function value(array $table, ?string $needle): ?float
    {
        return self::entry($table, $needle)?->value;
    }

    /** The rate with its confidence and provenance, where they were recorded. */
    public static function entry(array $table, ?string $needle): ?RateEntry
    {
        if (blank($needle)) {
            return null;
        }

        $needle = strtolower(trim($needle));

        foreach ($table as $key => $raw) {
            if (str_contains($needle, strtolower((string) $key))) {
                return self::read($raw, (string) $key);
            }
        }

        return null;
    }

    /** Read one entry by its exact key, without the loose matching. */
    public static function exact(array $table, string $key): ?RateEntry
    {
        return array_key_exists($key, $table) ? self::read($table[$key], $key) : null;
    }

    /**
     * Write a rate back, keeping whatever shape the row already had.
     *
     * A table of bare numbers stays a table of bare numbers unless the write
     * carries a confidence or a source, in which case the row grows to hold
     * them. Nobody has to migrate a table to start recording provenance.
     */
    public static function write(array $table, string $key, float $value, ?int $confidence = null, ?string $source = null): array
    {
        $existing = $table[$key] ?? null;
        $wasStructured = is_array($existing);

        if (! $wasStructured && $confidence === null && $source === null) {
            $table[$key] = $value;

            return $table;
        }

        $row = $wasStructured ? $existing : [];
        $row['multiplier'] = $value;

        // Drop any older spelling of the value so the row has one source of
        // truth rather than two fields that can disagree.
        foreach (array_slice(self::VALUE_KEYS, 1) as $alias) {
            unset($row[$alias]);
        }

        if ($confidence !== null) {
            $row['confidence'] = $confidence;
        }

        if ($source !== null) {
            $row['source'] = $source;
        }

        $table[$key] = $row;

        return $table;
    }

    private static function read(mixed $raw, string $key): ?RateEntry
    {
        if (is_numeric($raw)) {
            return new RateEntry($key, (float) $raw, null, null);
        }

        if (! is_array($raw)) {
            return null;
        }

        foreach (self::VALUE_KEYS as $field) {
            if (isset($raw[$field]) && is_numeric($raw[$field])) {
                // A blank confidence is genuinely unknown. Reading it as
                // zero would brand every unrated row as worthless and set
                // off the low-confidence warning on all of them.
                $confidence = $raw['confidence'] ?? null;

                return new RateEntry(
                    $key,
                    (float) $raw[$field],
                    ($confidence === null || $confidence === '') ? null : (int) $confidence,
                    isset($raw['source']) && $raw['source'] !== '' ? (string) $raw['source'] : null,
                );
            }
        }

        // A banded entry (diamond by carat weight) is not a single rate and
        // is read elsewhere, by the code that knows the carat weight.
        return null;
    }
}
