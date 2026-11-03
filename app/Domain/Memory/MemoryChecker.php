<?php

namespace App\Domain\Memory;

use App\Domain\Analysis\TaxpayerSnapshot;
use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\Memory\Decision;
use App\Models\Memory\DecisionCondition;
use App\Models\Memory\MemoryCheck;

/**
 * Rule 8: memory informs, never exempts. After the rules have run (and raised
 * whatever they raise), each earlier decision about the taxpayer is shown as
 * context, and the system states whether the figures are still within the
 * conditions that decision was made under.
 */
class MemoryChecker
{
    public const MET = 'met';

    public const NOT_MET = 'not_met';

    public const NOT_CHECKED = 'not_checked';    // no metric: the officer judges

    public const NO_DATA = 'no_data';

    /**
     * @param  list<Finding>  $findings  raised in this review
     * @return list<MemoryCheck>
     */
    public function check(TaxpayerSnapshot $s, FilingReview $review, array $findings): array
    {
        $checks = [];

        foreach (Decision::current()->where('tin', $s->tin)->with('conditionList')->orderBy('decided_on')->get() as $decision) {
            $results = $decision->conditionList->map(fn (DecisionCondition $c) => $this->evaluate($c, $s))->all();
            $outcome = $this->outcome($results);

            $checks[] = MemoryCheck::create([
                'filing_review_id' => $review->id,
                'decision_id' => $decision->id,
                'finding_id' => collect($findings)->firstWhere('pattern_code', $decision->pattern_code)?->id,
                'result' => $outcome,
                'statement' => $this->statement($decision, $outcome, $results),
                'condition_results' => $results,
            ]);
        }

        return $checks;
    }

    private function evaluate(DecisionCondition $c, TaxpayerSnapshot $s): array
    {
        $base = ['text' => $c->text, 'metric' => $c->metric];

        if (! $c->isCheckable()) {
            return [...$base, 'result' => self::NOT_CHECKED];
        }

        $actual = Metrics::value($c->metric, $s);
        if ($actual === null) {
            return [...$base, 'result' => self::NO_DATA, 'label' => Metrics::label($c->metric)];
        }

        $met = match ($c->operator) {
            'approx' => $c->value == 0 ? $actual == 0 : abs($actual / $c->value - 1) <= $c->tolerance,
            'lte' => $actual <= $c->value,
            'gte' => $actual >= $c->value,
            'between' => $actual >= $c->value && $actual <= $c->value_max,
            default => throw new \InvalidArgumentException("Unknown operator '{$c->operator}'."),
        };

        return [
            ...$base,
            'result' => $met ? self::MET : self::NOT_MET,
            'label' => Metrics::label($c->metric),
            'actual' => $actual,
            'actual_text' => Metrics::format($c->metric, $actual),
            'expected_text' => $this->expected($c),
        ];
    }

    private function expected(DecisionCondition $c): string
    {
        $f = fn (float $v) => Metrics::format($c->metric, $v);

        return match ($c->operator) {
            'approx' => "about {$f($c->value)}",
            'lte' => "at most {$f($c->value)}",
            'gte' => "at least {$f($c->value)}",
            'between' => "between {$f($c->value)} and {$f($c->value_max)}",
        };
    }

    private function outcome(array $results): string
    {
        $results = collect($results)->pluck('result');

        return match (true) {
            $results->contains(self::NOT_MET) => MemoryCheck::OUTSIDE,
            $results->contains(self::MET) => MemoryCheck::WITHIN,
            default => MemoryCheck::NOT_CHECKABLE,
        };
    }

    private function statement(Decision $d, string $outcome, array $results): string
    {
        $results = collect($results);
        $line = fn ($r) => "{$r['label']}: {$r['actual_text']} (condition: {$r['expected_text']})";
        $intro = "Previously reviewed {$d->tax_year_reviewed} under {$d->reference} ({$d->outcomeLabel()}): {$d->conclusion}.";

        $parts = [$intro];
        if ($outcome === MemoryCheck::OUTSIDE) {
            $parts[] = 'The pattern has moved beyond what that explanation covers. Outside its conditions: '
                .$results->where('result', self::NOT_MET)->map($line)->implode('; ').'.';
            if ($still = $results->where('result', self::MET)->map($line)->implode('; ')) {
                $parts[] = "Still within: {$still}.";
            }
        } elseif ($outcome === MemoryCheck::WITHIN) {
            $parts[] = 'Its conditions still hold: '.$results->where('result', self::MET)->map($line)->implode('; ').'.';
        }
        if ($open = $results->whereIn('result', [self::NOT_CHECKED, self::NO_DATA])->pluck('text')->implode('; ')) {
            $parts[] = "For the officer to judge: {$open}.";
        }
        $parts[] = 'Earlier decisions are context only; they do not stop a finding being raised.';

        return implode(' ', $parts);
    }
}
