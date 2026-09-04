<?php
namespace WPForge\WordPress;

/**
 * Taxonomy CRUD manager — list, create, update, delete terms.
 */
class TaxonomyManager
{
    /**
     * List terms in a taxonomy.
     */
    public function getTerms(string $taxonomy, array $args = []): array
    {
        $defaults = [
            'hide_empty' => false,
            'per_page'   => 100,
            'offset'     => 0,
        ];

        $args = wp_parse_args($args, $defaults);
        $args['taxonomy'] = $taxonomy;

        $query = new \WP_Term_Query($args);
        $terms = [];

        foreach ($query->get_terms() as $term) {
            $terms[] = [
                'id'          => (int) $term->term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'description' => $term->description,
                'count'       => (int) $term->count,
                'parent'      => (int) $term->parent,
                'taxonomy'    => $term->taxonomy,
            ];
        }

        return [
            'terms'  => $terms,
            'total'  => count($terms),
            'taxonomy' => $taxonomy,
        ];
    }

    /**
     * Get a single term.
     */
    public function getTerm(string $taxonomy, int $termId): ?array
    {
        $term = get_term($termId, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return null;
        }

        return [
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => (int) $term->count,
            'parent'      => (int) $term->parent,
            'taxonomy'    => $term->taxonomy,
        ];
    }

    /**
     * Create a new term.
     */
    public function createTerm(string $taxonomy, array $data): array
    {
        $result = wp_insert_term(
            sanitize_text_field($data['name']),
            $taxonomy,
            [
                'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
                'slug'        => isset($data['slug']) ? sanitize_title($data['slug']) : '',
                'parent'      => isset($data['parent']) ? (int) $data['parent'] : 0,
            ]
        );

        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }

        return $this->getTerm($taxonomy, (int) $result['term_id']);
    }

    /**
     * Update a term.
     */
    public function updateTerm(string $taxonomy, int $termId, array $data): array
    {
        $args = [];
        if (isset($data['name'])) {
            $args['name'] = sanitize_text_field($data['name']);
        }
        if (isset($data['description'])) {
            $args['description'] = sanitize_textarea_field($data['description']);
        }
        if (isset($data['slug'])) {
            $args['slug'] = sanitize_title($data['slug']);
        }
        if (isset($data['parent'])) {
            $args['parent'] = (int) $data['parent'];
        }

        $result = wp_update_term($termId, $taxonomy, $args);

        if (is_wp_error($result)) {
            throw new \RuntimeException($result->get_error_message());
        }

        return $this->getTerm($taxonomy, $termId);
    }

    /**
     * Delete a term.
     */
    public function deleteTerm(string $taxonomy, int $termId): bool
    {
        $result = wp_delete_term($termId, $taxonomy);
        return !is_wp_error($result);
    }
}
