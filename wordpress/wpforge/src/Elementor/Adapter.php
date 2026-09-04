<?php
namespace WPForge\Elementor;

/**
 * Elementor adapter — detect Elementor, read/write documents.
 */
class Adapter
{
    private bool $available = false;
    private ?string $version = null;
    private bool $proAvailable = false;
    private ?string $proVersion = null;

    public function __construct()
    {
        $this->detect();
    }

    private function detect(): void
    {
        if (class_exists('\\Elementor\\Plugin')) {
            $this->available = true;
            $this->version = defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : null;

            if (class_exists('\\ElementorPro\\Plugin') && defined('ELEMENTOR_PRO_VERSION')) {
                $this->proAvailable = true;
                $this->proVersion = constant('ELEMENTOR_PRO_VERSION');
            }
        }
    }

    public function isAvailable(): bool { return $this->available; }
    public function isProAvailable(): bool { return $this->proAvailable; }
    public function getVersion(): ?string { return $this->version; }
    public function getProVersion(): ?string { return $this->proVersion; }

    /**
     * Get full capabilities report.
     */
    public function getCapabilities(): array
    {
        return [
            'available'   => $this->available,
            'version'     => $this->version,
            'pro'         => $this->proAvailable,
            'pro_version' => $this->proVersion,
            'rest_api'    => $this->detectRestCapabilities(),
            'library'     => post_type_exists('elementor_library'),
            'templates'   => class_exists('\\Elementor\\TemplateLibrary\\Source_Local'),
            'documents'   => class_exists('\\Elementor\\Core\\Documents_Manager'),
        ];
    }

    /**
     * Get an Elementor document (post with builder data).
     */
    public function getDocument(int $id): ?array
    {
        if (!$this->available) {
            return null;
        }

        $post = get_post($id);
        if (!$post) {
            return null;
        }

        $content = get_post_meta($id, '_elementor_data', true);
        $meta = get_post_meta($id);

        return [
            'id'            => (int) $post->ID,
            'title'         => $post->post_title,
            'type'          => $post->post_type,
            'status'        => $post->post_status,
            'date'          => $post->post_date,
            'modified'      => $post->post_modified,
            'elementor_data'=> $content ? json_decode($content, true) : null,
            'elementor_meta'=> $this->filterMeta($meta),
            'editor'        => get_post_meta($id, '_elementor_edit_mode', true) === 'builder',
            'version'       => get_post_meta($id, '_elementor_version', true),
            'slug'          => $post->post_name,
            'permalink'     => get_permalink($post->ID),
        ];
    }

    /**
     * Update an Elementor document.
     */
    public function updateDocument(int $id, array $data): array
    {
        if (!$this->available) {
            throw new \RuntimeException('Elementor is not available');
        }

        $backup = $this->getDocument($id);
        if (!$backup) {
            throw new \RuntimeException('Document not found');
        }

        $postData = ['ID' => $id];

        if (isset($data['title'])) {
            $postData['post_title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['status'])) {
            $postData['post_status'] = sanitize_text_field($data['status']);
        }
        if (isset($data['content'])) {
            $postData['post_content'] = wp_kses_post($data['content']);
        }

        if (count($postData) > 1) {
            $result = wp_update_post($postData, true);
            if (is_wp_error($result)) {
                throw new \RuntimeException($result->get_error_message());
            }
        }

        if (isset($data['elementor_data'])) {
            update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($data['elementor_data'])));
            update_post_meta($id, '_elementor_version', $this->version);
        }

        // Clear Elementor cache if available.
        if ($this->available && method_exists('\\Elementor\\Plugin::$instance->files_manager', 'clear_cache')) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Throwable $e) {
                // Non-critical — cache clear failed silently.
            }
        }

        $updated = $this->getDocument($id);

        return [
            'success' => true,
            'document'=> $updated,
            'backup'  => $backup,
            'changes' => $this->calculateChanges($backup, $updated),
        ];
    }

    /* ------------------------------------------------------------------ */

    private function filterMeta(array $meta): array
    {
        $allowed = [
            '_elementor_template_type',
            '_elementor_edit_mode',
            '_elementor_version',
            '_elementor_css',
            '_elementor_page_assets',
            '_elementor_controls_usage',
        ];

        $result = [];
        foreach ($allowed as $key) {
            if (isset($meta[$key])) {
                $result[$key] = is_array($meta[$key]) && count($meta[$key]) === 1
                    ? $meta[$key][0]
                    : $meta[$key];
            }
        }
        return $result;
    }

    private function calculateChanges(array $old, array $new): array
    {
        $changes = [];
        foreach (['title', 'status', 'modified'] as $field) {
            if (isset($old[$field], $new[$field]) && $old[$field] !== $new[$field]) {
                $changes[$field] = ['old' => $old[$field], 'new' => $new[$field]];
            }
        }
        return $changes;
    }

    private function detectRestCapabilities(): array
    {
        $caps = [];
        try {
            $routes = rest_get_server()->get_routes();
            foreach ($routes as $route => $handlers) {
                if (strpos($route, '/elementor/') !== false) {
                    $methods = [];
                    foreach ($handlers as $handler) {
                        if (isset($handler['methods'])) {
                            $methods = array_merge($methods, array_keys($handler['methods']));
                        }
                    }
                    $caps[$route] = array_unique($methods);
                }
            }
        } catch (\Throwable $e) {
            // REST server not available.
        }
        return $caps;
    }
}
