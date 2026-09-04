<?php

namespace WPForge\Themes;

/**
 * Themes service
 */
class ThemesService
{
    public function getAllThemes(): array
    {
        $themes = wp_get_themes();
        $active = get_option('stylesheet');
        $result = [];
        
        foreach ($themes as $theme) {
            $result[] = [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'author' => $theme->get('Author'),
                'stylesheet' => $theme->get_stylesheet(),
                'is_active' => $theme->get_stylesheet() === $active,
            ];
        }
        
        return $result;
    }
    
    public function getActiveTheme(): array
    {
        $theme = wp_get_theme();
        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'stylesheet' => $theme->get_stylesheet(),
        ];
    }
}
