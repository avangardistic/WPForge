<?php
namespace WPForge\WordPress;

/**
 * Theme management service.
 */
class ThemeManager
{
    /**
     * List all installed themes.
     */
    public function getThemes(): array
    {
        $themes = wp_get_themes();
        $active = wp_get_theme();
        $result = [];

        foreach ($themes as $theme) {
            $isActive = $theme->get_stylesheet() === $active->get_stylesheet();
            $result[] = [
                'name'        => $theme->get('Name'),
                'version'     => $theme->get('Version'),
                'author'      => $theme->get('Author'),
                'description'=> $theme->get('Description'),
                'screenshot'  => $theme->get_screenshot(),
                'stylesheet'  => $theme->get_stylesheet(),
                'template'    => $theme->get_template(),
                'is_active'   => $isActive,
                'parent'      => $theme->parent() ? $theme->parent()->get('Name') : null,
                'tags'        => $theme->get('Tags') ?: [],
                'uri'         => $theme->get('ThemeURI'),
            ];
        }

        return $result;
    }

    /**
     * Get a single theme by stylesheet slug.
     */
    public function getTheme(string $stylesheet): ?array
    {
        $theme = wp_get_theme($stylesheet);
        if (!$theme->exists()) {
            return null;
        }

        $active = wp_get_theme();
        return [
            'name'        => $theme->get('Name'),
            'version'     => $theme->get('Version'),
            'author'      => $theme->get('Author'),
            'description'=> $theme->get('Description'),
            'screenshot'  => $theme->get_screenshot(),
            'stylesheet'  => $theme->get_stylesheet(),
            'template'    => $theme->get_template(),
            'is_active'   => $theme->get_stylesheet() === $active->get_stylesheet(),
            'parent'      => $theme->parent() ? [
                'name'    => $theme->parent()->get('Name'),
                'version' => $theme->parent()->get('Version'),
            ] : null,
            'tags'        => $theme->get('Tags') ?: [],
            'uri'         => $theme->get('ThemeURI'),
        ];
    }

    /**
     * Activate a theme.
     */
    public function activateTheme(string $stylesheet): bool
    {
        $theme = wp_get_theme($stylesheet);
        if (!$theme->exists()) {
            throw new \RuntimeException('Theme not found: ' . $stylesheet);
        }

        switch_theme($stylesheet);
        return wp_get_theme()->get_stylesheet() === $stylesheet;
    }

    /**
     * Get active theme info.
     */
    public function getActiveTheme(): array
    {
        $theme = wp_get_theme();
        return [
            'name'        => $theme->get('Name'),
            'version'     => $theme->get('Version'),
            'author'      => $theme->get('Author'),
            'stylesheet'  => $theme->get_stylesheet(),
            'template'    => $theme->get_template(),
        ];
    }
}
