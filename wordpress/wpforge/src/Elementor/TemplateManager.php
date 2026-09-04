<?php
namespace WPForge\Elementor;

/**
 * Elementor template management — list, get, import templates.
 */
class TemplateManager
{
    private Adapter $adapter;

    public function __construct()
    {
        $this->adapter = new Adapter();
    }

    /**
     * List Elementor library templates.
     */
    public function listTemplates(array $args = []): array
    {
        $defaults = [
            'post_type'      => 'elementor_library',
            'posts_per_page' => 50,
            'paged'          => 1,
        ];

        $args = wp_parse_args($args, $defaults);

        if (!empty($args['type'])) {
            $args['meta_key'] = '_elementor_template_type';
            $args['meta_value'] = sanitize_text_field($args['type']);
            unset($args['type']);
        }

        $query = new \WP_Query($args);
        $templates = [];

        foreach ($query->posts as $post) {
            $templates[] = [
                'id'       => (int) $post->ID,
                'title'    => $post->post_title,
                'type'     => get_post_meta($post->ID, '_elementor_template_type', true),
                'status'   => $post->post_status,
                'source'   => get_post_meta($post->ID, '_elementor_template_source', true) ?: 'local',
                'date'     => $post->post_date,
                'modified' => $post->post_modified,
                'slug'     => $post->post_name,
            ];
        }

        return [
            'templates'   => $templates,
            'total'       => (int) $query->found_posts,
            'page'        => (int) $args['paged'],
            'per_page'    => (int) $args['posts_per_page'],
            'total_pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * Get a single template.
     */
    public function getTemplate(int $id): ?array
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'elementor_library') {
            return null;
        }

        $content = get_post_meta($id, '_elementor_data', true);

        return [
            'id'            => (int) $post->ID,
            'title'         => $post->post_title,
            'type'          => get_post_meta($post->ID, '_elementor_template_type', true),
            'status'        => $post->post_status,
            'source'        => get_post_meta($id, '_elementor_template_source', true) ?: 'local',
            'elementor_data'=> $content ? json_decode($content, true) : null,
            'date'          => $post->post_date,
            'modified'      => $post->post_modified,
        ];
    }
}
