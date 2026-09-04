<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Menus routes for WPForge API
 */
class MenusRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/menus', [
            'methods' => 'GET',
            'callback' => [$instance, 'getMenus'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);

        register_rest_route($instance->namespace, '/menus/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getMenu'],
            'permission_callback' => [$instance, 'checkPermission'],
        ]);
    }

    public function getMenus(): WP_REST_Response
    {
        $menus = wp_get_nav_menus();
        $locations = get_nav_menu_locations();
        
        $result = [];
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $result[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'locations' => array_keys(array_filter($locations, fn($loc) => $loc === $menu->term_id)),
                'item_count' => count($items ?? []),
                'items' => $this->formatMenuItems($items ?? []),
            ];
        }

        return $this->successResponse(['menus' => $result]);
    }

    public function getMenu(WP_REST_Request $request): WP_REST_Response
    {
        $menu_id = (int) $request->get_param('id');
        $menu = wp_get_nav_menu_object($menu_id);

        if (!$menu) {
            return $this->errorResponse('menu_not_found', 'Menu not found.', 404);
        }

        $items = wp_get_nav_menu_items($menu_id);

        return $this->successResponse([
            'id' => $menu->term_id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'item_count' => count($items ?? []),
            'items' => $this->formatMenuItems($items ?? []),
        ]);
    }

    private function formatMenuItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->ID,
                'title' => $item->title,
                'url' => $item->url,
                'type' => $item->type,
                'object' => $item->object,
                'parent' => $item->menu_item_parent,
                'order' => $item->menu_order,
                'target' => $item->target,
                'classes' => $item->classes,
            ];
        }
        return $result;
    }

    public function checkPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('edit_theme_options');
    }
}
