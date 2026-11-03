<?php

namespace App\Contracts\AI;

use App\Services\AI\Results\GeneratedContent;

/** Ported from VENTIQ's provider layer (app/Services/AI there): swap models without touching callers. */
interface AIProvider
{
    /**
     * Generate a single response from a prompt.
     *
     * @param  array{think?: ?string, fast?: bool, schema?: ?array, temperature?: ?float}  $options
     *                                                                                               how hard to think (low, medium, high) for a model that reasons first; whether a
     *                                                                                               faster model will do; a JSON schema the response must satisfy; a temperature
     *                                                                                               override for this call. A provider ignores what it can't do.
     */
    public function generate(string $prompt, array $options = []): GeneratedContent;

    /** Check if the provider is available/reachable. */
    public function isAvailable(): bool;

    /** The provider name for logging/display and for recording what generated a brief. */
    public function name(): string;
}
