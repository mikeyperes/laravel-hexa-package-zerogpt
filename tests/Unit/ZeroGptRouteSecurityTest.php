<?php

namespace hexa_package_zerogpt\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ZeroGptRouteSecurityTest extends TestCase
{
    public function test_authenticated_routes_require_two_factor_verification(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/zerogpt.php');

        self::assertStringContainsString(
            "Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])",
            $routes,
        );
    }
}
