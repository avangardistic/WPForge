<?php

namespace WPForge\Tests\Security;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies that all six privileged / destructive routes require BOTH
 * developer_mode and their dedicated allow_* flag alongside WordPress
 * capabilities, while read-only plugin/theme routes remain accessible.
 */
class FeatureFlagsTest extends WP_UnitTestCase
{
    private const DUMMY_PLUGIN = 'wpforge-s3-dummy.php';
    private const DUMMY_FILE_REL = 'wp-content/wpforge-s3-test-delete.txt';
    private const DUMMY_BACKUP_ID = 'bkp_test_s3_dummy';

    private int $adminId;
    private int $subscriberId;
    private string $originalTheme;

    public function setUp(): void
    {
        parent::setUp();
        rest_get_server();

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $_SERVER['REMOTE_ADDR'] = '198.51.100.40';
        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.40'));

        $this->adminId = $this->factory->user->create(['role' => 'administrator']);
        $this->subscriberId = $this->factory->user->create(['role' => 'subscriber']);
        $this->originalTheme = (string) wp_get_theme()->get_stylesheet();

        if (!is_dir(WP_PLUGIN_DIR)) {
            wp_mkdir_p(WP_PLUGIN_DIR);
        }
        file_put_contents(
            WP_PLUGIN_DIR . '/' . self::DUMMY_PLUGIN,
            "<?php\n/**\n * Plugin Name: WPForge S3 Dummy\n */\n"
        );
        deactivate_plugins(self::DUMMY_PLUGIN, true);

        $dummyFileAbs = ABSPATH . self::DUMMY_FILE_REL;
        if (!is_dir(dirname($dummyFileAbs))) {
            wp_mkdir_p(dirname($dummyFileAbs));
        }
        file_put_contents($dummyFileAbs, 's3-delete-test');

        if (!is_dir(WPFORGE_BACKUP_DIR)) {
            wp_mkdir_p(WPFORGE_BACKUP_DIR);
        }
        file_put_contents(WPFORGE_BACKUP_DIR . '/' . self::DUMMY_BACKUP_ID . '.sql', "-- dummy\n");
    }

    public function tearDown(): void
    {
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins(self::DUMMY_PLUGIN, true);
        }
        foreach ([
            WP_PLUGIN_DIR . '/' . self::DUMMY_PLUGIN,
            ABSPATH . self::DUMMY_FILE_REL,
            WPFORGE_BACKUP_DIR . '/' . self::DUMMY_BACKUP_ID . '.sql',
        ] as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        if ($this->originalTheme !== '') {
            switch_theme($this->originalTheme);
        }

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpforge_logs");

        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.40'));
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        wp_set_current_user(0);

        parent::tearDown();
    }

    /**
     * @return array<string, array{method:string, route:string, flag:string, errorCode:string}>
     */
    public function gatedRoutesProvider(): array
    {
        return [
            'plugins activate' => [
                'method'    => 'POST',
                'route'     => '/wpforge/v1/plugins/activate',
                'flag'      => 'allow_plugin_installation',
                'errorCode' => 'PLUGIN_INSTALLATION_DISABLED',
            ],
            'plugins deactivate' => [
                'method'    => 'POST',
                'route'     => '/wpforge/v1/plugins/deactivate',
                'flag'      => 'allow_plugin_installation',
                'errorCode' => 'PLUGIN_INSTALLATION_DISABLED',
            ],
            'themes activate' => [
                'method'    => 'POST',
                'route'     => '/wpforge/v1/themes/activate',
                'flag'      => 'allow_theme_activation',
                'errorCode' => 'THEME_ACTIVATION_DISABLED',
            ],
            'files delete' => [
                'method'    => 'DELETE',
                'route'     => '/wpforge/v1/files/delete',
                'flag'      => 'allow_destructive_operations',
                'errorCode' => 'DESTRUCTIVE_OPERATIONS_DISABLED',
            ],
            'logs clear' => [
                'method'    => 'DELETE',
                'route'     => '/wpforge/v1/logs/clear',
                'flag'      => 'allow_destructive_operations',
                'errorCode' => 'DESTRUCTIVE_OPERATIONS_DISABLED',
            ],
            'backup delete' => [
                'method'    => 'DELETE',
                'route'     => '/wpforge/v1/backup/' . self::DUMMY_BACKUP_ID,
                'flag'      => 'allow_destructive_operations',
                'errorCode' => 'DESTRUCTIVE_OPERATIONS_DISABLED',
            ],
        ];
    }

