<?php

namespace WPForge\Core;

/**
 * Configuration management for WPForge
 */
class Config
{
    private const OPTION_NAME = 'wpforge_config';
    
    private array $defaults = [
        'enabled' => true,
        'developer_mode' => false,
        'filesystem_root' => '',
        'allow_filesystem_writes' => false,
        'allow_database_writes' => false,
        'allow_plugin_installation' => false,
        'allow_theme_activation' => false,
        'allow_destructive_operations' => false,
        'audit_logging' => true,
        'rate_limit_enabled' => true,
        'rate_limit_requests' => 100,
        'rate_limit_window' => 60,
        'backup_retention_days' => 7,
        'max_backup_size_mb' => 100,
        // Security settings
        'security' => [
            'cors_origins' => [],
            'allowed_ips' => [],
            'blocked_ips' => [],
            'public_status_enabled' => false,  // Disable public /status by default
            'public_health_enabled' => false,  // Disable public /health by default
            'redact_db_credentials' => true,    // Never expose DB credentials
        ],
    ];

    private ?array $config = null;

    /**
     * Get configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->all();
        
        if (array_key_exists($key, $config)) {
            return $config[$key];
        }
        
        return $default ?? ($this->defaults[$key] ?? null);
    }
    
    /**
     * Get nested configuration value using dot notation
     */
    public function getNested(string $key, mixed $default = null): mixed
    {
        $config = $this->all();
        $keys = explode('.', $key);
        
        foreach ($keys as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }
        
        return $config;
    }

    /**
     * Get all configuration values
     */
    public function all(): array
    {
        if (null === $this->config) {
            $stored = get_option(self::OPTION_NAME, []);
            $this->config = array_merge($this->defaults, $stored);
            
            // Set default filesystem root if not configured
            if (empty($this->config['filesystem_root'])) {
                $this->config['filesystem_root'] = ABSPATH;
            }
        }
        
        return $this->config;
    }

    /**
     * Update configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $config = $this->all();
        $config[$key] = $value;
        update_option(self::OPTION_NAME, $config);
        $this->config = $config;
    }

    /**
     * Update multiple configuration values
     */
    public function setMultiple(array $values): void
    {
        $config = array_merge($this->all(), $values);
        update_option(self::OPTION_NAME, $config);
        $this->config = $config;
    }

    /**
     * Check if developer mode is enabled
     */
    public function isDeveloperMode(): bool
    {
        return (bool) $this->get('developer_mode');
    }

    /**
     * Check if filesystem writes are allowed
     */
    public function allowFilesystemWrites(): bool
    {
        return $this->isDeveloperMode() && (bool) $this->get('allow_filesystem_writes');
    }

    /**
     * Check if database writes are allowed
     */
    public function allowDatabaseWrites(): bool
    {
        return $this->isDeveloperMode() && (bool) $this->get('allow_database_writes');
    }

    /**
     * Check if plugin installation is allowed
     */
    public function allowPluginInstallation(): bool
    {
        return $this->isDeveloperMode() && (bool) $this->get('allow_plugin_installation');
    }

    /**
     * Check if theme activation is allowed
     */
    public function allowThemeActivation(): bool
    {
        return $this->isDeveloperMode() && (bool) $this->get('allow_theme_activation');
    }

    /**
     * Check if destructive operations are allowed
     */
    public function allowDestructiveOperations(): bool
    {
        return $this->isDeveloperMode() && (bool) $this->get('allow_destructive_operations');
    }

    /**
     * Check if audit logging is enabled
     */
    public function auditLoggingEnabled(): bool
    {
        return (bool) $this->get('audit_logging');
    }

    /**
     * Check if rate limiting is enabled
     */
    public function rateLimitEnabled(): bool
    {
        return (bool) $this->get('rate_limit_enabled');
    }

    /**
     * Get rate limit requests
     */
    public function getRateLimitRequests(): int
    {
        return (int) $this->get('rate_limit_requests');
    }

    /**
     * Get rate limit window in seconds
     */
    public function getRateLimitWindow(): int
    {
        return (int) $this->get('rate_limit_window');
    }

    /**
     * Get filesystem root path
     */
    public function getFilesystemRoot(): string
    {
        $root = $this->get('filesystem_root');
        return !empty($root) ? rtrim($root, '/') . '/' : ABSPATH;
    }

    /**
     * Check if public status endpoint is enabled
     * SECURITY: Returns false by default to prevent information disclosure
     */
    public function isPublicStatusEnabled(): bool
    {
        return (bool) ($this->get('security')['public_status_enabled'] ?? false);
    }

    /**
     * Check if public health endpoint is enabled
     * SECURITY: Returns false by default to prevent information disclosure
     */
    public function isPublicHealthEnabled(): bool
    {
        return (bool) ($this->get('security')['public_health_enabled'] ?? false);
    }

    /**
     * Check if database credentials should be redacted from responses
     */
    public function shouldRedactDbCredentials(): bool
    {
        return (bool) ($this->get('security')['redact_db_credentials'] ?? true);
    }

    /**
     * Get security configuration
     */
    public function getSecurityConfig(): array
    {
        return $this->get('security', []);
    }

    /**
     * Reset configuration to defaults
     */
    public function reset(): void
    {
        delete_option(self::OPTION_NAME);
        $this->config = null;
    }
}