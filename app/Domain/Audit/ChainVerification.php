<?php

namespace App\Domain\Audit;

final class ChainVerification
{
    private function __construct(
        public readonly bool $intact,
        public readonly int $checked,
        public readonly ?string $tipHash = null,
        public readonly ?int $brokenAtSequence = null,
        public readonly ?string $problem = null,
    ) {}

    public static function intact(int $checked, string $tipHash): self
    {
        return new self(true, $checked, $tipHash);
    }

    public static function broken(int $checked, int $sequence, string $problem): self
    {
        return new self(false, $checked, null, $sequence, $problem);
    }
}
