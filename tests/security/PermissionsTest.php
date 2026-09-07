<?php

namespace WPForge\Tests\Security;

use WPForge\API\Permissions;
use WP_UnitTestCase;

/**
 * Unit coverage for the permission-callback factories.
 */
class PermissionsTest extends WP_UnitTestCase
{
    public function testAnonymousIsRejectedWith401(): void
    {
        wp_set_current_user(0);
        $callback = Permissions::can('manage_options');
        $result = $callback();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function testAnonymousFailsAuthenticatedGate(): void
    {
        wp_set_current_user(0);
        $result = (Permissions::authenticated())();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function testSubscriberIsForbiddenWith403(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        $result = (Permissions::can('manage_options'))();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(403, $result->get_error_data()['status']);
    }

    public function testSubscriberPassesAuthenticatedGate(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'subscriber']));
        $this->assertTrue((Permissions::authenticated())());
    }

    public function testAdministratorIsAllowed(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'administrator']));
        $this->assertTrue((Permissions::can('manage_options'))());
    }

    public function testAllCapabilitiesAreRequired(): void
    {
        wp_set_current_user($this->factory->user->create(['role' => 'author']));
        // An author can edit_posts but not manage_options.
        $result = (Permissions::can('edit_posts', 'manage_options'))();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame(403, $result->get_error_data()['status']);
    }
}
