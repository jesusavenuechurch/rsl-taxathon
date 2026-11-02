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
