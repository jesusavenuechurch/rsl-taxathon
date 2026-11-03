<?php

return [

    /*
    | Provider layer ported from VENTIQ (app/Services/AI there). RSL's only
    | use today is the AI case brief (checkpoint 5): the AI drafts, grounded
    | in a fact sheet of record IDs the system already looked up; the officer
    | approves, edits or rejects. Chosen by AI_PROVIDER so the demo can run
    | entirely on Ollama inside RSL, with OpenRouter as a cloud fallback.
    */
    'default' => env('AI_PROVIDER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'gpt-oss:20b'),
        'embed_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'timeout' => env('OLLAMA_TIMEOUT', 300),
        'temperature' => env('OLLAMA_TEMPERATURE', 0.7),
        'keep_alive' => env('OLLAMA_KEEP_ALIVE', '24h'),
        // Whether the model takes a thinking level (low/medium/high). Unset: gpt-oss does.
        'thinking' => env('OLLAMA_THINKING'),
        // How much prompt it may take; Ollama's default is small and drops the start of a longer one.
        'num_ctx' => env('OLLAMA_NUM_CTX', 16384),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-70b-instruct:free'),
        'max_tokens' => env('OPENROUTER_MAX_TOKENS', 1024),
        'temperature' => env('OPENROUTER_TEMPERATURE', 0.7),
    ],

    /*
    | The case brief asks for strict JSON (statements with citations) and
    | reads better from a low temperature than the provider's general default.
    */
    'brief_temperature' => env('AI_BRIEF_TEMPERATURE', 0.2),

];
