<?php

namespace Tests\Feature;

use hexa_core\Services\CredentialService;
use hexa_package_zerogpt\Services\ZeroGptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ZeroGptServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireInstalledPackage("hexawebsystems/laravel-hexa-package-zerogpt", ZeroGptService::class);
    }

    public function test_connection_uses_package_credential_and_provider_endpoint(): void
    {
        $credentials = Mockery::mock(CredentialService::class);
        $credentials->shouldReceive("get")->once()->with("zerogpt", "api_key")->andReturn("test-key");
        $this->app->instance(CredentialService::class, $credentials);

        Http::fake(["*api.zerogpt.com/*" => Http::response(["success" => true], 200)]);

        $result = app(ZeroGptService::class)->testConnection();

        $this->assertTrue($result["success"]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "api.zerogpt.com/api/detect/detectText") && $request->hasHeader("ApiKey", "test-key"));
    }
}
