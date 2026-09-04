<?php

namespace WPForge\WordPress;

/**
 * Core WordPress service providing site-wide information and utilities
 */
class WordPressService
{
    /**
     * Get WordPress version information
     */
    public function getVersion(): string
    {
        global $wp_version;
        return $wp_version;
    }

    /**
     * Get PHP version
     */
    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * Get site URL
     */
    public function getSiteUrl(): string
    {
        return get_site_url();
    }

    /**
     * Get home URL
     */
    public function getHomeUrl(): string
    {
        return get_home_url();
    }

    /**
     * Get admin URL
     */
    public function getAdminUrl(): string
    {
        return get_admin_url();
    }

    /**
     * Get permalink structure
     */
    public function getPermalinkStructure(): string
    {
        return get_option('permalink_structure', '');
    }

    /**
     * Check if permalinks are enabled
     */
    public function permalinksEnabled(): bool
    {
        return !empty(get_option('permalink_structure'));
    }

    /**
     * Get registered post types
     */
    public function getPostTypes(array $args = []): array
    {
        $defaults = [
            'public' => true,
            'show_in_rest' => null, // Include both REST and non-REST
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $post_types = get_post_types($args, 'objects');
        $result = [];
        
        foreach ($post_types as $name => $post_type) {
            $result[$name] = [
                'name' => $post_type->name,
                'label' => $post_type->label,
                'labels' => (array) $post_type->labels,
                'description' => $post_type->description,
                'public' => $post_type->public,
                'hierarchical' => $post_type->hierarchical,
                'rest_base' => $post_type->rest_base ?? null,
                'rest_controller_class' => $post_type->rest_controller_class ?? null,
                'supports' => get_all_post_type_supports($name),
                'taxonomies' => get_object_taxonomies($name),
                'has_archive' => $post_type->has_archive,
                'menu_icon' => $post_type->menu_icon,
                'capability_type' => $post_type->capability_type,
            ];
        }
        
        return $result;
    }

    /**
     * Get registered taxonomies
     */
    public function getTaxonomies(array $args = []): array
    {
        $defaults = [
            'public' => true,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $taxonomies = get_taxonomies($args, 'objects');
        $result = [];
        
        foreach ($taxonomies as $name => $taxonomy) {
            $result[$name] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'labels' => (array) $taxonomy->labels,
                'description' => $taxonomy->description,
                'public' => $taxonomy->public,
                'hierarchical' => $taxonomy->hierarchical,
                'rest_base' => $taxonomy->rest_base ?? null,
                'object_type' => $taxonomy->object_type,
                'show_tagcloud' => $taxonomy->show_tagcloud,
            ];
        }
        
        return $result;
    }

    /**
     * Get active theme information
     */
    public function getActiveTheme(): array
    {
        $theme = wp_get_theme();
        
        return [
            'name' => $theme->get('Name'),
            'uri' => $theme->get('ThemeURI'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'author_uri' => $theme->get('AuthorURI'),
            'description' => $theme->get('Description'),
            'template' => $theme->get('Template'),
            'is_child_theme' => $theme->parent() ? true : false,
            'parent_theme' => $theme->parent() ? [
                'name' => $theme->parent()->get('Name'),
                'version' => $theme->parent()->get('Version'),
            ] : null,
            'stylesheet' => $theme->get_stylesheet(),
            'template_dir' => $theme->get_template_directory(),
            'stylesheet_dir' => $theme->get_stylesheet_directory(),
        ];
    }

    /**
     * Get all installed themes
     */
    public function getAllThemes(): array
    {
        $themes = wp_get_themes();
        $result = [];
        
        foreach ($themes as $theme) {
            $result[] = [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'stylesheet' => $theme->get_stylesheet(),
                'template' => $theme->get_template(),
                'is_active' => $theme->is_allowed(),
            ];
        }
        
        return $result;
    }

    /**
     * Get site settings
     */
    public function getSettings(): array
    {
        return [
            'site_title' => get_option('blogname'),
            'site_description' => get_option('blogdescription'),
            'site_url' => get_option('siteurl'),
            'home_url' => get_option('home'),
            'timezone' => get_option('timezone_string'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'start_of_week' => get_option('start_of_week'),
            'language' => get_option('WPLANG'),
            'users_can_register' => (bool) get_option('users_can_register'),
            'default_role' => get_option('default_role'),
            'posts_per_page' => (int) get_option('posts_per_page'),
            'show_on_front' => get_option('show_on_front'),
            'page_on_front' => (int) get_option('page_on_front'),
            'page_for_posts' => (int) get_option('page_for_posts'),
            'default_comment_status' => get_option('default_comment_status'),
            'default_ping_status' => get_option('default_ping_status'),
        ];
    }

    /**
     * Get available REST API namespaces
     */
    public function getRestNamespaces(): array
    {
        $rest_server = rest_get_server();
        $namespaces = $rest_server->get_namespaces();
        
        $result = [];
        foreach ($namespaces as $namespace) {
            $routes = $rest_server->get_routes($namespace);
            $result[$namespace] = [
                'namespace' => $namespace,
                'route_count' => count($routes),
            ];
        }
        
        return $result;
    }

    /**
     * Check if multisite is enabled
     */
    public function isMultisite(): bool
    {
        return is_multisite();
    }

    /**
     * Get memory limit
     */
    public function getMemoryLimit(): string
    {
        return ini_get('memory_limit');
    }

    /**
     * Get max upload size
     */
    public function getMaxUploadSize(): string
    {
        return wp_max_upload_size();
    }

    /**
     * Check if in debug mode
     */
    public function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Get WordPress environment type
     */
    public function getEnvironmentType(): string
    {
        return function_exists('wp_get_environment_type') 
            ? wp_get_environment_type() 
            : 'production';
    }

    /**
     * Get cron status
     */
    public function getCronStatus(): array
    {
        $crons = _get_cron_array();
        
        if (empty($crons)) {
            return [
                'enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
                'event_count' => 0,
                'events' => [],
            ];
        }
        
        $events = [];
        foreach ($crons as $timestamp => $hooks) {
            foreach ($hooks as $hook => $details) {
                $events[] = [
                    'hook' => $hook,
                    'timestamp' => $timestamp,
                    'schedule' => $details[0]['schedule'] ?? 'oneshot',
                    'interval' => $details[0]['interval'] ?? null,
                ];
            }
        }
        
        return [
            'enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'event_count' => count($events),
            'events' => array_slice($events, 0, 50), // Limit to first 50
        ];
    }
}
