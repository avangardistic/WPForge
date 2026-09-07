<?php

namespace WPForge\WordPress;

/**
 * Comprehensive site inspector — gathers full WordPress environment info.
 */
class SiteInspector
{
    public function inspect(): array
    {
        return [
            'wordpress'  => $this->getWordPressInfo(),
            'theme'      => $this->getThemeInfo(),
            'plugins'    => $this->getPluginInfo(),
            'post_types' => $this->getPostTypes(),
            'taxonomies' => $this->getTaxonomies(),
            'menus'      => $this->getMenus(),
            'widgets'    => $this->getWidgets(),
            'elementor'  => $this->getElementorInfo(),
            'user'       => $this->getUserInfo(),
            'server'     => $this->getServerInfo(),
            'rest_api'   => $this->getRestNamespaces(),
        ];
    }

    public function getStructure(): array
    {
        return [
            'content' => [
                'posts'   => (int) wp_count_posts('post')->publish,
                'pages'   => (int) wp_count_posts('page')->publish,
                'media'   => (int) wp_count_posts('attachment')->inherit,
            ],
            'taxonomies' => [
                'categories' => (int) wp_count_terms('category'),
                'tags'       => (int) wp_count_terms('post_tag'),
            ],
            'users'  => (int) count_users()['total_users'],
            'plugins' => count(get_plugins()),
            'menus'  => count(wp_get_nav_menus()),
        ];
    }

    public function getRoutes(): array
    {
        $server = rest_get_server();
        $routes = $server->get_routes();
        $result = [];

        foreach ($routes as $route => $handlers) {
            $methods = [];
            foreach ($handlers as $handler) {
                if (isset($handler['methods'])) {
                    $methods = array_merge($methods, array_keys($handler['methods']));
                }
            }
            $result[$route] = array_unique($methods);
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */

    private function getWordPressInfo(): array
    {
        return [
            'version'             => get_bloginfo('version'),
            'site_url'            => get_site_url(),
            'home_url'            => get_home_url(),
            'admin_email'         => get_option('admin_email'),
            'language'            => get_bloginfo('language'),
            'timezone'            => get_option('timezone_string') ?: 'UTC',
            'date_format'         => get_option('date_format'),
            'time_format'         => get_option('time_format'),
            'blog_public'         => get_option('blog_public') == '1',
            'permalink_structure' => get_option('permalink_structure'),
            'front_page'          => get_option('page_on_front') ? get_the_title(get_option('page_on_front')) : 'Latest Posts',
            'posts_page'          => get_option('page_for_posts') ? get_the_title(get_option('page_for_posts')) : 'Not set',
            'show_on_front'       => get_option('show_on_front'),
        ];
    }

    private function getThemeInfo(): array
    {
        $theme = wp_get_theme();
        return [
            'name'        => $theme->get('Name'),
            'version'     => $theme->get('Version'),
            'author'      => $theme->get('Author'),
            'text_domain' => $theme->get('TextDomain'),
            'is_active'   => get_template() === $theme->get_stylesheet(),
            'parent'      => $theme->parent() ? $theme->parent()->get('Name') : null,
            'theme_uri'   => $theme->get('ThemeURI'),
            'description' => $theme->get('Description'),
            'template'    => $theme->get_template(),
            'stylesheet'  => $theme->get_stylesheet(),
        ];
    }

    private function getPluginInfo(): array
    {
        $plugins = get_plugins();
        $active  = get_option('active_plugins', []);
        $result  = [];

        foreach ($plugins as $path => $plugin) {
            $result[] = [
                'name'        => $plugin['Name'] ?? '',
                'version'     => $plugin['Version'] ?? '',
                'author'      => $plugin['Author'] ?? '',
                'description' => $plugin['Description'] ?? '',
                'path'        => $path,
                'is_active'   => in_array($path, $active) || is_plugin_active($path),
                'requires_wp' => $plugin['RequiresWP'] ?? '',
                'requires_php' => $plugin['RequiresPHP'] ?? '',
                'text_domain' => $plugin['TextDomain'] ?? '',
            ];
        }

        return $result;
    }

    private function getPostTypes(): array
    {
        $types  = get_post_types(['public' => true], 'objects');
        $result = [];

        foreach ($types as $name => $type) {
            $countObj = wp_count_posts($name);
            $result[$name] = [
                'label'          => $type->label,
                'description'    => $type->description,
                'public'         => $type->public,
                'hierarchical'   => $type->hierarchical,
                'has_archive'    => $type->has_archive,
                'show_in_rest'   => $type->show_in_rest,
                'rest_base'      => $type->rest_base,
                'supports'       => get_all_post_type_supports($name),
                'count'          => (int) ($countObj->publish ?? 0),
            ];
        }

        return $result;
    }

    private function getTaxonomies(): array
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $result = [];

        foreach ($taxonomies as $name => $tax) {
            $terms = get_terms(['taxonomy' => $name, 'hide_empty' => false]);
            $result[$name] = [
                'label'       => $tax->label,
                'hierarchical' => $tax->hierarchical,
                'show_in_rest' => $tax->show_in_rest,
                'rest_base'   => $tax->rest_base,
                'post_types'  => $tax->object_type,
                'count'       => is_wp_error($terms) ? 0 : count($terms),
            ];
        }

        return $result;
    }

    private function getMenus(): array
    {
        $menus  = wp_get_nav_menus();
        $result = [];

        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $locations = get_nav_menu_locations();
            $locationName = null;
            foreach ($locations as $loc => $menuId) {
                if ($menuId == $menu->term_id) {
                    $locationName = $loc;
                    break;
                }
            }
            $result[] = [
                'id'        => (int) $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'count'     => $items ? count($items) : 0,
                'location'  => $locationName,
            ];
        }

        return $result;
    }

