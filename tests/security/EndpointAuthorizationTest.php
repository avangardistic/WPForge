<?php

namespace WPForge\Tests\Security;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Dispatches real REST requests against the registered routes to confirm the
 * permission callbacks reject unauthorised callers before any handler runs.
 */
class EndpointAuthorizationTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Force the REST server to initialise so the plugin's routes register.
        rest_get_server();
    }

    /**
     * Endpoints that must never be reachable by a low-privileged user.
     *
     * @return array<string, array{0:string,1:string}>
     */
    public function protectedEndpoints(): array
    {
        return [
            'files list'      => ['GET', '/wpforge/v1/files/list'],
            'file read'       => ['GET', '/wpforge/v1/files/read'],
            'database status' => ['GET', '/wpforge/v1/database/status'],
            'database tables' => ['GET', '/wpforge/v1/database/tables'],
            'site inspect'    => ['GET', '/wpforge/v1/site'],
            'environment'     => ['GET', '/wpforge/v1/environment'],
            'diagnostics'     => ['GET', '/wpforge/v1/diagnostics'],
            'logs'            => ['GET', '/wpforge/v1/logs'],
            'backups'         => ['GET', '/wpforge/v1/backup'],
            'users'           => ['GET', '/wpforge/v1/users'],
            'plugins'         => ['GET', '/wpforge/v1/plugins'],
        ];
    }

    /**
     * @dataProvider protectedEndpoints
     */
    public function testSubscriberIsForbidden(string $method, string $route): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        $response = rest_do_request(new WP_REST_Request($method, $route));
        $this->assertSame(
            403,
            $response->get_status(),
            "{$route} should be forbidden (403) for a subscriber, got {$response->get_status()}"
        );
    }

    /**
     * @dataProvider protectedEndpoints
     */
    public function testAnonymousIsUnauthorized(string $method, string $route): void
    {
        wp_set_current_user(0);
        $response = rest_do_request(new WP_REST_Request($method, $route));
        $this->assertSame(
            401,
            $response->get_status(),
            "{$route} should be unauthorized (401) for an anonymous request, got {$response->get_status()}"
        );
    }

    public function testAdministratorReachesTheHandler(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        $response = rest_do_request(new WP_REST_Request('GET', '/wpforge/v1/database/status'));
        // The handler ran: a 200, not a permission rejection.
        $this->assertSame(200, $response->get_status());
    }

    public function testQuickDiagnosticsStaysPublic(): void
    {
        wp_set_current_user(0);
        $response = rest_do_request(new WP_REST_Request('GET', '/wpforge/v1/diagnostics/quick'));
        $this->assertSame(200, $response->get_status());
    }
}
