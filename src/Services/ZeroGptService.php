<?php

namespace hexa_package_zerogpt\Services;

use hexa_core\AI\Contracts\AiTransactionRecorder;
use hexa_core\Models\Setting;
use hexa_core\Security\Http\OutboundHttpException;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI-content detection through ZeroGPT's text detection endpoint.
 */
class ZeroGptService
{
    private const DEFAULT_ENDPOINT = 'https://api.zerogpt.com/api/detect/detectText';

    private const CONNECTION_TIMEOUT_SECONDS = 15;

    private const DETECTION_TIMEOUT_SECONDS = 30;

    private const MAX_RESPONSE_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly SafeOutboundHttpClient $http,
        private readonly CredentialService $credentials,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('zerogpt_enabled', config('zerogpt.enabled', true));
    }

    public function isDebugMode(): bool
    {
        return (bool) Setting::getValue('zerogpt_debug_mode', false);
    }

    public function getApiKey(): ?string
    {
        return $this->credentials->get('zerogpt', 'api_key')
            ?: Setting::getValue('zerogpt_api_key');
    }

    /** @return array{success: bool, message: string, data?: array} */
    public function detect(string $text): array
    {
        $apiKey = $this->validApiKey($this->getApiKey());
        if ($apiKey === null) {
            return ['success' => false, 'message' => 'ZeroGPT API key not configured.'];
        }

        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'ZeroGPT is disabled.'];
        }

        $text = $this->debugText($text);

        try {
            $response = $this->requestDetection($apiKey, $text, self::DETECTION_TIMEOUT_SECONDS, 'detector.scan');
        } catch (Throwable $exception) {
            $this->logTransportFailure('scan', $exception);

            return ['success' => false, 'message' => 'ZeroGPT request failed safely.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => "ZeroGPT API error ({$response->status})."];
        }

        $data = $this->jsonObject($response);
        $resultData = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $fakePercent = $resultData['fakePercentage'] ?? null;
        $aiSentences = $resultData['h'] ?? [];

        return [
            'success' => (bool) ($data['success'] ?? false),
            'message' => is_string($data['message'] ?? null) ? $data['message'] : 'Detection complete.',
            'data' => [
                'is_human_written' => $fakePercent !== null ? round(100 - (float) $fakePercent, 1) : null,
                'fake_percentage' => $fakePercent,
                'ai_words' => $resultData['aiWords'] ?? null,
                'text_words' => $resultData['textWords'] ?? null,
                'sentences' => is_array($aiSentences) ? $aiSentences : [],
                'raw' => $data,
            ],
        ];
    }

    /** @return array{success: bool, message: string} */
    public function testConnection(): array
    {
        $apiKey = $this->validApiKey($this->getApiKey());
        if ($apiKey === null) {
            return ['success' => false, 'message' => 'ZeroGPT API key not configured.'];
        }

        try {
            $response = $this->requestDetection(
                $apiKey,
                'Test connection.',
                self::CONNECTION_TIMEOUT_SECONDS,
                'detector.connection_test',
            );
        } catch (Throwable $exception) {
            $this->logTransportFailure('connection_test', $exception);

            return ['success' => false, 'message' => 'ZeroGPT connection failed safely.'];
        }

        if ($response->successful()) {
            return ['success' => true, 'message' => 'ZeroGPT API connected successfully.'];
        }

        return ['success' => false, 'message' => "ZeroGPT API error ({$response->status})."];
    }

    private function requestDetection(string $apiKey, string $text, int $timeout, string $operation): OutboundHttpResponse
    {
        $endpoint = (string) config('zerogpt.api_url', self::DEFAULT_ENDPOINT);
        $units = ['characters' => mb_strlen($text), 'words' => str_word_count($text)];
        $span = app(AiTransactionRecorder::class)->start([
            'provider' => 'zerogpt',
            'package' => 'hexawebsystems/laravel-hexa-package-zerogpt',
            'model' => 'zerogpt-ai-detector',
            'operation' => $operation,
            'endpoint' => parse_url($endpoint, PHP_URL_PATH) ?: '/api/detect/detectText',
            'request_metadata' => array_merge($units, ['timeout_seconds' => $timeout]),
        ]);

        try {
            $response = $this->http->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                    'ApiKey' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['input_text' => $text], JSON_THROW_ON_ERROR),
                'timeout' => $timeout,
                'max_bytes' => self::MAX_RESPONSE_BYTES,
                'max_redirects' => 0,
            ]);
            $payload = $this->jsonObject($response);
            $resultData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $usage = array_merge($units, [
                'ai_words' => is_numeric($resultData['aiWords'] ?? null) ? (int) $resultData['aiWords'] : null,
                'text_words' => is_numeric($resultData['textWords'] ?? null) ? (int) $resultData['textWords'] : null,
            ]);
            $attributes = [
                'provider_request_id' => $response->headerValues('x-request-id')[0] ?? null,
                'http_status' => $response->status,
                'usage' => $usage,
                'response_metadata' => [
                    'fake_percentage' => $resultData['fakePercentage'] ?? null,
                    'flagged_sentence_count' => count(is_array($resultData['h'] ?? null) ? $resultData['h'] : []),
                ],
            ];
            $providerSucceeded = $response->successful() && (bool) ($payload['success'] ?? true);

            if ($providerSucceeded) {
                $span->succeed($attributes);
            } else {
                $span->fail('ZeroGPT request failed.', array_merge($attributes, [
                    'error_type' => 'zerogpt_provider_error',
                ]));
            }

            return $response;
        } catch (Throwable $exception) {
            $span->fail(
                $exception instanceof OutboundHttpException ? $exception : 'ZeroGPT request failed.',
                ['usage' => $units],
            );

            throw $exception;
        }
    }

    private function debugText(string $text): string
    {
        if (! $this->isDebugMode()) {
            return $text;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $text, 4) ?: [];

        return implode(' ', array_slice($sentences, 0, 3));
    }

    /** @return array<string, mixed> */
    private function jsonObject(OutboundHttpResponse $response): array
    {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    private function validApiKey(?string $apiKey): ?string
    {
        $apiKey = trim((string) $apiKey);

        return $apiKey !== ''
            && strlen($apiKey) <= 4096
            && preg_match('/[\x00-\x1f\x7f]/', $apiKey) !== 1
                ? $apiKey
                : null;
    }

    private function logTransportFailure(string $operation, Throwable $exception): void
    {
        Log::warning('ZeroGPT request failed safely', [
            'operation' => $operation,
            'failure_code' => $exception instanceof OutboundHttpException
                ? $exception->failureCode()
                : 'request_failed',
        ]);
    }
}
