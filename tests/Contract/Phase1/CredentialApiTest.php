<?php

namespace Tests\Contract\Phase1;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('phase-1')]
#[Group('credentials')]
final class CredentialApiTest extends TestCase
{
    public function test_credential_operations_are_routed_with_exact_permissions(): void
    {
        $expected = [
            ['api/v1/tenant/events/{event_id}/credentials/{credential_id}/revoke', 'permission:credential.revoke,tenant'],
            ['api/v1/tenant/events/{event_id}/credentials/{credential_id}/reissue', 'permission:credential.reissue,tenant'],
            ['api/v1/tenant/credential-validations', 'permission:credential.validate,tenant'],
        ];
        foreach ($expected as [$uri, $permission]) {
            $route = collect(app('router')->getRoutes()->getRoutes())->first(
                fn (Route $route): bool => $route->uri() === $uri && in_array('POST', $route->methods(), true),
            );
            self::assertNotNull($route);
            self::assertContains($permission, $route->gatherMiddleware());
        }
    }
}