    private function getWidgets(): array
    {
        // Use get_option() instead of wp_get_sidebars_widgets() for WordPress.org compliance
        $sidebars = get_option('sidebars_widgets', []);
        $result   = [];

        if (!is_array($sidebars)) {
            return $result;
        }

        foreach ($sidebars as $sidebar => $widgets) {
            if ($sidebar === 'wp_inactive_widgets' || empty($widgets)) {
                continue;
            }
            $result[$sidebar] = $widgets;
        }

        return $result;
    }

    private function getElementorInfo(): array
    {
        if (!class_exists('\Elementor\Plugin')) {
            return ['installed' => false];
        }

        return [
            'installed' => true,
            'active'    => did_action('elementor/loaded') > 0,
            'version'   => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
            'pro'       => [
                'installed' => class_exists('\ElementorPro\Plugin'),
                'active'    => defined('ELEMENTOR_PRO_VERSION') && constant('ELEMENTOR_PRO_VERSION') !== '',
                'version'   => defined('ELEMENTOR_PRO_VERSION') ? constant('ELEMENTOR_PRO_VERSION') : null,
            ],
        ];
    }

    private function getUserInfo(): array
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return ['authenticated' => false];
        }

        return [
            'authenticated' => true,
            'id'            => (int) $user->ID,
            'username'      => $user->user_login,
            'display_name'  => $user->display_name,
            'email'         => $user->user_email,
            'roles'         => $user->roles,
            'registered'    => $user->user_registered,
        ];
    }

    private function getServerInfo(): array
    {
        return [
            'php_version'      => PHP_VERSION,
            'server_software'  => isset($_SERVER['SERVER_SOFTWARE'])
                ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']))
                : 'unknown',
            'max_upload_size'  => (int) wp_max_upload_size(),
            'memory_limit'     => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    private function getRestNamespaces(): array
    {
        $server = rest_get_server();
        $namespaces = $server->get_namespaces();
        return is_array($namespaces) ? $namespaces : [];
    }
}
