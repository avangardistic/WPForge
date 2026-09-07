<?php
/**
 * WPForge default configuration.
 *
 * This array is the base for every setting. The `wpforge_config` option is merged
 * over it at runtime, so a key absent from the option falls back to the value here.
 * `WPForge\Core\Config` loads this file; see docs/CONFIGURATION.md for what each
 * key does and which are safe to change on a production site.
 *
 * Every capability that can modify the site is false by default, and each one is
 * additionally gated behind `developer_mode`.
 *
 * @package WPForge
 */

return [
    'enabled'                      => true,

    // Master switch. All allow_* flags below are inert while this is false.
    'developer_mode'               => false,

    // Filesystem sandbox root. Empty string means ABSPATH.
    'filesystem_root'              => '',

    // Privileged capabilities — each also requires developer_mode.
    'allow_filesystem_writes'      => false,
    'allow_database_writes'        => false,
    'allow_plugin_installation'    => false,
    'allow_theme_activation'       => false,
    'allow_destructive_operations' => false,

    // Audit trail.
    'audit_logging'                => true,

    // Rate limiting (per authenticated identity).
    'rate_limit_enabled'           => true,
    'rate_limit_requests'          => 100,
    'rate_limit_window'            => 60,

    // Backup retention.
    'backup_retention_days'        => 7,
    'max_backup_size_mb'           => 100,

    'security'                     => [
        'cors_origins'          => [],
        'allowed_ips'           => [],
        'blocked_ips'           => [],

        // When false, /status and /health require an authenticated user.
        // Leave false unless an external monitor genuinely needs them: both
        // disclose the WordPress version, PHP version and site URL.
        'public_status_enabled' => false,
        'public_health_enabled' => false,

        // Strip DB_NAME from GET /database/status.
        'redact_db_credentials' => true,
    ],
];
