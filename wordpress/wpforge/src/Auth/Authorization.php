<?php

namespace WPForge\Auth;

/**
 * Authorization service for WPForge
 * 
 * Handles capability-based authorization using WordPress roles and capabilities.
 */
class Authorization
{
    /**
     * Check if current user has a specific capability
     * 
     * @param string $capability The capability to check
     * @param int|null $object_id Optional object ID for meta capabilities
     * @return bool
     */
    public function hasCapability(string $capability, ?int $object_id = null): bool
    {
        if ($object_id !== null) {
            return current_user_can($capability, $object_id);
        }
        
        return current_user_can($capability);
    }

    /**
     * Require a specific capability - throws exception if not authorized
     * 
     * @param string $capability The required capability
     * @param int|null $object_id Optional object ID for meta capabilities
     * @throws \WP_Error if not authorized
     */
    public function requireCapability(string $capability, ?int $object_id = null): void
    {
        if (!$this->hasCapability($capability, $object_id)) {
            throw new \WP_Error(
                'wpforge_insufficient_permissions',
                sprintf('You do not have permission to perform this action. Required capability: %s', $capability),
                ['status' => 403]
            );
        }
    }

    /**
     * Check if current user can edit a post/page
     */
    public function canEditPost(int $post_id): bool
    {
        return $this->hasCapability('edit_post', $post_id);
    }

    /**
     * Check if current user can delete a post/page
     */
    public function canDeletePost(int $post_id): bool
    {
        return $this->hasCapability('delete_post', $post_id);
    }

    /**
     * Check if current user can publish posts/pages
     */
    public function canPublishPosts(): bool
    {
        return $this->hasCapability('publish_posts');
    }

    /**
     * Check if current user can manage options (admin-level access)
     */
    public function canManageOptions(): bool
    {
        return $this->hasCapability('manage_options');
    }

    /**
     * Check if current user can install plugins
     */
    public function canInstallPlugins(): bool
    {
        return $this->hasCapability('install_plugins');
    }

    /**
     * Check if current user can activate plugins
     */
    public function canActivatePlugins(): bool
    {
        return $this->hasCapability('activate_plugins');
    }

    /**
     * Check if current user can manage themes
     */
    public function canManageThemes(): bool
    {
        return $this->hasCapability('switch_themes');
    }

    /**
     * Check if current user can edit theme files
     */
    public function canEditThemeFiles(): bool
    {
        return $this->hasCapability('edit_themes');
    }

    /**
     * Check if current user can upload files
     */
    public function canUploadFiles(): bool
    {
        return $this->hasCapability('upload_files');
    }

    /**
     * Check if current user can delete users
     */
    public function canDeleteUsers(): bool
    {
        return $this->hasCapability('delete_users');
    }

    /**
     * Check if current user can promote users
     */
    public function canPromoteUsers(): bool
    {
        return $this->hasCapability('promote_users');
    }

    /**
     * Check if current user can edit other users
     */
    public function canEditUsers(): bool
    {
        return $this->hasCapability('edit_users');
    }

    /**
     * Get all capabilities relevant for WPForge operations
     * 
     * @return array Array of capability => description
     */
    public function getRelevantCapabilities(): array
    {
        return [
            'manage_options' => 'Access to site settings and configuration',
            'edit_pages' => 'Edit pages',
            'publish_pages' => 'Publish pages',
            'edit_others_pages' => 'Edit pages created by others',
            'delete_pages' => 'Delete pages',
            'delete_others_pages' => 'Delete pages created by others',
            'edit_posts' => 'Edit posts',
            'publish_posts' => 'Publish posts',
            'edit_others_posts' => 'Edit posts created by others',
            'delete_posts' => 'Delete posts',
            'delete_others_posts' => 'Delete posts created by others',
            'upload_files' => 'Upload media files',
            'edit_theme_options' => 'Edit theme options',
            'switch_themes' => 'Switch themes',
            'edit_themes' => 'Edit theme files',
            'activate_plugins' => 'Activate/deactivate plugins',
            'install_plugins' => 'Install new plugins',
            'update_plugins' => 'Update plugins',
            'delete_plugins' => 'Delete plugins',
            'edit_users' => 'Edit users',
            'promote_users' => 'Change user roles',
            'delete_users' => 'Delete users',
            'create_users' => 'Create new users',
            'list_users' => 'List users',
            'unfiltered_html' => 'Post unfiltered HTML content',
            'unfiltered_upload' => 'Upload any file type',
        ];
    }

    /**
     * Check if current user is an administrator
     */
    public function isAdministrator(): bool
    {
        $user = wp_get_current_user();
        return in_array('administrator', $user->roles ?? [], true);
    }

    /**
     * Check if current user is a super admin (multisite)
     */
    public function isSuperAdmin(): bool
    {
        return is_super_admin(get_current_user_id());
    }

    /**
     * Get minimum capability required for an operation type
     * 
     * @param string $operation_type Type of operation
     * @return string|null Required capability or null if unknown
     */
    public function getRequiredCapability(string $operation_type): ?string
    {
        $capabilities = [
            // Read operations
            'read_site' => 'read',
            'read_posts' => 'read',
            'read_pages' => 'read',
            'read_media' => 'read',
            'read_users' => 'list_users',
            'read_plugins' => 'activate_plugins',
            'read_themes' => 'switch_themes',
            
            // Write operations
            'create_post' => 'publish_posts',
            'update_post' => 'edit_posts',
            'delete_post' => 'delete_posts',
            'create_page' => 'publish_pages',
            'update_page' => 'edit_pages',
            'delete_page' => 'delete_pages',
            'upload_media' => 'upload_files',
            'delete_media' => 'delete_posts',
            
            // High-risk operations
            'activate_plugin' => 'activate_plugins',
            'install_plugin' => 'install_plugins',
            'delete_plugin' => 'delete_plugins',
            'activate_theme' => 'switch_themes',
            'edit_theme_file' => 'edit_themes',
            'write_file' => 'edit_themes',
            'delete_file' => 'edit_themes',
            'database_write' => 'manage_options',
            'modify_settings' => 'manage_options',
            'create_user' => 'create_users',
            'update_user' => 'edit_users',
            'delete_user' => 'delete_users',
        ];
        
        return $capabilities[$operation_type] ?? null;
    }
}
