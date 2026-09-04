<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Taxonomies routes for WPForge API
 */
class TaxonomiesRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/taxonomies', [
            'methods' => 'GET',
            'callback' => [$instance, 'getTaxonomies'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/taxonomies/(?P<name>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getTaxonomy'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/taxonomies/(?P<taxonomy>[a-zA-Z0-9_-]+)/terms', [
            'methods' => 'GET',
            'callback' => [$instance, 'getTerms'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/taxonomies/(?P<taxonomy>[a-zA-Z0-9_-]+)/terms', [
            'methods' => 'POST',
            'callback' => [$instance, 'createTerm'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);
    }

    public function getTaxonomies(): WP_REST_Response
    {
        $taxonomies = get_taxonomies([], 'objects');
        $result = [];

        foreach ($taxonomies as $taxonomy) {
            $result[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'description' => $taxonomy->description,
                'public' => $taxonomy->public,
                'hierarchical' => $taxonomy->hierarchical,
                'object_types' => $taxonomy->object_type,
                'rest_base' => $taxonomy->rest_base ?? null,
            ];
        }

        return $this->successResponse(['taxonomies' => $result]);
    }

    public function getTaxonomy(WP_REST_Request $request): WP_REST_Response
    {
        $taxonomy = get_taxonomy($request->get_param('name'));
        
        if (!$taxonomy) {
            return $this->errorResponse('taxonomy_not_found', 'Taxonomy not found.', 404);
        }

        return $this->successResponse([
            'name' => $taxonomy->name,
            'label' => $taxonomy->label,
            'description' => $taxonomy->description,
            'public' => $taxonomy->public,
            'hierarchical' => $taxonomy->hierarchical,
            'object_types' => $taxonomy->object_type,
        ]);
    }

    public function getTerms(WP_REST_Request $request): WP_REST_Response
    {
        $taxonomy = sanitize_key($request->get_param('taxonomy'));
        
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            return $this->errorResponse($terms->get_error_code(), $terms->get_error_message(), 400);
        }

        $result = [];
        foreach ($terms as $term) {
            $result[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'count' => $term->count,
                'parent' => $term->parent,
            ];
        }

        return $this->successResponse(['terms' => $result]);
    }

    public function createTerm(WP_REST_Request $request): WP_REST_Response
    {
        $taxonomy = sanitize_key($request->get_param('taxonomy'));
        $name = sanitize_text_field($request->get_param('name'));
        $description = sanitize_textarea_field($request->get_param('description') ?? '');
        $parent = (int) ($request->get_param('parent') ?? 0);

        $result = wp_insert_term($name, $taxonomy, [
            'description' => $description,
            'parent' => $parent,
        ]);

        if (is_wp_error($result)) {
            $this->logMutation('create_term', $taxonomy, false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $term = get_term($result['term_id'], $taxonomy);
        
        $this->logMutation('create_term', $taxonomy, true, 201);
        return $this->successResponse([
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ], 201);
    }

    public function checkEditPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('manage_categories');
    }
}
