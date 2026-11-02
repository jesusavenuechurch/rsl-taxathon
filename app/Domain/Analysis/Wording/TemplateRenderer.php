<?php

namespace App\Domain\Analysis\Wording;

use InvalidArgumentException;

/**
 * Fills a rule's wording template. Placeholders are {name} or {name|filter}:
 * money, percent, fixed0, int. Templates are editable by rule owners, so an
 * unknown placeholder or filter is an error, never silently blank.
 */
final class TemplateRenderer
{
    private const PATTERN = '/\{([a-z_][a-z0-9_]*)(?:\|([a-z0-9_]+))?\}/';

    public const FILTERS = ['money', 'percent', 'fixed0', 'int'];

    public static function render(string $template, array $values): string
    {
        return preg_replace_callback(self::PATTERN, function (array $m) use ($values) {
            [$token, $name] = $m;
            $filter = $m[2] ?? null;

            if (! array_key_exists($name, $values)) {
                throw new InvalidArgumentException("Template uses {$token} but the rule provides no '{$name}'.");
            }
            $value = $values[$name];

            return match ($filter) {
                null => (string) $value,
                'money' => PyFormat::money($value),
                'percent' => PyFormat::percent($value),
                'fixed0' => PyFormat::fixed0($value),
                'int' => (string) (int) $value,
                default => throw new InvalidArgumentException("Unknown filter '{$filter}' in {$token}."),
            };
        }, $template);
    }

    /** @return list<string> placeholder names used by a template */
    public static function placeholders(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $m);

        return array_values(array_unique($m[1]));
    }
}
