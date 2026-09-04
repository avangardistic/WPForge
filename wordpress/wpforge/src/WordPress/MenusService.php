<?php

namespace WPForge\WordPress;

/**
 * Menus service
 */
class MenusService
{
    public function getMenus(): array
    {
        $menus = wp_get_nav_menus();
        $result = [];
        
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $result[] = [
                'id' => $menu->term_id,
                'name' => $menu->name,
                'item_count' => count($items ?? []),
            ];
        }
        
        return $result;
    }
}
