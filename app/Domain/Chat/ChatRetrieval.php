<?php

namespace App\Domain\Chat;

use App\Domain\Briefs\FactSheet;

/** What the system looked up for a question: the fact sheet the AI may use, and the plain answer the same records give directly. */
final class ChatRetrieval
{
    /** @param list<array{text: string, citations: list<string>}> $lookup empty when the question matched no direct lookup */
    public function __construct(
        public readonly FactSheet $sheet,
        public readonly array $lookup,
        public readonly ?string $note = null,
    ) {}
}
