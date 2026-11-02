<?php

namespace App\Domain\Analysis;

/** What a rule found, before wording is rendered and it is saved. */
final class FindingDraft
{
    /**
     * @param  string  $scope  distinguishes several findings of one rule for one taxpayer (a payee, a period...)
     * @param  array<string, mixed>  $values  placeholders for the wording template
     * @param  list<string>  $evidence  record IDs, in the order to show them
     */
    public function __construct(
        public readonly string $scope,
        public readonly array $values,
        public readonly array $evidence,
        public readonly float|int $exposure = 0,
        public readonly string $variant = 'default',
    ) {}
}
