<?php

namespace Tests\Feature;

use hexa_core\Security\Http\OutboundHttpRequest;
use hexa_core\Security\Http\OutboundHttpResponse;
use hexa_core\Security\Http\OutboundUrlGuard;
use hexa_core\Security\Http\SafeOutboundHttpClient;
use hexa_core\Services\CredentialService;
use hexa_package_zerogpt\Services\ZeroGptService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class ZeroGptServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage('hexawebsystems/laravel-hexa-package-zerogpt', ZeroGptService::class);
        Http::preventStrayRequests();
    }

    public function test_connection_uses_bounded_pinned_transport_and_package_credential(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(200, [], '{"success":true}'));

        $result = $service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $requests);
        $this->assertSame('https://api.zerogpt.com/api/detect/detectText', $requests[0]->target->url);
        $this->assertSame('fixture-key', $requests[0]->headers['ApiKey']);
        $this->assertSame(15, $requests[0]->timeoutSeconds);
        $this->assertSame(4 * 1024 * 1024, $requests[0]->maxResponseBytes);
        $this->assertSame('Test connection.', json_decode((string) $requests[0]->body, true, 512, JSON_THROW_ON_ERROR)['input_text']);
        Http::assertNothingSent();
    }

    public function test_provider_failures_do_not_leak_credentials_or_response_details(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(
            500,
            [],
            '{"message":"fixture-key provider detail"}',
        ));

        $result = $service->testConnection();
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);

        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('fixture-key', $serialized);
        $this->assertStringNotContainsString('provider detail', $serialized);
        Http::assertNothingSent();
    }

    public function test_detection_preserves_provider_classification_and_result_shape(): void
    {
        $credentials = $this->mockCredential('fixture-key');
        $requests = [];
        $service = $this->service($credentials, $requests, new OutboundHttpResponse(200, [], json_encode([
            'success' => true,
            'message' => 'Detection complete.',
            'data' => [
                'fakePercentage' => 82.5,
                'aiWords' => 12,
                'textWords' => 20,
                'h' => [['sentence' => 'Fixture sentence.']],
            ],
        ], JSON_THROW_ON_ERROR)));

        $result = $service->detect('Fixture content for a detector request.');

        $this->assertTrue($result['success']);
        $this->assertSame(82.5, $result['data']['fake_percentage']);
        $this->assertSame(17.5, $result['data']['is_human_written']);
        $this->assertSame(12, $result['data']['ai_words']);
        $this->assertSame(30, $requests[0]->timeoutSeconds);
        Http::assertNothingSent();
    }

    public function test_service_source_has_no_raw_http_or_exception_detail_fallback(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Services/ZeroGptService.php');

        $this->assertStringContainsString('SafeOutboundHttpClient', $source);
        $this->assertStringNotContainsString('Facades\\Http', $source);
        $this->assertStringNotContainsString('Http::', $source);
        $this->assertStringNotContainsString('->getMessage()', $source);
        $this->assertStringNotContainsString('->body()', $source);
    }

    public function test_settings_controller_writes_api_keys_only_through_credential_vault(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/ZeroGptController.php');

        $this->assertStringContainsString("credentials->store('zerogpt', 'api_key'", $source);
        $this->assertStringNotContainsString("Setting::setValue('zerogpt_api_key', \$validated", $source);
    }

    /** @param list<OutboundHttpRequest> $requests */
    private function service(
        CredentialService $credentials,
        array &$requests,
        OutboundHttpResponse $response,
    ): ZeroGptService {
        $client = new SafeOutboundHttpClient(
            new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
            static function (OutboundHttpRequest $request) use (&$requests, $response): OutboundHttpResponse {
                $requests[] = $request;

                return $response;
            },
        );

        return new ZeroGptService($client, $credentials);
    }

    private function mockCredential(string $apiKey): CredentialService
    {
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive('get')->once()->with('zerogpt', 'api_key')->andReturn($apiKey);

        return $credentials;
    }
}
