<?php

namespace App\Domain\Analysis;

use App\Models\Analysis\RuleCheck;

final class RuleOutcome
{
    /** @param list<FindingDraft> $findings */
    private function __construct(
        public readonly string $result,
        public readonly string $explanation,
        public readonly array $metrics = [],
        public readonly array $findings = [],
    ) {}

    public static function fired(array $findings, string $explanation, array $metrics = []): self
    {
        return new self(RuleCheck::FIRED, $explanation, $metrics, $findings);
    }

    public static function notFired(string $explanation, array $metrics = []): self
    {
        return new self(RuleCheck::NOT_FIRED, $explanation, $metrics);
    }

    public static function notApplicable(string $explanation): self
    {
        return new self(RuleCheck::NOT_APPLICABLE, $explanation);
    }
}
