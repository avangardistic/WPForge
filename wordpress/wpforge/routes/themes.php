<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WPForge\Core\Config;

/**
 * Themes routes for WPForge API
 */
class ThemesRoutes extends BaseRoutes
{
    private Config $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new Config();
    }

    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/themes', [
            'methods' => 'GET',
            'callback' => [$instance, 'getThemes'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/themes/active', [
            'methods' => 'GET',
            'callback' => [$instance, 'getActiveTheme'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/themes/(?P<stylesheet>[a-zA-Z0-9_-]+)/activate', [
            'methods' => 'POST',
            'callback' => [$instance, 'activateTheme'],
            'permission_callback' => [$instance, 'checkActivatePermission'],
        ]);
    }

    public function getThemes(WP_REST_Request $request): WP_REST_Response
    {
        $themes = wp_get_themes();
        $active_stylesheet = get_option('stylesheet');
        
        $result = [];
        foreach ($themes as $theme) {
            $result[] = [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'stylesheet' => $theme->get_stylesheet(),
                'template' => $theme->get_template(),
                'is_active' => $theme->get_stylesheet() === $active_stylesheet,
                'is_child' => (bool) $theme->parent(),
            ];
        }

        return $this->successResponse(['themes' => $result]);
    }

    public function getActiveTheme(WP_REST_Response): WP_REST_Response
    {
        $theme = wp_get_theme();
        
        return $this->successResponse([
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'stylesheet' => $theme->get_stylesheet(),
            'template' => $theme->get_template(),
            'theme_uri' => $theme->get('ThemeURI'),
            'description' => $theme->get('Description'),
        ]);
    }

    public function activateTheme(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->config->allowThemeActivation()) {
            return $this->errorResponse('theme_activation_disabled', 'Theme activation is disabled.', 403);
        }

        $stylesheet = $request->get_param('stylesheet');
        $theme = wp_get_theme($stylesheet);

        if (!$theme->exists()) {
            return $this->errorResponse('theme_not_found', 'Theme not found.', 404);
        }

        if ($theme->get_stylesheet() === get_option('stylesheet')) {
            return $this->successResponse(['activated' => false, 'already_active' => true]);
        }

        switch_theme($theme->get_stylesheet());

        $this->logMutation('activate_theme', $stylesheet, true, 200);
        return $this->successResponse(['activated' => true, 'theme' => $theme->get('Name')]);
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('switch_themes');
    }

    public function checkActivatePermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        
        if (!$this->config->allowThemeActivation()) {
            return new WP_Error('theme_activation_disabled', 'Theme activation is disabled.', ['status' => 403]);
        }
        
        return $this->checkCapability('switch_themes');
    }
}
