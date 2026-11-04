<?php

namespace App\Domain\Requests;

use App\Domain\Audit\AuditLog;
use App\Domain\Cases\CaseWorkflow;
use App\Models\Analysis\Finding;
use App\Models\Cases\DocumentDelivery;
use App\Models\Cases\InformationRequest;
use App\Models\Cases\ReviewCase;
use App\Models\Cases\SimulatedTaxpayerDocument;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A document is invisible until an officer's request names its document_type
 * (handoff rule 3). The composer prefills from open findings' documents to
 * request; the officer edits before sending. Sending schedules delivery
 * against whatever the taxpayer actually has on file for that type — asking
 * for something the pool has nothing for just gets no delivery, honestly.
 */
class RequestWorkflow
{
    public function __construct(private CaseWorkflow $workflow, private Clock $clock, private AuditLog $audit) {}

    /** @return list<array{document_type: string, finding_id: ?int, reason: string}> prefilled, deduplicated by document_type */
    public function prefill(ReviewCase $case): array
    {
        $items = [];
        foreach ($case->findings()->open()->with('ruleVersion')->get() as $finding) {
            foreach ($finding->ruleVersion->documents_to_request ?? [] as $docType) {
                $docType = trim($docType);
                if ($docType === '' || isset($items[$docType])) {
                    continue;
                }
                $items[$docType] = ['document_type' => $docType, 'finding_id' => $finding->id, 'reason' => "For {$finding->pattern_code} {$finding->title}"];
            }
        }

        return array_values($items);
    }

    /** @param  list<array{document_type: string, finding_id: ?int}>  $items */
    public function send(User $user, ReviewCase $case, array $items, string $message): InformationRequest
    {
        if (! $this->workflow->canAct($user, $case)) {
            throw new AuthorizationException('Only the team holding the case (or a team lead) can send an information request.');
        }
        $items = array_values(array_filter($items, fn ($i) => trim($i['document_type'] ?? '') !== ''));
        if ($items === []) {
            throw new InvalidArgumentException('Name at least one document to request.');
        }

        return DB::transaction(function () use ($user, $case, $items, $message) {
            $request = InformationRequest::create([
                'case_id' => $case->id,
                'composed_by' => $user->id,
                'status' => InformationRequest::SENT,
                'message' => trim($message) ?: null,
                'sent_at' => $this->clock->now(),
            ]);
            $request->update(['reference' => sprintf('IR-%04d', $request->id)]);

            $scheduled = [];
            foreach ($items as $item) {
                $docType = trim($item['document_type']);
                $requestItem = $request->items()->create([
                    'document_type' => $docType,
                    'finding_id' => Finding::where('id', $item['finding_id'] ?? null)->where('case_id', $case->id)->value('id'),
                ]);

                $pool = SimulatedTaxpayerDocument::where('tin', $case->tin)->where('document_type', $docType)->first();
                if ($pool) {
                    DocumentDelivery::create([
                        'request_item_id' => $requestItem->id,
                        'simulated_document_id' => $pool->id,
                        'due_at' => $this->clock->now()->addDays($pool->responds_after_days),
                    ]);
                    $scheduled[] = $docType;
                }
            }

            $case->update(['stage' => ReviewCase::STAGE_WAITING_TAXPAYER]);
            $case->events()->create([
                'type' => 'information_requested', 'actor_id' => $user->id, 'actor_label' => $user->name,
                'note' => trim($message) ?: 'Documents requested: '.implode(', ', array_column($items, 'document_type')),
                'occurred_at' => $this->clock->now(),
            ]);
            $this->audit->record('information_requested', subjectType: 'information_request', subjectRef: $request->reference, taxpayerTin: $case->tin,
                payload: ['case' => $case->reference, 'requested' => array_column($items, 'document_type'), 'scheduled' => $scheduled]);

            return $request;
        });
    }
}
