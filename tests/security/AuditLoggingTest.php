<?php

namespace WPForge\Tests\Security;

use WPForge\Core\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

class AuditLoggingTest extends WP_UnitTestCase
{
    private int $adminId;

    public function setUp(): void
    {
        parent::setUp();
        rest_get_server();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpforge_logs");

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.30';
        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.30'));

        $this->adminId = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($this->adminId);
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpforge_logs");

        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        delete_option('wpforge_config');
        delete_transient('wpforge_ratelimit_' . md5('198.51.100.30'));
        wp_set_current_user(0);
        parent::tearDown();
    }

    public function testAuditLoggerIsRegisteredInRestRequestAfterCallbacks(): void
    {
        $plugin = Plugin::getInstance();
        $priority = has_filter('rest_request_after_callbacks', [$plugin, 'auditLogMutation']);

        $this->assertNotFalse(
            $priority,
            'Audit logging listener must be registered on rest_request_after_callbacks.'
        );
    }

    public function testPostMutationWritesAuditRowWithNestedRedaction(): void
    {
        $request = new WP_REST_Request('POST', '/wpforge/v1/posts');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'post_title'   => 'Audit Test Post',
            'post_content' => 'Audit test content',
            'post_status'  => 'draft',
            'password'     => 'secret-top-level-123',
            'body'         => [
                'password' => 'secret-nested-456',
                'user'     => [
                    'api_key' => 'secret-doubly-nested-789',
                    'name'    => 'alice',
                ],
            ],
        ]));

        $response = rest_do_request($request);
        $this->assertSame(201, $response->get_status());

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wpforge_logs WHERE target = %s ORDER BY id DESC LIMIT 1",
                '/wpforge/v1/posts'
            ),
            ARRAY_A
        );

        $this->assertIsArray($row, 'Expected an audit log row in wpforge_logs for POST /wpforge/v1/posts.');
        $this->assertSame($this->adminId, (int) $row['user_id']);
        $this->assertSame(201, (int) $row['http_status']);
        $this->assertSame('198.51.100.30', $row['ip_address']);
        $this->assertStringContainsString('POST', $row['operation']);

        $this->assertNotEmpty($row['metadata']);
        $this->assertStringNotContainsString('secret-top-level-123', $row['metadata']);
        $this->assertStringNotContainsString('secret-nested-456', $row['metadata']);
        $this->assertStringNotContainsString('secret-doubly-nested-789', $row['metadata']);

        $metadata = json_decode($row['metadata'], true);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('body', $metadata);
        $this->assertSame('[REDACTED]', $metadata['body']['password'] ?? null);
        $this->assertSame('[REDACTED]', $metadata['body']['body']['password'] ?? null);
        $this->assertSame('[REDACTED]', $metadata['body']['body']['user']['api_key'] ?? null);
        $this->assertSame('alice', $metadata['body']['body']['user']['name'] ?? null);
    }

    public function testLargeRequestBodyIsTruncatedTo64Kb(): void
    {
        $request = new WP_REST_Request('POST', '/wpforge/v1/posts');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode([
            'post_title'   => 'Large Payload Post',
            'post_content' => 'Small content',
            'post_status'  => 'draft',
            'payload'      => str_repeat('x', 70000),
        ]));

        $response = rest_do_request($request);
        $this->assertSame(201, $response->get_status());

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wpforge_logs WHERE target = %s ORDER BY id DESC LIMIT 1",
                '/wpforge/v1/posts'
            ),
            ARRAY_A
        );

        $this->assertIsArray($row, 'Expected an audit log row in wpforge_logs for oversized POST /wpforge/v1/posts.');
        $this->assertLessThan(65536, strlen((string) $row['metadata']));

        $metadata = json_decode((string) $row['metadata'], true);
        $this->assertIsArray($metadata);
        $this->assertIsString($metadata['body'] ?? null);
        $this->assertStringStartsWith('[TRUNCATED]', $metadata['body']);
        $this->assertMatchesRegularExpression('/\d+/', $metadata['body']);
    }
}
