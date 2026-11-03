<?php

namespace App\Domain\Briefs;

use App\Contracts\AI\AIProvider;
use App\Domain\Audit\AuditActor;
use App\Domain\Audit\AuditLog;
use App\Models\Cases\CaseBrief;
use App\Models\Cases\ReviewCase;
use App\Support\Clock;
use Illuminate\Support\Facades\Log;

/**
 * Consolidates a case's findings, rule checks, memory checks and refund
 * letter into one cited brief. The AI drafts; the guard enforces grounding
 * and wording; anything it rejects (or any provider failure) falls back to
 * the deterministic template so the demo never breaks (checkpoint 5).
 */
class CaseBriefGenerator
{
    public function __construct(
        private FactSheetBuilder $facts,
        private BriefPrompt $prompt,
        private BriefGuard $guard,
        private TemplateBriefBuilder $template,
        private AIProvider $provider,
        private Clock $clock,
        private AuditLog $audit,
    ) {}

    public function generate(ReviewCase $case): CaseBrief
    {
        $sheet = $this->facts->build($case);
        $prompt = $this->prompt->build($case, $sheet);

        [$draft, $source, $providerName, $rejectionReason] = $this->draft($case, $prompt, $sheet);

        $brief = CaseBrief::create([
            'case_id' => $case->id,
            'tin' => $case->tin,
            'source' => $source,
            'provider' => $providerName,
            'prompt_hash' => hash('sha256', $prompt),
            'fact_sheet_ids' => $sheet->ids(),
            'draft' => $draft,
            'rejection_reason' => $rejectionReason,
            'status' => CaseBrief::DRAFT,
            'generated_at' => $this->clock->now(),
        ]);
        $brief->update(['reference' => sprintf('BRIEF-%04d', $brief->id)]);

        $this->audit->record('brief_generated', subjectType: 'case_brief', subjectRef: $brief->reference, taxpayerTin: $case->tin,
            actor: AuditActor::system(), payload: ['case' => $case->reference, 'source' => $source, 'provider' => $providerName]);

        return $brief;
    }

    /** @return array{0: array, 1: string, 2: ?string, 3: ?string} */
    private function draft(ReviewCase $case, string $prompt, FactSheet $sheet): array
    {
        $result = $this->provider->generate($prompt, [
            'schema' => $this->prompt->schema(),
            'temperature' => config('ai.brief_temperature', 0.2),
            'think' => 'low',
        ]);

        if (! $result->success) {
            Log::info('Case brief: provider unavailable, using the template', ['error' => $result->error]);

            return [$this->template->build($case), CaseBrief::SOURCE_TEMPLATE, null, "Provider unavailable: {$result->error}"];
        }

        $decoded = json_decode($this->stripFences($result->content), true);

        try {
            $draft = $this->guard->validate($decoded, $sheet);

            return [$draft, CaseBrief::SOURCE_AI, $result->provider, null];
        } catch (BriefRejected $e) {
            Log::warning('Case brief: AI draft rejected, using the template', ['problems' => $e->problems, 'raw' => $result->content]);

            return [$this->template->build($case), CaseBrief::SOURCE_TEMPLATE, $result->provider, implode(' ', $e->problems)];
        }
    }

    private function stripFences(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```[a-z]*\s*/i', '', $content);
            $content = preg_replace('/```\s*$/', '', $content);
        }

        return trim($content);
    }
}
