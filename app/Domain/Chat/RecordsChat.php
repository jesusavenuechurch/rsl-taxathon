<?php

namespace App\Domain\Chat;

use App\Contracts\AI\AIProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Briefs\BriefRejected;
use App\Models\Cases\ReviewCase;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Support\Facades\Log;

/**
 * "Ask the records": a way into the same records the case pages use, never a
 * separate source (handoff rule 12). The question is looked up first; the
 * model may only word an answer from those records, and a guard checks every
 * citation, figure and word. If the model is unreachable or fails the guard,
 * the answer comes straight from the lookup, so the chat always works.
 */
class RecordsChat
{
    public const NOTHING_FOUND = 'The records give me nothing to answer that with. Try a company by name, related-party payments, recurring VAT credits, low-tax jurisdictions or an earlier decision.';

    public function __construct(
        private ChatRetriever $retriever,
        private ChatPrompt $prompt,
        private ChatGuard $guard,
        private AIProvider $provider,
        private Clock $clock,
        private AuditLog $audit,
    ) {}

    public function ask(string $question, ?User $user = null, ?ReviewCase $case = null): ChatAnswer
    {
        $question = trim($question);
        $retrieval = $this->retriever->retrieve($question, $case);
        $answer = $this->answer($question, $retrieval);

        ChatMessage::create([
            'user_id' => $user?->id,
            'case_id' => $case?->id,
            'question' => $question,
            'source' => $answer->source,
            'provider' => $answer->provider,
            'answer' => $answer->toArray(),
            'fact_sheet_ids' => $answer->factSheetIds,
            'rejection_reason' => $answer->rejectionReason,
            'asked_at' => $this->clock->now(),
        ]);

        $this->audit->record('chat_asked', subjectType: 'chat', subjectRef: $case?->reference, taxpayerTin: $case?->tin,
            payload: ['question' => $question, 'source' => $answer->source, 'provider' => $answer->provider]);

        return $answer;
    }

    private function answer(string $question, ChatRetrieval $retrieval): ChatAnswer
    {
        $ids = $retrieval->sheet->ids();
        $fallback = fn (?string $why) => $retrieval->lookup === []
            ? new ChatAnswer([], ChatAnswer::NONE, rejectionReason: $why, factSheetIds: $ids)
            : new ChatAnswer($retrieval->lookup, ChatAnswer::LOOKUP, note: $retrieval->note, rejectionReason: $why, factSheetIds: $ids);

        if ($ids === []) {
            return $fallback(null);
        }

        $result = $this->provider->generate($this->prompt->build($question, $retrieval->sheet), [
            'schema' => $this->prompt->schema(),
            'temperature' => config('ai.brief_temperature', 0.2),
            'think' => 'low',
        ]);

        if (! $result->success) {
            Log::info('Ask the records: provider unavailable, answering from the lookup', ['error' => $result->error]);

            return $fallback("Provider unavailable: {$result->error}");
        }

        try {
            $statements = $this->guard->validate(json_decode($this->stripFences($result->content), true), $retrieval->sheet);

            return new ChatAnswer($statements, ChatAnswer::AI, $result->provider, $retrieval->note, factSheetIds: $ids);
        } catch (BriefRejected $e) {
            Log::warning('Ask the records: AI answer rejected, answering from the lookup', ['problems' => $e->problems, 'raw' => $result->content]);

            return $fallback(implode(' ', $e->problems));
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
