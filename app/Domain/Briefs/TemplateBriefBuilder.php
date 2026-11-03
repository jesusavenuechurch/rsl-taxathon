<?php

namespace App\Domain\Briefs;

use App\Models\Cases\ReviewCase;

/**
 * The deterministic fallback: built straight from the same findings, memory
 * and refund letter as the AI prompt, with no model call. Used whenever the
 * provider is unavailable or its draft fails the guard, so the demo never
 * shows a blank or broken brief.
 */
final class TemplateBriefBuilder
{
    public function build(ReviewCase $case): array
    {
        $case->loadMissing(['findings', 'refundClaim', 'taxpayer']);

        $statements = $case->findings->map(fn ($f) => [
            'text' => "{$f->pattern_code}: {$f->wording}",
            'citations' => [$f->reference],
        ])->values()->all();

        if ($case->refundClaim) {
            $statements[] = [
                'text' => "A refund letter is on file ({$case->refundClaim->record_id}), in the {$case->laneLabel()} lane.",
                'citations' => [$case->refundClaim->record_id],
            ];
        }

        if ($statements === [] && $case->taxpayer) {
            $statements[] = [
                'text' => "This case has no findings currently open on {$case->taxpayer->name}.",
                'citations' => [$case->taxpayer->record_id],
            ];
        }

        $openRefs = $case->findings->where('status', 'open')->pluck('reference')->all();
        $fallbackRefs = array_values(array_filter([
            ...$case->findings->pluck('reference')->all(),
            $case->refundClaim?->record_id,
            $case->taxpayer?->record_id,
        ]));

        $nextStep = $openRefs !== []
            ? ['text' => 'Consider working through the open findings above and recording one of the four officer outcomes for each.', 'citations' => $openRefs]
            : ['text' => 'Consider reviewing this case\'s history; no findings are currently open.', 'citations' => $fallbackRefs];

        return ['statements' => $statements, 'next_step' => $nextStep];
    }
}
