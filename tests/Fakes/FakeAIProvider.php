<?php

namespace Tests\Fakes;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Results\GeneratedContent;
use Closure;

/** A controllable AIProvider for tests: no network, no delay, no real model. */
class FakeAIProvider implements AIProvider
{
    private function __construct(private Closure $responder) {}

    /** Succeeds with this raw content (JSON or otherwise malformed, for guard tests). */
    public static function returning(string $content, string $name = 'fake/test-model'): self
    {
        return new self(fn () => GeneratedContent::success($content, $name, 0.01));
    }

    /** Fails, as a real provider does when it cannot be reached. */
    public static function unavailable(string $error = 'Not used in this test.'): self
    {
        return new self(fn () => GeneratedContent::failure($error, 'fake/test-model'));
    }

    public function generate(string $prompt, array $options = []): GeneratedContent
    {
        return ($this->responder)($prompt, $options);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function name(): string
    {
        return 'fake/test-model';
    }
}
