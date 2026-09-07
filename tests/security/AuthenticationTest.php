<?php

namespace WPForge\Tests\Security;

use WPForge\Auth\Authenticator;
use WP_UnitTestCase;

class AuthenticationTest extends WP_UnitTestCase
{
    public function testRequiresAuth(): void
    {
        $auth = new Authenticator();
        $this->assertNull($auth->authenticate(new \WP_REST_Request()));
    }
}
