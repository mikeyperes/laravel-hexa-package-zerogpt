<?php

namespace hexa_package_zerogpt\Services;

use hexa_core\Models\Setting;
use hexa_core\Services\GenericService;
use Illuminate\Support\Facades\Http;

/**
 * ZeroGptService — AI content detection via ZeroGPT API.
 *
 * Detects AI-generated text with per-sentence probability scoring.
 * Free tier: 10,000 words/month. API: api.zerogpt.me
 */
class ZeroGptService
{
    protected GenericService $generic;

    /**
     * @param GenericService $generic
     */
    public function __construct(GenericService $generic)
    {
        $this->generic = $generic;
    }

    /**
     * Check if ZeroGPT is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return Setting::getValue('zerogpt_enabled', config('zerogpt.enabled', true));
    }

    /**
     * Check if debug mode is on (sends only first 3 sentences).
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (bool) Setting::getValue('zerogpt_debug_mode', false);
    }

    /**
     * Get the API key (from CredentialService or legacy Setting).
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        if (class_exists(\hexa_core\Services\CredentialService::class)) {
            $cred = app(\hexa_core\Services\CredentialService::class);
            $val = $cred->get('zerogpt', 'api_key');
            if ($val) return $val;
        }
        return Setting::getValue('zerogpt_api_key');
    }

    /**
     * Detect AI-generated content.
     *
     * @param string $text Plain text to analyze
     * @return array{success: bool, message: string, data?: array}
     */
    public function detect(string $text): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'ZeroGPT API key not configured.'];
        }

        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'ZeroGPT is disabled.'];
        }

        // Debug mode: only send first 3 sentences
        if ($this->isDebugMode()) {
            $sentences = preg_split('/(?<=[.!?])\s+/', $text, 4);
            $text = implode(' ', array_slice($sentences, 0, 3));
        }

        try {
            $response = Http::withHeaders([
                'ApiKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(config('zerogpt.api_url', 'https://api.zerogpt.com/api/detect/detectText'), [
                'input_text' => $text,
            ]);

            if (!$response->successful()) {
                $error = $response->json('message') ?? $response->body();
                return ['success' => false, 'message' => 'ZeroGPT API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error))];
            }

            $data = $response->json();
            $resultData = $data['data'] ?? $data;
            $fakePercent = $resultData['fakePercentage'] ?? null;
            $aiSentences = $resultData['h'] ?? [];

            return [
                'success' => (bool) ($data['success'] ?? false),
                'message' => $data['message'] ?? 'Detection complete.',
                'data' => [
                    'is_human_written' => $fakePercent !== null ? round(100 - (float) $fakePercent, 1) : null,
                    'fake_percentage' => $fakePercent,
                    'ai_words' => $resultData['aiWords'] ?? null,
                    'text_words' => $resultData['textWords'] ?? null,
                    'sentences' => is_array($aiSentences) ? $aiSentences : [],
                    'raw' => $data,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'ZeroGPT request failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test the API connection by verifying the API key is accepted.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'ZeroGPT API key not configured.'];
        }

        try {
            // Send minimal text to verify the API key works
            $response = Http::withHeaders([
                'ApiKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post(config('zerogpt.api_url', 'https://api.zerogpt.com/api/detect/detectText'), [
                'input_text' => 'Test connection.',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $balance = $data['data']['creditBalance'] ?? $data['data']['balance'] ?? null;
                $msg = 'ZeroGPT API connected successfully.';
                if ($balance !== null) {
                    $msg .= ' Balance: ' . $balance . ' credits.';
                }
                return ['success' => true, 'message' => $msg];
            }

            $error = $response->json('message') ?? $response->body();
            return ['success' => false, 'message' => 'ZeroGPT API error (' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error))];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'ZeroGPT connection failed: ' . $e->getMessage()];
        }
    }
}
