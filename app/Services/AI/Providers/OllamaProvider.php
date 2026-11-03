<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AIProvider;
use App\Services\AI\Results\GeneratedContent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * gpt-oss is a reasoning model: we call /api/chat (not /api/generate) so
 * Ollama separates the reasoning trace into message.thinking and leaves the
 * answer alone in message.content. We only ever read message.content; the
 * thinking is never shown to an officer or stored as part of a brief.
 */
class OllamaProvider implements AIProvider
{
    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('ai.ollama.url', 'http://localhost:11434');
        $this->model = config('ai.ollama.model', 'gpt-oss:20b');
        $this->timeout = config('ai.ollama.timeout', 300);
    }

    public function generate(string $prompt, array $options = []): GeneratedContent
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($this->timeout)->connectTimeout(10)
                ->post("{$this->baseUrl}/api/chat", array_filter([
                    'model' => $this->model,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'stream' => false,
                    'format' => $options['schema'] ?? null,
                    // Kept loaded between calls: reloading it makes the first brief of a session wait.
                    'keep_alive' => config('ai.ollama.keep_alive') ?: null,
                    // Only to a model that reasons first; others refuse it.
                    'think' => ! empty($options['think']) && self::thinks($this->model) ? $options['think'] : null,
                    'options' => array_filter([
                        'temperature' => (float) ($options['temperature'] ?? config('ai.ollama.temperature', 0.7)),
                        'top_p' => 0.9,
                        'num_ctx' => config('ai.ollama.num_ctx') ? (int) config('ai.ollama.num_ctx') : null,
                    ], fn ($v) => $v !== null),
                ], fn ($v) => $v !== null));

            if (! $response->successful()) {
                return GeneratedContent::failure("Ollama returned status {$response->status()}", $this->name());
            }

            $content = trim((string) $response->json('message.content', ''));

            if ($content === '') {
                return GeneratedContent::failure('Empty response from Ollama', $this->name());
            }

            return GeneratedContent::success(content: $content, provider: $this->name(), duration: round(microtime(true) - $start, 2));

        } catch (ConnectionException $e) {
            // Off, asleep or off the tailnet: the one case that's "unavailable".
            Log::warning('Ollama not reachable', ['url' => $this->baseUrl, 'error' => $e->getMessage()]);

            return GeneratedContent::failure('The AI model is temporarily unavailable.', $this->name());
        } catch (\Exception $e) {
            Log::error('Ollama generate failed', ['error' => $e->getMessage()]);

            return GeneratedContent::failure($e->getMessage(), $this->name());
        }
    }

    /** A model that thinks before answering, and takes a level for it: gpt-oss, unless OLLAMA_THINKING says otherwise. */
    public static function thinks(string $model): bool
    {
        $set = config('ai.ollama.thinking');

        return $set === null || $set === '' ? str_starts_with($model, 'gpt-oss') : filter_var($set, FILTER_VALIDATE_BOOLEAN);
    }

    public function isAvailable(): bool
    {
        try {
            return Http::timeout(10)->get("{$this->baseUrl}/api/tags")->successful();
        } catch (\Exception) {
            return false;
        }
    }

    public function name(): string
    {
        return 'ollama/'.$this->model;
    }
}
