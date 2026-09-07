<?php

namespace WPForge\WordPress;

/**
 * Plugin management service — list, activate, deactivate, install.
 */
class PluginManager
{
    /**
     * List all installed plugins with active state.
     */
    public function getPlugins(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active  = get_option('active_plugins', []);
        $result  = [];

        foreach ($plugins as $path => $plugin) {
            $slug = dirname($path);
            $result[] = [
                'name'        => $plugin['Name'] ?? '',
                'slug'        => $slug,
                'version'     => $plugin['Version'] ?? '',
                'author'      => $plugin['Author'] ?? '',
                'description' => $plugin['Description'] ?? '',
                'path'        => $path,
                'is_active'   => in_array($path, $active) || is_plugin_active($path),
                'requires_wp' => $plugin['RequiresWP'] ?? '',
                'requires_php' => $plugin['RequiresPHP'] ?? '',
                'text_domain' => $plugin['TextDomain'] ?? '',
                'update_uri'  => $plugin['UpdateURI'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Get info about a single plugin.
     */
    public function getPlugin(string $pluginPath): ?array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        if (!isset($plugins[$pluginPath])) {
            return null;
        }

        $plugin = $plugins[$pluginPath];
        return [
            'name'        => $plugin['Name'] ?? '',
            'slug'        => dirname($pluginPath),
            'version'     => $plugin['Version'] ?? '',
            'author'      => $plugin['Author'] ?? '',
            'description' => $plugin['Description'] ?? '',
            'path'        => $pluginPath,
            'is_active'   => is_plugin_active($pluginPath),
            'requires_wp' => $plugin['RequiresWP'] ?? '',
            'requires_php' => $plugin['RequiresPHP'] ?? '',
            'text_domain' => $plugin['TextDomain'] ?? '',
        ];
    }

    /**
     * Activate a plugin.
     */
    public function activatePlugin(string $pluginPath): bool
    {
        if (!is_plugin_active($pluginPath)) {
            $result = activate_plugin($pluginPath);
            if (is_wp_error($result)) {
                throw new \RuntimeException($result->get_error_message());
            }
        }
        return is_plugin_active($pluginPath);
    }

    /**
     * Deactivate a plugin.
     */
    public function deactivatePlugin(string $pluginPath): bool
    {
        if (is_plugin_active($pluginPath)) {
            deactivate_plugins($pluginPath);
        }
        return !is_plugin_active($pluginPath);
    }

    /**
     * Get available plugin updates.
     */
    public function getUpdates(): array
    {
        $updates = get_plugin_updates();
        $result = [];

        foreach ($updates as $path => $update) {
            $result[] = [
                'slug'          => dirname($path),
                'new_version'   => $update->update->new_version ?? '',
                'url'           => $update->update->url ?? '',
                'package'       => $update->update->package ?? '',
                'upgrade_notice' => $update->update->upgrade_notice ?? '',
            ];
        }

        return $result;
    }
}
