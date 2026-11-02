<?php

namespace App\Domain\Records;

/** One column of a source file and how to read it. */
final class Field
{
    public const STRING = 'string';

    public const TEXT = 'text';

    public const INTEGER = 'integer';

    public const MONEY = 'money';       // whole Maloti

    public const DATE = 'date';         // YYYY-MM-DD

    public const YES_NO = 'yes_no';     // "Yes" / "No"

    public const PERIOD = 'period';     // YYYY-MM

    public const TAX_YEAR = 'tax_year'; // 2025/26

    public function __construct(
        public readonly string $column,
        public readonly string $attribute,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $nullable = false,
    ) {}
}
