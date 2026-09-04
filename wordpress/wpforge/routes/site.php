<?php

namespace WPForge\API;

use WP_REST_Response;
use WPForge\WordPress\WordPressService;
use WPForge\Elementor\ElementorService;

/**
 * Site inspection routes for WPForge API
 */
class SiteRoutes extends BaseRoutes
{
    private WordPressService $wordpress;
    private ElementorService $elementor;

    public function __construct()
    {
        parent::__construct();
        $this->wordpress = new WordPressService();
        $this->elementor = new ElementorService();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/site', [
            'methods' => 'GET',
            'callback' => [$instance, 'getSite'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/site/structure', [
            'methods' => 'GET',
            'callback' => [$instance, 'getStructure'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/site/routes', [
            'methods' => 'GET',
            'callback' => [$instance, 'getRoutes'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getSite(): WP_REST_Response
    {
        return $this->successResponse([
            'wordpress_version' => $this->wordpress->getVersion(),
            'php_version' => PHP_VERSION,
            'site_url' => $this->wordpress->getSiteUrl(),
            'home_url' => $this->wordpress->getHomeUrl(),
            'admin_url' => $this->wordpress->getAdminUrl(),
            'permalink_structure' => $this->wordpress->getPermalinkStructure(),
            'active_theme' => $this->wordpress->getActiveTheme(),
            'plugins' => $this->getPluginSummary(),
            'elementor' => $this->elementor->getStatus(),
            'settings' => $this->wordpress->getSettings(),
            'post_types' => array_keys($this->wordpress->getPostTypes()),
            'taxonomies' => array_keys($this->wordpress->getTaxonomies()),
            'rest_namespaces' => array_keys($this->wordpress->getRestNamespaces()),
            'front_page' => $this->wordpress->getSettings()['page_on_front'] ?: null,
            'posts_page' => $this->wordpress->getSettings()['page_for_posts'] ?: null,
        ]);
    }

    public function getStructure(): WP_REST_Response
    {
        return $this->successResponse([
            'post_types' => $this->wordpress->getPostTypes(),
            'taxonomies' => $this->wordpress->getTaxonomies(),
            'menus' => $this->getMenus(),
            'widgets' => $this->getWidgetsSummary(),
            'templates' => $this->getThemeTemplates(),
        ]);
    }

    public function getRoutes(): WP_REST_Response
    {
        $rest_server = rest_get_server();
        $namespaces = $rest_server->get_namespaces();
        $routes = [];

        foreach ($namespaces as $namespace) {
            $namespace_routes = $rest_server->get_routes($namespace);
            foreach ($namespace_routes as $route => $data) {
                $methods = [];
                foreach ($data as $item) {
                    if (isset($item['methods'])) {
                        $methods[] = $item['methods'];
                    }
                }
                $routes[] = [
                    'namespace' => $namespace,
                    'route' => $route,
                    'methods' => array_unique($methods),
                ];
            }
        }

        return $this->successResponse(['routes' => $routes]);
    }

    private function getPluginSummary(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $plugins = get_plugins();
        $active = get_option('active_plugins', []);
        
        $result = [];
        foreach ($plugins as $file => $data) {
            $result[] = [
                'file' => $file,
                'name' => $data['Name'],
                'version' => $data['Version'],
                'active' => in_array($file, $active, true),
            ];
        }
        
        return $result;
    }

    private function getMenus(): array
    {
        $menus = wp_get_nav_menus();
        $result = [];
        
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $result[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'locations' => get_nav_menu_locations(),
                'item_count' => count($items ?? []),
            ];
        }
        
        return $result;
    }

    private function getWidgetsSummary(): array
    {
        global $wp_registered_sidebars;
        $result = [];
        
        foreach ($wp_registered_sidebars as $id => $sidebar) {
            $result[] = [
                'id' => $id,
                'name' => $sidebar['name'],
                'description' => $sidebar['description'] ?? '',
            ];
        }
        
        return $result;
    }

    private function getThemeTemplates(): array
    {
        $theme = wp_get_theme();
        $templates = [];
        
        // Get page templates
        $page_templates = $theme->get_page_templates();
        foreach ($page_templates as $file => $name) {
            $templates[] = [
                'type' => 'page',
                'file' => $file,
                'name' => $name,
            ];
        }
        
        return $templates;
    }
}
