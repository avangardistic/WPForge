<?php
namespace WPForge\Tests\Security;

use WPForge\API\Middleware\RateLimit;
use WPForge\Core\Config;
use WP_UnitTestCase;

class RateLimitTest extends WP_UnitTestCase
{
    public function testAllowsRequest(): void
    {
        $config = new Config();
        $middleware = new RateLimit($config);
        $request = new \WP_REST_Request();
        $result = $middleware($request);
        $this->assertTrue($result === true);
    }
}
