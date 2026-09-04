<?php
namespace WPForge\Diagnostics;

/**
 * Comprehensive health reporter — aggregates all diagnostic checks.
 */
class HealthReporter
{
    private SystemChecker $system;
    private PermissionsChecker $permissions;
    private ComponentDetector $components;

    public function __construct()
    {
        $this->system = new SystemChecker();
        $this->permissions = new PermissionsChecker();
        $this->components = new ComponentDetector();
    }

    /**
     * Generate a full health report.
     */
    public function getFullReport(): array
    {
        $system = $this->system->checkAll();
        $permissions = $this->permissions->checkUserCapabilities();
        $components = $this->components->detectAll();

        $checks = [
            'wordpress' => [
                'status'  => 'ok',
                'details' => 'WordPress ' . $system['wordpress']['version'] . ' loaded',
            ],
            'database' => [
                'status'  => $system['database']['connected'] ? 'ok' : 'error',
                'details' => $system['database']['connected']
                    ? 'Connected (latency: ' . $system['database']['latency_ms'] . 'ms)'
                    : 'Database connection failed',
            ],
            'filesystem' => [
                'status'  => is_writable(WP_CONTENT_DIR) ? 'ok' : 'warning',
                'details' => is_writable(WP_CONTENT_DIR)
                    ? 'Content directory is writable'
                    : 'Content directory is not writable',
            ],
            'rest_api' => [
                'status'  => $permissions['rest_api_available'] ?? true ? 'ok' : 'error',
                'details' => 'REST API is available',
            ],
            'php' => [
                'status'  => version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'warning',
                'details' => 'PHP ' . PHP_VERSION,
            ],
        ];

        $allOk = true;
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $allOk = false;
                break;
            }
        }

        return [
            'status'     => $allOk ? 'healthy' : 'degraded',
            'checks'     => $checks,
            'system'     => $system,
            'permissions'=> $permissions,
            'components' => $components,
            'timestamp'  => current_time('mysql'),
        ];
    }

    /**
     * Get a quick health check (no detailed info).
     */
    public function getQuickCheck(): array
    {
        $checks = [
            'wordpress'   => function_exists('is_user_logged_in'),
            'rest_api'    => class_exists('WP_REST_Server'),
            'database'    => !empty($GLOBALS['wpdb']),
            'filesystem'  => is_writable(WP_CONTENT_DIR),
            'memory'      => $this->checkMemory(),
        ];

        return [
            'healthy' => !in_array(false, $checks, true),
            'checks'  => $checks,
        ];
    }

    private function checkMemory(): bool
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return true;
        }
        $bytes = $this->convertToBytes($limit);
        return $bytes >= 64 * 1024 * 1024; // At least 64MB
    }

    private function convertToBytes(string $value): int
    {
        $num = (int) $value;
        $suffix = strtolower(substr($value, -1));
        return match ($suffix) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }
}
