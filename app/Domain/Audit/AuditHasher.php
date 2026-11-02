<?php

namespace App\Domain\Audit;

/**
 * Canonical form of an audit event, so the same event always hashes the same
 * way whether it is being written or re-checked later.
 */
final class AuditHasher
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const JSON = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /** @param array<string, mixed> $fields every column except id and hash */
    public static function hash(array $fields): string
    {
        $canonical = [
            'sequence' => (int) $fields['sequence'],
            'occurred_at' => (string) $fields['occurred_at'],
            'recorded_at' => (string) $fields['recorded_at'],
            'actor_type' => $fields['actor_type'],
            'actor_id' => $fields['actor_id'] === null ? null : (int) $fields['actor_id'],
            'actor_label' => $fields['actor_label'],
            'action' => $fields['action'],
            'subject_type' => $fields['subject_type'],
            'subject_ref' => $fields['subject_ref'],
            'taxpayer_tin' => $fields['taxpayer_tin'],
            'payload' => self::canonicalPayload($fields['payload']),
            'ip' => $fields['ip'],
            'prev_hash' => $fields['prev_hash'],
        ];

        return hash('sha256', json_encode($canonical, self::JSON));
    }

    /** Payload as plain JSON data with object keys sorted at every level. */
    public static function canonicalPayload(array|string $payload): mixed
    {
        $data = is_string($payload)
            ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR)
            : json_decode(json_encode($payload, self::JSON), true, 512, JSON_THROW_ON_ERROR);

        return self::sortKeys($data);
    }

    public static function encode(mixed $payload): string
    {
        return json_encode($payload, self::JSON);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
