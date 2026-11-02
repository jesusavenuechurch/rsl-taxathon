<?php

namespace App\Domain\Analysis\Wording;

/**
 * Number formatting that matches the reference implementation (rules.py)
 * character for character: Python rounds exact halves to even
 * (f"{2.5:.0f}" == "2"), and so must we, or wording tests drift.
 */
final class PyFormat
{
    /** Python's round(x) / f"{x:.0f}": nearest integer, exact halves to even. */
    public static function roundHalfEven(float|int $x): int
    {
        if (is_int($x)) {
            return $x;
        }
        $floor = floor($x);
        $diff = $x - $floor; // exact for the magnitudes we handle

        $rounded = match (true) {
            $diff > 0.5 => $floor + 1,
            $diff < 0.5 => $floor,
            default => fmod($floor, 2.0) === 0.0 ? $floor : $floor + 1,
        };

        return (int) $rounded;
    }

    /** rules.py M(): "M1,234,567", or "a loss of M400,000" for negatives. */
    public static function money(float|int $x): string
    {
        return $x >= 0
            ? 'M'.number_format(self::roundHalfEven($x))
            : 'a loss of M'.number_format(self::roundHalfEven(-$x));
    }

    /** f"{x:.0%}": Python multiplies by 100 in floating point, then rounds. */
    public static function percent(float|int $x): string
    {
        return self::roundHalfEven($x * 100).'%';
    }

    /** f"{x:.0f}" */
    public static function fixed0(float|int $x): string
    {
        return (string) self::roundHalfEven($x);
    }
}
