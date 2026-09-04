<?php

namespace WPForge\Elementor;

/**
 * Elementor service for detecting and interacting with Elementor
 */
class ElementorService
{
    private ?bool $isInstalled = null;
    private ?bool $isActive = null;
    private ?bool $isProInstalled = null;
    private ?bool $isProActive = null;
    private ?string $version = null;
    private ?string $proVersion = null;

    /**
     * Check if Elementor is installed
     */
    public function isInstalled(): bool
    {
        if (null === $this->isInstalled) {
            $this->isInstalled = defined('ELEMENTOR_VERSION');
        }
        return $this->isInstalled;
    }

    /**
     * Check if Elementor is active
     */
    public function isActive(): bool
    {
        if (null === $this->isActive) {
            $this->isActive = class_exists('\Elementor\Plugin');
        }
        return $this->isActive;
    }

    /**
     * Get Elementor version
     */
    public function getVersion(): ?string
    {
        if (!$this->isInstalled()) {
            return null;
        }
        
        if (null === $this->version) {
            $this->version = defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null;
        }
        
        return $this->version;
    }

    /**
     * Check if Elementor Pro is installed
     */
    public function isProInstalled(): bool
    {
        if (null === $this->isProInstalled) {
            $this->isProInstalled = defined('ELEMENTOR_PRO_VERSION');
        }
        return $this->isProInstalled;
    }

    /**
     * Check if Elementor Pro is active
     */
    public function isProActive(): bool
    {
        if (null === $this->isProActive) {
            $this->isProActive = class_exists('\ElementorPro\Plugin');
        }
        return $this->isProActive;
    }

    /**
     * Get Elementor Pro version
     */
    public function getProVersion(): ?string
    {
        if (!$this->isProInstalled()) {
            return null;
        }
        
        if (null === $this->proVersion) {
            $this->proVersion = defined('ELEMENTOR_PRO_VERSION') ? ELEMENTOR_PRO_VERSION : null;
        }
        
        return $this->proVersion;
    }

    /**
     * Get Elementor status information
     */
    public function getStatus(): array
    {
        return [
            'installed' => $this->isInstalled(),
            'active' => $this->isActive(),
            'version' => $this->getVersion(),
            'pro_installed' => $this->isProInstalled(),
            'pro_active' => $this->isProActive(),
            'pro_version' => $this->getProVersion(),
            'rest_namespace' => $this->isActive() ? 'elementor/v1' : null,
            'library_available' => $this->isActive() && post_type_exists('elementor_library'),
        ];
    }

    /**
     * Get Elementor documents (templates, pages built with Elementor)
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function getDocuments(array $args = []): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $defaults = [
            'post_type' => 'any',
            'posts_per_page' => 50,
            'paged' => 1,
            'meta_query' => [
                [
                    'key' => '_elementor_edit_mode',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $args = wp_parse_args($args, $defaults);
        $query = new \WP_Query($args);
        
        $documents = [];
        
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $doc = $this->getDocument($post_id);
            if ($doc) {
                $documents[] = $doc;
            }
        }
        
        wp_reset_postdata();
        
        return [
            'documents' => $documents,
            'total' => $query->found_posts,
            'page' => $args['paged'],
            'per_page' => $args['posts_per_page'],
        ];
    }

    /**
     * Get a specific Elementor document
     * 
     * @param int $post_id Post ID
     * @return array|null
     */
    public function getDocument(int $post_id): ?array
    {
        if (!$this->isActive()) {
            return null;
        }

        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // Check if this post has Elementor data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        
        if (empty($elementor_data) && empty($edit_mode)) {
            return null;
        }

        // Parse Elementor data
        $parsed_data = !empty($elementor_data) 
            ? json_decode($elementor_data, true) 
            : null;

        return [
            'id' => $post_id,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'edit_mode' => $edit_mode ?: 'builder',
            'has_elementor_data' => !empty($elementor_data),
            'elementor_data' => $parsed_data,
            'elementor_data_raw' => $elementor_data,
            'created_at' => $post->post_date_gmt,
            'modified_at' => $post->post_modified_gmt,
            'author' => $post->post_author,
            'permalink' => get_permalink($post_id),
            'thumbnail' => get_the_post_thumbnail_url($post_id),
        ];
    }

