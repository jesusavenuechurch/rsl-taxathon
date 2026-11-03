<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AIProvider
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('ai.openrouter.api_key');
        $this->baseUrl = config('ai.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->model = config('ai.openrouter.model', 'meta-llama/llama-3.3-70b-instruct:free');
        $this->maxTokens = (int) config('ai.openrouter.max_tokens', 1024);
    }

    public function generate(string $prompt, array $options = []): GeneratedContent
    {
        $start = microtime(true);
        $temperature = (float) ($options['temperature'] ?? config('ai.openrouter.temperature', 0.7));

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/chat/completions", array_filter([
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $temperature,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    // Not every OpenRouter model honours a JSON schema strictly; the
                    // brief's own guard is what actually enforces grounding and wording.
                    'response_format' => isset($options['schema']) ? ['type' => 'json_object'] : null,
                ], fn ($v) => $v !== null));

            if (! $response->successful()) {
                Log::error('OpenRouter request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return GeneratedContent::failure("OpenRouter returned status {$response->status()}", $this->name());
            }

            $content = trim((string) $response->json('choices.0.message.content', ''));

            if ($content === '') {
                return GeneratedContent::failure('Empty response from OpenRouter', $this->name());
            }

            return GeneratedContent::success(content: $content, provider: $this->name(), duration: round(microtime(true) - $start, 2));

        } catch (\Exception $e) {
            Log::error('OpenRouter generate failed', ['error' => $e->getMessage()]);

            return GeneratedContent::failure($e->getMessage(), $this->name());
        }
    }

    public function isAvailable(): bool
    {
        try {
            return Http::withToken($this->apiKey)->timeout(10)->get("{$this->baseUrl}/models")->successful();
        } catch (\Exception $e) {
            Log::error('OpenRouter isAvailable check threw exception', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function name(): string
    {
        return 'openrouter/'.$this->model;
    }
}
