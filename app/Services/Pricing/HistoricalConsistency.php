<?php

namespace App\Services\Pricing;

use App\Models\Setting;

/**
 * Does this piece describe something that could have existed?
 *
 * A maker founded in 1906 cannot have made a Georgian piece, and a house that
 * dissolved in 1900 cannot have made a 1950s one. The pricing stack will
 * happily multiply a maker premium by a period premium without ever asking
 * whether the combination is possible, and a confident price for a piece that
 * could not exist is worse than no price at all.
 *
 * Two rules govern this class:
 *
 *   It only speaks when it is certain. A maker with no founding year on
 *   record, or a period with no dates, produces silence — never a guess. A
 *   false accusation against a genuine piece costs more than a missed check.
 *
 *   It never decides. It reports, and a reviewer accepts, corrects or
 *   rejects. The maker dates are a starting table, not scholarship.
 */
class HistoricalConsistency
{
    /**
     * Makers that are not a house at all, and so can never conflict.
     *
     * "Unsigned", "attributed to", a retailer's mark: none of these assert
     * that a particular workshop made the piece.
     */
    private const NOT_A_MAKER = ['unsigned', 'attributed', 'retailer mark', 'unknown', 'unmarked'];

    /**
     * The problem with this combination, or null when there is none.
     *
     * @return array{problem: string, maker: string, period: string}|null
     */
    public function check(?string $maker, ?string $period): ?array
    {
        if (blank($maker) || blank($period)) {
            return null;
        }

        $makerKey = strtolower(trim($maker));

        foreach (self::NOT_A_MAKER as $phrase) {
            if (str_contains($makerKey, $phrase)) {
                return null;
            }
        }

        $makerYears = $this->lookup('pricing.maker_years', $maker);
        $periodYears = $this->lookup('pricing.period_years', $period);

        // Nothing on record for one side of the comparison: say nothing.
        if ($makerYears === null || $periodYears === null) {
            return null;
        }

        $founded = $this->year($makerYears, 'founded');
        $dissolved = $this->year($makerYears, 'dissolved');
        $from = $this->year($periodYears, 'from');
        $to = $this->year($periodYears, 'to');

        if ($founded !== null && $to !== null && $founded > $to) {
            return $this->problem(
                "{$maker} was founded in {$founded}; the {$period} period ended in {$to}.",
                $maker,
                $period,
            );
        }

        if ($dissolved !== null && $from !== null && $dissolved < $from) {
            return $this->problem(
                "{$maker} ceased trading in {$dissolved}; the {$period} period began in {$from}.",
                $maker,
                $period,
            );
        }

        return null;
    }

    /** True when the combination is impossible on the dates we hold. */
    public function conflicts(?string $maker, ?string $period): bool
    {
        return $this->check($maker, $period) !== null;
    }

    /** @return array{problem: string, maker: string, period: string} */
    private function problem(string $problem, string $maker, string $period): array
    {
        return ['problem' => $problem, 'maker' => $maker, 'period' => $period];
    }

    /** @return array<string, mixed>|null */
    private function lookup(string $settingKey, string $needle): ?array
    {
        $table = Setting::get($settingKey, []);

        if (! is_array($table)) {
            return null;
        }

        $needle = strtolower(trim($needle));

        foreach ($table as $key => $value) {
            if (is_array($value) && str_contains($needle, strtolower((string) $key))) {
                return $value;
            }
        }

        return null;
    }

    private function year(array $row, string $field): ?int
    {
        $value = $row[$field] ?? null;

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
