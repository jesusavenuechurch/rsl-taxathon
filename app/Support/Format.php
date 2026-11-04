<?php

namespace App\Support;

use App\Domain\Records\Field;
use Carbon\CarbonInterface;

/** Display formatting for screens. (Finding wording has its own formatter.) */
final class Format
{
    public static function money(?int $amount): string
    {
        if ($amount === null) {
            return '–';
        }

        return ($amount < 0 ? '-' : '').'M'.number_format(abs($amount));
    }

    /** officer-desk.md section 4: "Evidence level 2" means nothing to an officer who hasn't memorised the ladder. */
    public static function evidenceLevel(int $level): string
    {
        return match ($level) {
            1 => 'Based on the return only',
            2 => 'Two RSL records disagree',
            3 => 'Group records involved',
            4 => 'Taxpayer documents received',
            default => "Evidence level {$level}",
        };
    }

    public static function field(Field $field, mixed $value): string
    {
        if ($value === null) {
            return '–';
        }

        return match ($field->type) {
            Field::MONEY => self::money($value),
            Field::INTEGER => number_format($value),
            Field::YES_NO => $value ? 'Yes' : 'No',
            Field::DATE => $value instanceof CarbonInterface ? $value->format('j M Y') : (string) $value,
            default => (string) $value,
        };
    }
}
