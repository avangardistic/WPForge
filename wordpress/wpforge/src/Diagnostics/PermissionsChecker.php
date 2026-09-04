<?php
namespace WPForge\Diagnostics;

/**
 * Check WordPress and filesystem permissions.
 */
class PermissionsChecker
{
    /**
     * Check user capabilities.
     */
    public function checkUserCapabilities(): array
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return ['authenticated' => false];
        }

        $caps = [
            'manage_options', 'edit_pages', 'publish_pages', 'delete_pages',
            'edit_others_pages', 'edit_posts', 'publish_posts', 'delete_posts',
            'upload_files', 'switch_themes', 'edit_themes', 'activate_plugins',
            'install_plugins', 'update_plugins', 'delete_plugins', 'edit_users',
            'delete_users', 'edit_files', 'unfiltered_html',
        ];

        $result = [];
        foreach ($caps as $cap) {
            $result[$cap] = current_user_can($cap);
        }

        return [
            'authenticated' => true,
            'user_id'       => (int) $user->ID,
            'roles'         => $user->roles,
            'capabilities'  => $result,
        ];
    }

    /**
     * Check if REST API is accessible.
     */
    public function checkRestApi(): array
    {
        $restUrl = rest_url('wpforge/v1/status');
        $isHttps = is_ssl();

        return [
            'available'  => class_exists('WP_REST_Server'),
            'rest_url'   => $restUrl,
            'is_https'   => $isHttps,
            'ssl_warning'=> !$isHttps ? 'HTTPS is recommended for API security.' : null,
        ];
    }

    /**
     * Check WPForge-specific permissions.
     */
    public function checkWPForgePermissions(): array
    {
        return [
            'can_manage_options'   => current_user_can('manage_options'),
            'can_edit_files'       => current_user_can('edit_files'),
            'can_edit_pages'       => current_user_can('edit_pages'),
            'can_publish_posts'    => current_user_can('publish_posts'),
            'can_upload_files'     => current_user_can('upload_files'),
            'is_administrator'     => in_array('administrator', wp_get_current_user()->roles ?? [], true),
        ];
    }
}
