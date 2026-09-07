<?php

namespace WPForge\WordPress;

/**
 * Navigation menu manager.
 */
class MenuManager
{
    /**
     * List all menus with their locations.
     */
    public function getMenus(): array
    {
        $menus    = wp_get_nav_menus();
        $locations = get_nav_menu_locations();
        $result   = [];

        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $locationName = null;
            foreach ($locations as $loc => $menuId) {
                if ($menuId == $menu->term_id) {
                    $locationName = $loc;
                    break;
                }
            }

            $result[] = [
                'id'       => (int) $menu->term_id,
                'name'     => $menu->name,
                'slug'     => $menu->slug,
                'count'    => $items ? count($items) : 0,
                'location' => $locationName,
                'items'    => $this->formatMenuItems($items),
            ];
        }

        return $result;
    }

    /**
     * Get a single menu.
     */
    public function getMenu(int $menuId): ?array
    {
        $menu = wp_get_nav_menu_object($menuId);
        if (!$menu) {
            return null;
        }

        $items     = wp_get_nav_menu_items($menuId);
        $locations = get_nav_menu_locations();
        $locationName = null;
        foreach ($locations as $loc => $menuId2) {
            if ($menuId2 == $menuId) {
                $locationName = $loc;
                break;
            }
        }

        return [
            'id'       => (int) $menu->term_id,
            'name'     => $menu->name,
            'slug'     => $menu->slug,
            'count'    => $items ? count($items) : 0,
            'location' => $locationName,
            'items'    => $this->formatMenuItems($items),
        ];
    }

    /**
     * Get available menu locations.
     */
    public function getLocations(): array
    {
        $locations = get_registered_nav_menus();
        $assigned  = get_nav_menu_locations();
        $result    = [];

        foreach ($locations as $slug => $label) {
            $result[$slug] = [
                'label'     => $label,
                'menu_id'   => $assigned[$slug] ?? null,
                'menu_name' => null,
            ];
            if (isset($assigned[$slug]) && $assigned[$slug]) {
                $menuObj = wp_get_nav_menu_object($assigned[$slug]);
                if ($menuObj) {
                    $result[$slug]['menu_name'] = $menuObj->name;
                }
            }
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */

    private function formatMenuItems(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id'        => (int) $item->ID,
                'title'     => $item->title,
                'url'       => $item->url,
                'target'    => $item->target,
                'classes'   => $item->classes,
                'menu_order' => (int) $item->menu_order,
                'parent'    => (int) $item->menu_item_parent,
                'type'      => $item->type,
                'object'    => $item->object,
                'object_id' => (int) $item->object_id,
            ];
        }

        return $result;
    }
}