    /**
     * Get Elementor page content as sections/widgets structure
     * 
     * @param int $post_id Post ID
     * @return array|null
     */
    public function getPageContent(int $post_id): ?array
    {
        if (!$this->isActive()) {
            return null;
        }

        $document = $this->getDocument($post_id);
        if (!$document || empty($document['elementor_data'])) {
            return null;
        }

        return $this->parseElementorData($document['elementor_data']);
    }

    /**
     * Parse Elementor data into structured format
     * 
     * @param array $data Raw Elementor data
     * @return array
     */
    private function parseElementorData(array $data): array
    {
        $sections = [];
        
        foreach ($data as $element) {
            if (($element['elType'] ?? '') === 'section') {
                $sections[] = $this->parseSection($element);
            }
        }
        
        return [
            'sections' => $sections,
            'count' => count($sections),
        ];
    }

    /**
     * Parse a section element
     * 
     * @param array $element Section element data
     * @return array
     */
    private function parseSection(array $element): array
    {
        $columns = [];
        
        foreach ($element['elements'] ?? [] as $child) {
            if (($child['elType'] ?? '') === 'column') {
                $columns[] = $this->parseColumn($child);
            }
        }
        
        return [
            'id' => $element['id'] ?? null,
            'type' => 'section',
            'settings' => $element['settings'] ?? [],
            'columns' => $columns,
        ];
    }

    /**
     * Parse a column element
     * 
     * @param array $element Column element data
     * @return array
     */
    private function parseColumn(array $element): array
    {
        $widgets = [];
        
        foreach ($element['elements'] ?? [] as $child) {
            if (($child['elType'] ?? '') === 'widget') {
                $widgets[] = $this->parseWidget($child);
            }
        }
        
        return [
            'id' => $element['id'] ?? null,
            'type' => 'column',
            'settings' => $element['settings'] ?? [],
            'widgets' => $widgets,
        ];
    }

    /**
     * Parse a widget element
     * 
     * @param array $element Widget element data
     * @return array
     */
    private function parseWidget(array $element): array
    {
        return [
            'id' => $element['id'] ?? null,
            'type' => 'widget',
            'widget_type' => $element['widgetType'] ?? null,
            'settings' => $element['settings'] ?? [],
        ];
    }

    /**
     * Update an Elementor document
     * 
     * @param int $post_id Post ID
     * @param array $elementor_data Elementor data (JSON-decoded)
     * @param array $settings Optional settings
     * @return array|\WP_Error
     */
    public function updateDocument(int $post_id, array $elementor_data, array $settings = []): array|\WP_Error
    {
        if (!$this->isActive()) {
            return new \WP_Error(
                'elementor_not_active',
                'Elementor is not active.',
                ['status' => 400]
            );
        }

        $post = get_post($post_id);
        if (!$post) {
            return new \WP_Error(
                'post_not_found',
                'Post not found.',
                ['status' => 404]
            );
        }

        // Validate edit permissions
        if (!current_user_can('edit_post', $post_id)) {
            return new \WP_Error(
                'insufficient_permissions',
                'You do not have permission to edit this post.',
                ['status' => 403]
            );
        }

        // Encode the Elementor data
        $encoded_data = wp_json_encode($elementor_data, JSON_UNESCAPED_UNICODE);
        
        if ($encoded_data === false) {
            return new \WP_Error(
                'invalid_elementor_data',
                'Invalid Elementor data provided.',
                ['status' => 400]
            );
        }

        // Update post meta
        update_post_meta($post_id, '_elementor_data', $encoded_data);
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        update_post_meta($post_id, '_elementor_version', $this->getVersion());

        // Apply additional settings if provided
        foreach ($settings as $key => $value) {
            update_post_meta($post_id, '_' . $key, $value);
        }

        // Clear Elementor cache for this post
        $this->clearCache($post_id);

        return [
            'success' => true,
            'post_id' => $post_id,
            'updated_at' => current_time('mysql'),
        ];
    }

