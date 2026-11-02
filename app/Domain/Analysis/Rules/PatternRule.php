<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/**
 * One pattern of the catalogue. Rules only observe: they report what the
 * records show and why it may be worth a look. Thresholds come from
 * $params, never from constants in code.
 */
interface PatternRule
{
    public function evaluate(TaxpayerSnapshot $snapshot, RuleParameters $params): RuleOutcome;
}
