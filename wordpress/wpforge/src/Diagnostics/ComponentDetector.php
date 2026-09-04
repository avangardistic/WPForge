<?php
namespace WPForge\Diagnostics;

/**
 * Detect installed WordPress components (themes, plugins, page builders).
 */
class ComponentDetector
{
    /**
     * Detect all major components.
     */
    public function detectAll(): array
    {
        return [
            'elementor'    => $this->detectElementor(),
            'themes'       => $this->detectThemes(),
            'plugins'      => $this->detectActivePlugins(),
            'caching'      => $this->detectCachingPlugins(),
            'seo'          => $this->detectSEOPlugins(),
            'security'     => $this->detectSecurityPlugins(),
            'forms'        => $this->detectFormPlugins(),
        ];
    }

    /**
     * Detect Elementor.
     */
    public function detectElementor(): array
    {
        if (!class_exists('\\Elementor\\Plugin')) {
            return ['installed' => false];
        }

        return [
            'installed'   => true,
            'active'      => did_action('elementor/loaded') > 0,
            'version'     => defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null,
            'pro'         => class_exists('\\ElementorPro\\Plugin'),
            'pro_version' => defined('ELEMENTOR_PRO_VERSION') ? constant('ELEMENTOR_PRO_VERSION') : null,
        ];
    }

    /**
     * Detect active themes.
     */
    public function detectThemes(): array
    {
        $active = wp_get_theme();
        return [
            'active' => [
                'name'    => $active->get('Name'),
                'version' => $active->get('Version'),
                'parent'  => $active->parent() ? $active->parent()->get('Name') : null,
            ],
            'installed_count' => count(wp_get_themes()),
        ];
    }

    /**
     * Detect active plugins.
     */
    public function detectActivePlugins(): array
    {
        $activePlugins = get_option('active_plugins', []);
        $allPlugins = get_plugins();
        $result = [];

        foreach ($activePlugins as $path) {
            if (isset($allPlugins[$path])) {
                $result[] = [
                    'name'    => $allPlugins[$path]['Name'] ?? '',
                    'version' => $allPlugins[$path]['Version'] ?? '',
                    'path'    => $path,
                ];
            }
        }

        return $result;
    }

    public function detectCachingPlugins(): array
    {
        return $this->detectPluginCategory(['wp-super-cache', 'w3-total-cache', 'wp-rocket', 'litespeed-cache', 'autoptimize']);
    }

    public function detectSEOPlugins(): array
    {
        return $this->detectPluginCategory(['wordpress-seo', 'all-in-one-seo-pack', 'seopress']);
    }

    public function detectSecurityPlugins(): array
    {
        return $this->detectPluginCategory(['wordfence', 'sucuri-scanner', 'ithemes-security', 'all-in-one-wp-security-and-firewall']);
    }

    public function detectFormPlugins(): array
    {
        return $this->detectPluginCategory(['contact-form-7', 'wpforms-lite', 'gravityforms', 'ninja-forms']);
    }

    private function detectPluginCategory(array $slugs): array
    {
        $active = get_option('active_plugins', []);
        $allPlugins = get_plugins();
        $found = [];

        foreach ($active as $path) {
            $slug = dirname($path);
            if (in_array($slug, $slugs, true) && isset($allPlugins[$path])) {
                $found[] = $allPlugins[$path]['Name'] ?? $slug;
            }
        }

        return $found;
    }
}
