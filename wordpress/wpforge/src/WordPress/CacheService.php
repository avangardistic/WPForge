<?php

namespace WPForge\WordPress;

/**
 * Cache management service
 */
class CacheService
{
    public function clearAll(): void
    {
        // Clear object cache
        wp_cache_flush();
        
        // Clear rewrite rules
        delete_option('rewrite_rules');
        flush_rewrite_rules();
        
        // Clear Elementor cache if available
        if (class_exists('\Elementor\Plugin')) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
    
    public function clearPostCache(int $post_id): void
    {
        clean_post_cache($post_id);
        wp_cache_delete($post_id, 'posts');
    }
}