    private function buildRequest(string $method, string $route): WP_REST_Request
    {
        $request = new WP_REST_Request($method, $route);

        if ($route === '/wpforge/v1/plugins/activate' || $route === '/wpforge/v1/plugins/deactivate') {
            $request->set_param('plugin', self::DUMMY_PLUGIN);
        } elseif ($route === '/wpforge/v1/themes/activate') {
            $request->set_param('theme', $this->originalTheme);
        } elseif ($route === '/wpforge/v1/files/delete') {
            $request->set_param('path', self::DUMMY_FILE_REL);
        }

        return $request;
    }

    /**
     * @dataProvider gatedRoutesProvider
     */
    public function testGatedRouteIsDisabledByDefault(
        string $method,
        string $route,
        string $flag,
        string $errorCode
    ): void {
        wp_set_current_user($this->adminId);

        $response = rest_do_request($this->buildRequest($method, $route));
        $data = $response->get_data();

        $this->assertSame(
            403,
            $response->get_status(),
            "{$method} {$route} must return 403 when defaults are in effect."
        );
        $this->assertFalse($data['success'] ?? null);
        $this->assertSame($errorCode, $data['error']['code'] ?? null);
    }

    /**
     * @dataProvider gatedRoutesProvider
     */
    public function testGatedRouteRemainsDisabledWhenDeveloperModeIsOff(
        string $method,
        string $route,
        string $flag,
        string $errorCode
    ): void {
        wp_set_current_user($this->adminId);
        update_option('wpforge_config', [
            'developer_mode' => false,
            $flag            => true,
        ]);

        $response = rest_do_request($this->buildRequest($method, $route));
        $data = $response->get_data();

        $this->assertSame(
            403,
            $response->get_status(),
            "{$method} {$route} must stay 403 when developer_mode is false even if {$flag} is true."
        );
        $this->assertFalse($data['success'] ?? null);
        $this->assertSame($errorCode, $data['error']['code'] ?? null);
    }

    /**
     * @dataProvider gatedRoutesProvider
     */
    public function testGatedRouteRequiresCapabilityEvenWhenFlagsAreEnabled(
        string $method,
        string $route,
        string $flag,
        string $errorCode
    ): void {
        wp_set_current_user($this->subscriberId);
        update_option('wpforge_config', [
            'developer_mode' => true,
            $flag            => true,
        ]);

        $response = rest_do_request($this->buildRequest($method, $route));

        $this->assertSame(
            403,
            $response->get_status(),
            "{$method} {$route} must reject low-privilege users even when {$flag} is enabled."
        );
    }

    /**
     * @dataProvider gatedRoutesProvider
     */
    public function testGatedRouteSucceedsWhenDeveloperModeFlagAndCapabilitiesArePresent(
        string $method,
        string $route,
        string $flag,
        string $errorCode
    ): void {
        wp_set_current_user($this->adminId);
        update_option('wpforge_config', [
            'developer_mode' => true,
            $flag            => true,
        ]);

        $response = rest_do_request($this->buildRequest($method, $route));
        $data = $response->get_data();

        $this->assertSame(
            200,
            $response->get_status(),
            "{$method} {$route} must succeed (200) when developer_mode, {$flag}, and capabilities are present."
        );
        $this->assertTrue($data['success'] ?? null);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function openReadRoutesProvider(): array
    {
        return [
            'plugins list' => ['/wpforge/v1/plugins'],
            'themes list'  => ['/wpforge/v1/themes'],
        ];
    }

    /**
     * @dataProvider openReadRoutesProvider
     */
    public function testReadRoutesRemainAccessibleWhenDeveloperModeIsFalse(string $route): void
    {
        wp_set_current_user($this->adminId);
        update_option('wpforge_config', [
            'developer_mode'            => false,
            'allow_plugin_installation' => false,
            'allow_theme_activation'    => false,
        ]);

        $response = rest_do_request(new WP_REST_Request('GET', $route));
        $data = $response->get_data();

        $this->assertSame(
            200,
            $response->get_status(),
            "GET {$route} must remain 200 for authorised users when developer_mode is false."
        );
        $this->assertTrue($data['success'] ?? null);
    }
}
