<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta WhatsApp Cloud API (ported from VENTIQ's WhatsAppCloudService). Every
 * send here is business-initiated, so it always goes through a pre-approved
 * template — never freeform text.
 */
class WhatsAppCloudService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            config('services.whatsapp.phone_number_id'),
        );
    }

    public function isConfigured(): bool
    {
        return (bool) (config('services.whatsapp.phone_number_id') && config('services.whatsapp.access_token'));
    }

    /**
     * @param  list<string>  $bodyParams  plain strings, in order, matching the template's {{1}}, {{2}}, ...
     * @return array{success: bool, message_id: ?string, error: ?string}
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $bodyParams,
        ?string $buttonUrlSuffix = null,
        string $languageCode = 'en_US',
    ): array {
        if (! $this->isConfigured()) {
            Log::info("WhatsApp not configured, '{$templateName}' skipped", ['to' => $to]);

            return ['success' => false, 'message_id' => null, 'error' => 'WhatsApp is not configured.'];
        }

        $components = [];
        if ($bodyParams) {
            $components[] = [
                'type' => 'body',
                // Meta rejects a send outright if any {{n}} parameter is empty.
                'parameters' => array_map(fn ($text) => ['type' => 'text', 'text' => trim((string) $text) !== '' ? (string) $text : '—'], $bodyParams),
            ];
        }
        if ($buttonUrlSuffix) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $buttonUrlSuffix]]];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->normalizeNumber($to),
            'type' => 'template',
            'template' => ['name' => $templateName, 'language' => ['code' => $languageCode], 'components' => $components],
        ];

        try {
            $response = Http::withToken(config('services.whatsapp.access_token'))->acceptJson()->timeout(20)->post($this->baseUrl, $payload);
        } catch (\Exception $e) {
            Log::error("WhatsApp template '{$templateName}' failed to send", ['to' => $to, 'error' => $e->getMessage()]);

            return ['success' => false, 'message_id' => null, 'error' => $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if ($response->successful()) {
            Log::info("WhatsApp template '{$templateName}' sent", ['to' => $to, 'response' => $body]);

            return ['success' => true, 'message_id' => $body['messages'][0]['id'] ?? null, 'error' => null];
        }

        $error = $body['error']['message'] ?? "HTTP {$response->status()}";
        Log::error("WhatsApp template '{$templateName}' rejected", ['to' => $to, 'status' => $response->status(), 'response' => $body]);

        return ['success' => false, 'message_id' => null, 'error' => $error];
    }

    /** Meta wants E.164 without the leading "+" (e.g. "26658123456"). */
    private function normalizeNumber(string $number): string
    {
        return ltrim(preg_replace('/\D/', '', $number), '0') ?: $number;
    }
}
