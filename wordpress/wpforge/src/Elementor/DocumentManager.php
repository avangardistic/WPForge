<?php

namespace WPForge\Elementor;

/**
 * Elementor document operations — list, get, create, update, delete documents.
 */
class DocumentManager
{
    private Adapter $adapter;

    public function __construct()
    {
        $this->adapter = new Adapter();
    }

    public function isAvailable(): bool
    {
        return $this->adapter->isAvailable();
    }

    /**
     * List Elementor documents with pagination.
     */
    public function listDocuments(array $args = []): array
    {
        $defaults = [
            'post_type'      => 'any',
            'posts_per_page' => 50,
            'paged'          => 1,
            'meta_query'     => [
                [
                    'key'     => '_elementor_edit_mode',
                    'value'   => 'builder',
                    'compare' => '=',
                ],
            ],
        ];

        $args = wp_parse_args($args, $defaults);

        if (!empty($args['search'])) {
            $args['s'] = $args['search'];
            unset($args['search']);
        }

        $query = new \WP_Query($args);
        $documents = [];

        foreach ($query->posts as $post) {
            $documents[] = $this->adapter->getDocument($post->ID);
        }

        return [
            'documents'    => array_filter($documents),
            'total'        => (int) $query->found_posts,
            'page'         => (int) $args['paged'],
            'per_page'     => (int) $args['posts_per_page'],
            'total_pages'  => (int) $query->max_num_pages,
        ];
    }

    /**
     * Get a single document.
     */
    public function getDocument(int $id): ?array
    {
        return $this->adapter->getDocument($id);
    }

    /**
     * Update a document.
     */
    public function updateDocument(int $id, array $data): array
    {
        return $this->adapter->updateDocument($id, $data);
    }

    /**
     * Delete a document.
     */
    public function deleteDocument(int $id, bool $force = false): bool
    {
        $result = wp_delete_post($id, $force);
        return $result !== null;
    }
}
