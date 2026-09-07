<?php

namespace WPForge\Tests\Security;

use WPForge\Auth\CapabilityChecker;
use WP_UnitTestCase;

class AuthorizationTest extends WP_UnitTestCase
{
    public function testAdminHasCapability(): void
    {
        $userId = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($userId);
        $this->assertTrue(CapabilityChecker::check('manage_options'));
    }

    public function testSubscriberLacksCapability(): void
    {
        $userId = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($userId);
        $this->assertFalse(CapabilityChecker::check('manage_options'));
    }
}