    /**
     * Create a new Elementor page
     * 
     * @param array $args Page arguments
     * @return array|\WP_Error
     */
    public function createPage(array $args): array|\WP_Error
    {
        if (!$this->isActive()) {
            return new \WP_Error(
                'elementor_not_active',
                'Elementor is not active.',
                ['status' => 400]
            );
        }

        $defaults = [
            'post_title' => 'Untitled',
            'post_content' => '',
            'post_status' => 'draft',
            'post_type' => 'page',
            'elementor_data' => [],
        ];

        $args = wp_parse_args($args, $defaults);

        // Insert the post
        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($args['post_title']),
            'post_content' => $args['post_content'],
            'post_status' => sanitize_key($args['post_status']),
            'post_type' => sanitize_key($args['post_type']),
        ]);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set Elementor edit mode
        update_post_meta($post_id, '_elementor_edit_mode', 'builder');
        update_post_meta($post_id, '_elementor_version', $this->getVersion());

        // Add Elementor data if provided
        if (!empty($args['elementor_data'])) {
            $encoded_data = wp_json_encode($args['elementor_data']);
            if ($encoded_data !== false) {
                update_post_meta($post_id, '_elementor_data', $encoded_data);
            }
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'permalink' => get_permalink($post_id),
        ];
    }

    /**
     * Get Elementor library templates
     * 
     * @param string $type Optional template type filter
     * @return array
     */
    public function getLibraryTemplates(?string $type = null): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $args = [
            'post_type' => 'elementor_library',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];

        if ($type) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'elementor_library_type',
                    'field' => 'slug',
                    'terms' => $type,
                ],
            ];
        }

        $query = new \WP_Query($args);
        $templates = [];

        while ($query->have_posts()) {
            $query->the_post();
            $templates[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'type' => get_post_meta(get_the_ID(), '_elementor_template_type', true),
                'author' => get_the_author_meta('display_name'),
            ];
        }

        wp_reset_postdata();

        return $templates;
    }

    /**
     * Clear Elementor cache for a post
     * 
     * @param int $post_id Post ID
     */
    public function clearCache(int $post_id): void
    {
        if (!$this->isActive()) {
            return;
        }

        // Clear post meta cache
        wp_cache_delete($post_id, 'post_meta');

        // If Elementor has a cache manager, use it
        if (class_exists('\Elementor\Plugin')) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache($post_id);
            } catch (\Exception $e) {
                // Ignore cache clearing errors
            }
        }

        // Regenerate CSS
        delete_post_meta($post_id, '_elementor_css');
    }

    /**
     * Check if a post is built with Elementor
     * 
     * @param int $post_id Post ID
     * @return bool
     */
    public function isBuiltWithElementor(int $post_id): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        return !empty($edit_mode);
    }

    /**
     * Get available Elementor widgets
     * 
     * @return array
     */
    public function getAvailableWidgets(): array
    {
        if (!$this->isActive()) {
            return [];
        }

        $widgets = [];
        
        try {
            $widget_registry = \Elementor\Plugin::$instance->widgets_manager;
            $widget_instances = $widget_registry->get_widget_types();
            
            foreach ($widget_instances as $widget) {
                $widgets[] = [
                    'name' => $widget->get_name(),
                    'title' => $widget->get_title(),
                    'category' => $widget->get_categories(),
                    'icon' => $widget->get_icon(),
                ];
            }
        } catch (\Exception $e) {
            // Return empty if we can't access widgets
        }
        
        return $widgets;
    }
}
