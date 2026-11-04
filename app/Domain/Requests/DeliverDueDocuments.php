<?php

namespace App\Domain\Requests;

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Audit\AuditActor;
use App\Domain\Audit\AuditLog;
use App\Domain\Notifications\NotificationSender;
use App\Events\ClockMoved;
use App\Models\Cases\DocumentDelivery;
use App\Models\Cases\InformationRequestItem;
use App\Models\Cases\ReleasedDocument;
use App\Models\Cases\ReviewCase;
use App\Support\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Runs on every clock move: documents due since the last check are released,
 * the officer's request item is marked received, and if nothing else is
 * outstanding the case moves to the decision stage. Taxpayers whose
 * documents arrived are re-analysed so document-triggered rules (P09) and
 * any finding ready to move up the evidence ladder can run.
 */
class DeliverDueDocuments
{
    public function __construct(private Clock $clock, private AuditLog $audit, private NotificationSender $notifications) {}

    public function handle(ClockMoved $event): void
    {
        $now = $this->clock->now();
        $due = DocumentDelivery::whereNull('delivered_at')->where('due_at', '<=', $now)
            ->with(['requestItem.request.case', 'document'])->get();

        $affectedTins = [];
        $notifiedCases = [];

        foreach ($due as $delivery) {
            $tin = DB::transaction(fn () => $this->release($delivery, $now));
            if ($tin) {
                $affectedTins[$tin] = true;
            }
            $case = $delivery->requestItem->request->case;
            if (! isset($notifiedCases[$case->id])) {
                $notifiedCases[$case->id] = true;
                $this->notifications->documentsReceived($case);
            }
        }

        if ($affectedTins) {
            app(AnalysisEngine::class)->run(array_keys($affectedTins), 'document_released');
        }
    }

    private function release(DocumentDelivery $delivery, $now): ?string
    {
        $item = $delivery->requestItem;
        $case = $item->request->case;
        $doc = $delivery->document;

        $delivery->update(['delivered_at' => $now]);
        $item->update(['status' => InformationRequestItem::RECEIVED]);

        $released = ReleasedDocument::create([
            'reference' => $doc->document_id,
            'case_id' => $case->id,
            'request_item_id' => $item->id,
            'finding_id' => $item->finding_id,
            'document_type' => $doc->document_type,
            'title' => $doc->title,
            'content_summary' => $doc->content_summary,
            'what_it_shows' => $doc->what_it_shows,
            'received_at' => $now,
        ]);

        $case->events()->create([
            'type' => 'document_received', 'actor_label' => 'System',
            'note' => "{$doc->title} arrived.", 'data' => ['document' => $released->reference], 'occurred_at' => $now,
        ]);
        $this->audit->record('released', subjectType: 'released_document', subjectRef: $released->reference, taxpayerTin: $case->tin,
            actor: AuditActor::system(), payload: ['case' => $case->reference, 'document_type' => $doc->document_type]);

        $stillWaiting = DocumentDelivery::whereNull('delivered_at')
            ->whereHas('requestItem.request', fn ($q) => $q->where('case_id', $case->id))->exists();
        if (! $stillWaiting && $case->stage === ReviewCase::STAGE_WAITING_TAXPAYER) {
            $case->update(['stage' => ReviewCase::STAGE_DECISION]);
        }

        return $case->tin;
    }
}
