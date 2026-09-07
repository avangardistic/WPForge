<?php

namespace WPForge\WordPress;

/**
 * Post CRUD manager — create, read, update, delete posts of any type.
 */
class PostManager
{
    /**
     * List posts of a given type with pagination.
     */
    public function getPosts(string $postType = 'post', array $args = []): array
    {
        $defaults = [
            'posts_per_page' => 20,
            'paged'          => 1,
            'post_status'    => 'publish',
        ];

        $args = wp_parse_args($args, $defaults);
        $args['post_type'] = $postType;

        if (isset($args['s']) && $args['s'] !== '') {
            // Search is handled by WP_Query.
        }

        $query = new \WP_Query($args);

        $posts = [];
        foreach ($query->posts as $post) {
            $posts[] = $this->formatPost($post);
        }

        return [
            'posts'       => $posts,
            'total'       => (int) $query->found_posts,
            'page'        => (int) $args['paged'],
            'per_page'    => (int) $args['posts_per_page'],
            'total_pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * Get a single post by ID.
     */
    public function getPost(int $id): ?array
    {
        $post = get_post($id);
        if (!$post) {
            return null;
        }
        return $this->formatPost($post);
    }

    /**
     * Create a new post.
     */
    public function createPost(array $data): array
    {
        $defaults = [
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_title'   => 'Untitled',
            'post_content' => '',
        ];

        $data = wp_parse_args($data, $defaults);

        $postId = wp_insert_post([
            'post_type'    => sanitize_text_field($data['post_type']),
            'post_status'  => sanitize_text_field($data['post_status']),
            'post_title'   => sanitize_text_field($data['post_title']),
            'post_content' => wp_kses_post($data['post_content']),
            'post_author'  => get_current_user_id(),
            'post_excerpt' => isset($data['post_excerpt']) ? sanitize_textarea_field($data['post_excerpt']) : '',
        ]);

        if (is_wp_error($postId)) {
            throw new \RuntimeException($postId->get_error_message());
        }

        $this->setTermsAndMeta($postId, $data);

        $post = get_post($postId);
        return $this->formatPost($post);
    }

    /**
     * Update an existing post.
     */
    public function updatePost(int $id, array $data): array
    {
        $post = get_post($id);
        if (!$post) {
            throw new \RuntimeException('Post not found');
        }

        $updateData = ['ID' => $id];

        if (isset($data['post_title'])) {
            $updateData['post_title'] = sanitize_text_field($data['post_title']);
        }
        if (isset($data['post_content'])) {
            $updateData['post_content'] = wp_kses_post($data['post_content']);
        }
        if (isset($data['post_status'])) {
            $updateData['post_status'] = sanitize_text_field($data['post_status']);
        }
        if (isset($data['post_excerpt'])) {
            $updateData['post_excerpt'] = sanitize_textarea_field($data['post_excerpt']);
        }
        if (isset($data['post_name'])) {
            $updateData['post_name'] = sanitize_title($data['post_name']);
        }
        if (isset($data['menu_order'])) {
            $updateData['menu_order'] = (int) $data['menu_order'];
        }

        $result = wp_update_post($updateData, true);
        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }

        $this->setTermsAndMeta($id, $data);

        $post = get_post($id);
        return $this->formatPost($post);
    }

    /**
     * Delete a post (trash or force-delete).
     */
    public function deletePost(int $id, bool $force = false): bool
    {
        $result = wp_delete_post($id, $force);
        return $result !== null;
    }

    /* ------------------------------------------------------------------ */

    private function formatPost(\WP_Post $post): array
    {
        $thumbnailId = get_post_thumbnail_id($post->ID);
        return [
            'id'         => (int) $post->ID,
            'title'      => $post->post_title,
            'status'     => $post->post_status,
            'type'       => $post->post_type,
            'date'       => $post->post_date,
            'modified'   => $post->post_modified,
            'author'     => get_the_author_meta('display_name', $post->post_author),
            'author_id'  => (int) $post->post_author,
            'excerpt'    => $post->post_excerpt,
            'content'    => $post->post_content,
            'slug'       => $post->post_name,
            'permalink'  => get_permalink($post->ID),
            'thumbnail'  => $thumbnailId ? get_the_post_thumbnail_url($post->ID, 'full') : null,
            'thumbnail_id' => $thumbnailId ? (int) $thumbnailId : null,
            'meta'       => $this->getPostMeta($post->ID),
            'taxonomies' => $this->getPostTaxonomies($post->ID),
        ];
    }

    private function getPostMeta(int $id): array
    {
        $meta = get_post_meta($id);
        $result = [];

        foreach ($meta as $key => $value) {
            // Skip internal keys except a few useful ones.
            if (strpos($key, '_') === 0 && !in_array($key, ['_thumbnail_id'], true)) {
                continue;
            }
            $result[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
        }

        return $result;
    }

    private function getPostTaxonomies(int $id): array
    {
        $taxonomies = get_object_taxonomies(get_post_type($id), 'names');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($id, $taxonomy);
            if (is_wp_error($terms)) {
                $result[$taxonomy] = [];
                continue;
            }
            $result[$taxonomy] = array_map(fn($t) => [
                'id'   => (int) $t->term_id,
                'name' => $t->name,
                'slug' => $t->slug,
            ], $terms);
        }

        return $result;
    }

    private function setTermsAndMeta(int $postId, array $data): void
    {
        if (isset($data['taxonomies']) && is_array($data['taxonomies'])) {
            foreach ($data['taxonomies'] as $taxonomy => $terms) {
                wp_set_post_terms($postId, $terms, sanitize_text_field($taxonomy));
            }
        }

        if (isset($data['meta']) && is_array($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                update_post_meta($postId, sanitize_key($key), $value);
            }
        }
    }
}
