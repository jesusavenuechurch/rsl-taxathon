<?php

namespace App\Domain\Chat;

/** A cited answer. `source`: ai (model wording that passed the guard), lookup (direct record lookup), none (nothing found). */
final class ChatAnswer
{
    public const AI = 'ai';

    public const LOOKUP = 'lookup';

    public const NONE = 'none';

    /** @param list<array{text: string, citations: list<string>}> $statements */
    public function __construct(
        public readonly array $statements,
        public readonly string $source,
        public readonly ?string $provider = null,
        public readonly ?string $note = null,
        public readonly ?string $rejectionReason = null,
        /** @var list<string> */
        public readonly array $factSheetIds = [],
    ) {}

    public function toArray(): array
    {
        return ['statements' => $this->statements, 'note' => $this->note];
    }
}
