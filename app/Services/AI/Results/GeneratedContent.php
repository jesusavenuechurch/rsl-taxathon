<?php

namespace App\Services\AI\Results;

class GeneratedContent
{
    public function __construct(
        public readonly string $content,
        public readonly bool $success,
        public readonly string $provider,
        public readonly float $duration,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $content, string $provider, float $duration): self
    {
        return new self(content: $content, success: true, provider: $provider, duration: $duration);
    }

    public static function failure(string $error, string $provider): self
    {
        return new self(content: '', success: false, provider: $provider, duration: 0, error: $error);
    }
}
