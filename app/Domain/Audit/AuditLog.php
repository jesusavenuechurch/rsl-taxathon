<?php

namespace App\Domain\Audit;

use App\Models\AuditEvent;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes and checks the tamper-evident audit log: who received, analysed,
 * opened, requested and concluded what, and when.
 */
class AuditLog
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private Clock $clock) {}

    /**
     * @param  string  $action  past-tense verb in snake_case, e.g. received, analysed, opened, requested, concluded
     * @param  array<string, mixed>  $payload  details; keep taxpayer data to what the event is about
     */
    public function record(
        string $action,
        ?string $subjectType = null,
        ?string $subjectRef = null,
        array $payload = [],
        ?string $taxpayerTin = null,
        ?AuditActor $actor = null,
    ): AuditEvent {
        $actor ??= AuditActor::current();
        $payload = AuditHasher::canonicalPayload($payload);

        $fields = [
            'occurred_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'recorded_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_label' => $actor->label,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_ref' => $subjectRef,
            'taxpayer_tin' => $taxpayerTin,
            'payload' => $payload,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ];

        // Two writers can race for the same sequence number; the unique
        // index rejects the loser, which simply tries again on the new tip.
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->append($fields));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    private function append(array $fields): AuditEvent
    {
        $tip = AuditEvent::query()->orderByDesc('sequence')->lockForUpdate()->first(['sequence', 'hash']);

        $fields['sequence'] = ($tip?->sequence ?? 0) + 1;
        $fields['prev_hash'] = $tip?->hash ?? AuditHasher::GENESIS;
        $fields['hash'] = AuditHasher::hash($fields);
        $fields['payload'] = AuditHasher::encode($fields['payload']);

        // Insert the raw row so the stored values are exactly the hashed ones.
        $id = DB::table('audit_events')->insertGetId($fields);

        return AuditEvent::findOrFail($id);
    }

    /**
     * Walk the whole chain: numbering has no gaps, each row points at the one
     * before it, and each row's hash still matches its contents.
     */
    public function verify(): ChainVerification
    {
        $expectedSequence = 1;
        $prevHash = AuditHasher::GENESIS;

        foreach (DB::table('audit_events')->lazyById(500, 'id') as $row) {
            $fields = (array) $row;

            $problem = match (true) {
                (int) $row->sequence !== $expectedSequence => "expected sequence {$expectedSequence}, found {$row->sequence} (a row is missing)",
                $row->prev_hash !== $prevHash => 'does not point at the previous entry',
                AuditHasher::hash($fields) !== $row->hash => 'contents no longer match its hash (edited)',
                default => null,
            };

            if ($problem !== null) {
                return ChainVerification::broken($expectedSequence - 1, (int) $row->sequence, $problem);
            }

            $prevHash = $row->hash;
            $expectedSequence++;
        }

        return ChainVerification::intact($expectedSequence - 1, $prevHash);
    }
}
