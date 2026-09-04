<?php
namespace WPForge\Auth;

/**
 * WordPress capability checker with optional custom capability maps.
 */
class CapabilityChecker
{
    /**
     * Check if the current user has a given capability (optionally for a specific object).
     */
    public static function check(string $capability, int $objectId = 0): bool
    {
        if ($objectId > 0) {
            return current_user_can($capability, $objectId);
        }
        return current_user_can($capability);
    }

    /**
     * Check multiple capabilities — returns true only if ALL are present.
     */
    public static function checkAll(array $capabilities, int $objectId = 0): bool
    {
        foreach ($capabilities as $cap) {
            if (!self::check($cap, $objectId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if the user can perform at least one of the listed capabilities.
     */
    public static function checkAny(array $capabilities, int $objectId = 0): bool
    {
        foreach ($capabilities as $cap) {
            if (self::check($cap, $objectId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a summary of the current user's relevant capabilities.
     */
    public static function getCapabilities(): array
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return [];
        }

        $relevant = [
            'manage_options', 'edit_pages', 'publish_pages', 'delete_pages',
            'edit_others_pages', 'edit_posts', 'publish_posts', 'delete_posts',
            'edit_others_posts', 'upload_files', 'switch_themes', 'edit_themes',
            'activate_plugins', 'install_plugins', 'update_plugins', 'delete_plugins',
            'edit_users', 'promote_users', 'delete_users', 'create_users',
            'unfiltered_html', 'edit_files',
        ];

        $caps = [];
        foreach ($relevant as $cap) {
            $caps[$cap] = current_user_can($cap);
        }
        return $caps;
    }

    /**
     * Get the user's roles.
     */
    public static function getRoles(): array
    {
        $user = wp_get_current_user();
        return $user->roles ?? [];
    }

    /**
     * Check if the user is an administrator.
     */
    public static function isAdmin(): bool
    {
        return in_array('administrator', self::getRoles(), true);
    }
}
