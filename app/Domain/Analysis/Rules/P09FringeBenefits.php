<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P09: raised only from taxpayer documents (housing, vehicles). Wired up with document release. */
class P09FringeBenefits implements PatternRule
{
    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        return RuleOutcome::notApplicable('Raised only from taxpayer documents (housing and vehicle records), never from returns.');
    }
}
