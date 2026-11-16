<?php

namespace WPForge\Tests\Security;

use WPForge\API\Middleware\RateLimit;
use WPForge\Core\Config;
use WPForge\Core\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

class RateLimitTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        rest_get_server();
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('127.0.0.1'));
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.10'));
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.20'));
    }

    public function tearDown(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('127.0.0.1'));
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.10'));
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.20'));
        parent::tearDown();
    }

    public function testAllowsRequest(): void
    {
        $config = new Config();
        $middleware = new RateLimit($config);
        $request = new WP_REST_Request();
        $result = $middleware($request);
        $this->assertTrue($result === true);
    }

    public function testRateLimitMiddlewareIsRegisteredInRestDispatch(): void
    {
        $plugin = Plugin::getInstance();
        $priority = has_filter('rest_pre_dispatch', [$plugin, 'enforceRateLimit']);

        $this->assertNotFalse(
            $priority,
            'Rate limit middleware must be registered on rest_pre_dispatch.'
        );
    }

    public function testDispatchReturns429WithEnvelopeAfterLimitExceeded(): void
    {
        update_option('wpforge_config', [
            'rate_limit_enabled'  => true,
            'rate_limit_requests' => 3,
            'rate_limit_window'   => 60,
        ]);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';

        for ($i = 0; $i < 3; $i++) {
            $response = rest_do_request(new WP_REST_Request('GET', '/wpforge/v1/diagnostics/quick'));
            $this->assertSame(200, $response->get_status());
        }

        $blocked = rest_do_request(new WP_REST_Request('GET', '/wpforge/v1/diagnostics/quick'));
        $this->assertSame(429, $blocked->get_status());

        $data = $blocked->get_data();
        $this->assertIsArray($data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('request_id', $data);
        $this->assertNotEmpty($data['request_id']);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('code', $data['error']);
        $this->assertArrayHasKey('message', $data['error']);
    }

    public function testSpoofedForwardedForDoesNotBypassRateLimitWithoutTrustedProxy(): void
    {
        update_option('wpforge_config', [
            'rate_limit_enabled'  => true,
            'rate_limit_requests' => 3,
            'rate_limit_window'   => 60,
        ]);

        $_SERVER['REMOTE_ADDR'] = '198.51.100.20';
        $middleware = new RateLimit(new Config());

        for ($i = 1; $i <= 3; $i++) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.' . $i;
            $result = $middleware(new WP_REST_Request('GET', '/wpforge/v1/diagnostics/quick'));
            $this->assertTrue($result === true);
        }

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.4';
        $blocked = $middleware(new WP_REST_Request('GET', '/wpforge/v1/diagnostics/quick'));

        $this->assertInstanceOf(\WP_Error::class, $blocked);
        $this->assertSame(429, $blocked->get_error_data()['status'] ?? null);
    }
}
