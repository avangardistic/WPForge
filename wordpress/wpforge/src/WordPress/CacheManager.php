<?php
namespace WPForge\WordPress;

/**
 * Cache management service — purge object cache, page cache, etc.
 */
class CacheManager
{
    /**
     * Flush all caches.
     */
    public function flushAll(): array
    {
        $results = [];

        // Object cache
        $results['object_cache'] = $this->flushObjectCache();

        // WordPress transient cache
        $results['transients'] = $this->flushTransients();

        // Page cache (if supported)
        $results['page_cache'] = $this->flushPageCache();

        // Rewrite rules
        flush_rewrite_rules();
        $results['rewrite_rules'] = true;

        return $results;
    }

    /**
     * Flush the object cache.
     */
    public function flushObjectCache(): bool
    {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            return true;
        }
        return false;
    }

    /**
     * Clear all transients.
     */
    public function flushTransients(): bool
    {
        global $wpdb;

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
        return true;
    }

    /**
     * Flush page cache (checks for common caching plugins).
     */
    public function flushPageCache(): array
    {
        $results = [];

        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            $results['wp_super_cache'] = true;
        }

        // W3 Total Cache
        if (class_exists('W3TC\\Config')) {
            $results['w3_total_cache'] = 'plugin detected — manual flush may be needed';
        }

        // WP Rocket
        if (class_exists('\\WP_Rocket\\Subscriber\\Cache\\Purge')) {
            $results['wp_rocket'] = 'plugin detected — manual flush may be needed';
        }

        // LiteSpeed Cache
        if (class_exists('\\LiteSpeed\\Cache')) {
            $results['litespeed_cache'] = 'plugin detected — manual flush may be needed';
        }

        if (empty($results)) {
            $results['note'] = 'No page cache plugin detected';
        }

        return $results;
    }

    /**
     * Get cache status information.
     */
    public function getStatus(): array
    {
        return [
            'object_cache_available' => function_exists('wp_cache_flush'),
            'transients_count'      => $this->getTransientCount(),
            'page_cache_plugins'    => $this->detectCachePlugins(),
        ];
    }

    private function getTransientCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
        );
    }

    private function detectCachePlugins(): array
    {
        $plugins = [];

        if (defined('WP_CACHE') && WP_CACHE) {
            $plugins[] = 'WP-Cache (generic)';
        }
        if (class_exists('\\Site_LiteSpeed_Cache')) {
            $plugins[] = 'LiteSpeed Cache';
        }
        if (function_exists('rocket_clean_domain')) {
            $plugins[] = 'WP Rocket';
        }
        if (defined('WPCACHEHOME')) {
            $plugins[] = 'WP Super Cache';
        }
        if (defined('W3TC')) {
            $plugins[] = 'W3 Total Cache';
        }

        return $plugins;
    }
}
