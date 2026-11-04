<?php

namespace App\Domain\Cases;

use App\Models\Analysis\Finding;
use App\Models\Analysis\OfficerOutcome;
use App\Models\Cases\ReviewCase;
use Illuminate\Support\Collection;

/**
 * A summary of work done, not a scoreboard of taxpayers flagged
 * (officer-desk.md section 6). Only final outcomes count: a pending or
 * returned sign-off, or "more information needed", is not yet a conclusion.
 */
class ResultsSummary
{
    /** @return Collection<int, OfficerOutcome> every outcome that has actually concluded */
    private function finalOutcomes(): Collection
    {
        return OfficerOutcome::with('finding')->get()
            ->filter(fn (OfficerOutcome $o) => $o->outcome !== OfficerOutcome::MORE_INFORMATION
                && (! $o->requires_second_signoff || $o->signoff_status === OfficerOutcome::APPROVED));
    }

    /** @return array<string, int> outcome => count */
    public function byOutcome(): array
    {
        $labels = OfficerOutcome::LABELS;

        return $this->finalOutcomes()->countBy('outcome')
            ->sortKeys()->mapWithKeys(fn ($count, $outcome) => [$labels[$outcome] ?? $outcome => $count])->all();
    }

    public function exposureReferred(): int
    {
        return (int) $this->finalOutcomes()->where('outcome', OfficerOutcome::CONCERN_SUPPORTED)->sum('exposure_at_decision');
    }

    /** @return array{verified: int, total: int, medianDays: ?float} */
    public function refundsVerified(): array
    {
        $cases = ReviewCase::where('type', ReviewCase::REFUND_REVIEW)->where('status', ReviewCase::CLOSED)
            ->with('refundClaim')->get()->filter(fn ($c) => $c->refundClaim && $c->closed_at);

        $onTime = $cases->filter(fn ($c) => $c->closed_at->lte($c->refundClaim->verification_due));
        $days = $cases->map(fn ($c) => $c->opened_at->diffInDays($c->closed_at));

        return ['verified' => $onTime->count(), 'total' => $cases->count(), 'medianDays' => $days->isEmpty() ? null : $days->median()];
    }

    /** Overall cycle time; per-stage timing is not tracked yet (see the journey doc's honest limits). */
    public function averageDaysOpenToClose(): ?float
    {
        $closed = ReviewCase::where('status', ReviewCase::CLOSED)->whereNotNull('closed_at')->get();

        return $closed->isEmpty() ? null : round($closed->avg(fn ($c) => $c->opened_at->diffInDays($c->closed_at)), 1);
    }

    /** @return Collection<string, array{concern_supported: int, explained: int}> pattern_code => counts */
    public function byPattern(): Collection
    {
        return $this->finalOutcomes()
            ->whereIn('outcome', [OfficerOutcome::CONCERN_SUPPORTED, OfficerOutcome::EXPLAINED])
            ->groupBy(fn (OfficerOutcome $o) => $o->finding->pattern_code)
            ->map(fn ($group) => [
                'concern_supported' => $group->where('outcome', OfficerOutcome::CONCERN_SUPPORTED)->count(),
                'explained' => $group->where('outcome', OfficerOutcome::EXPLAINED)->count(),
            ])->sortKeys();
    }

    /** @return Collection<int, ReviewCase> closed cases, for the detail list */
    public function closedCases(): Collection
    {
        return ReviewCase::where('status', ReviewCase::CLOSED)
            ->with(['taxpayer', 'findings' => fn ($q) => $q->with('outcome')])
            ->orderByDesc('closed_at')->get();
    }

    public function findingsConcluded(): int
    {
        return Finding::where('status', Finding::CONCLUDED)->count();
    }
}
