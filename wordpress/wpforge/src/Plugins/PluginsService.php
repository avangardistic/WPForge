<?php

namespace WPForge\Plugins;

/**
 * Plugins service
 */
class PluginsService
{
    public function getAllPlugins(): array
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
                'slug' => dirname($file),
                'name' => $data['Name'],
                'version' => $data['Version'],
                'active' => in_array($file, $active, true),
            ];
        }
        
        return $result;
    }
}
