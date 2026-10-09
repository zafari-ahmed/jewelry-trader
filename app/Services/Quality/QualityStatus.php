<?php

namespace App\Services\Quality;

/**
 * The five states a check can be in, and the colour each one shows.
 *
 * Deliberately the same five colours the rest of the build uses, so staff
 * learn the language once: red blocks, amber waits, green is done, grey does
 * not apply, blue is a person overruling the machine.
 */
final class QualityStatus
{
    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public const NOT_APPLICABLE = 'not_applicable';

    /** The colour for one check, given whether a human overrode it. */
    public static function colour(string $status, bool $isOverride = false): string
    {
        if ($isOverride) {
            return 'blue';
        }

        return match ($status) {
            self::PASSED => 'green',
            self::FAILED => 'red',
            self::NOT_APPLICABLE => 'gray',
            default => 'yellow',
        };
    }

    /**
     * The colour for a whole stage.
     *
     * A failed critical check is red whatever else passed — that is the point
     * of calling it critical. Otherwise anything still waiting makes the
     * stage amber, and a stage with nothing applicable is grey rather than
     * falsely complete.
     */
    public static function stageColour(iterable $checks): string
    {
        $applicable = 0;
        $passed = 0;
        $pending = 0;

        foreach ($checks as $check) {
            if ($check->status === self::NOT_APPLICABLE) {
                continue;
            }

            $applicable++;

            if ($check->status === self::FAILED && $check->check_type === 'critical') {
                return 'red';
            }

            if ($check->status === self::PASSED) {
                $passed++;
            } elseif ($check->status === self::PENDING) {
                $pending++;
            }
        }

        if ($applicable === 0) {
            return 'gray';
        }

        if ($passed === $applicable) {
            return 'green';
        }

        return $pending > 0 ? 'yellow' : 'red';
    }
}
