<?php

namespace App\Domain\Analysis;

use InvalidArgumentException;

/** A rule version's thresholds plus the statutory rates from settings. */
final class RuleParameters
{
    /**
     * @param  array<string, mixed>  $values  thresholds from the rule version
     * @param  array<string, float>  $rates  vat, cit, wht_standard
     */
    public function __construct(private array $values, private array $rates) {}

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            throw new InvalidArgumentException("Rule parameter '{$key}' is not configured.");
        }

        return $this->values[$key];
    }

    public function rate(string $tax): float
    {
        return $this->rates[$tax] ?? throw new InvalidArgumentException("No rate configured for '{$tax}'.");
    }
}
