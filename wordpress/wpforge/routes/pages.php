<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Pages routes for WPForge API
 */
class PagesRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/pages', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPages'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/pages/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPage'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/pages', [
            'methods' => 'POST',
            'callback' => [$instance, 'createPage'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        register_rest_route($instance->namespace, '/pages/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$instance, 'updatePage'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        register_rest_route($instance->namespace, '/pages/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'deletePage'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);
    }

    public function getPages(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_type' => 'page',
            'posts_per_page' => min((int) ($request->get_param('per_page') ?? 20), 100),
            'paged' => (int) ($request->get_param('page') ?? 1),
            'post_status' => $request->get_param('status') ?? 'any',
        ];

        $query = new \WP_Query($args);
        $pages = [];

        while ($query->have_posts()) {
            $query->the_post();
            $pages[] = $this->formatPage(get_post());
        }

        wp_reset_postdata();

        return $this->successResponse([
            'pages' => $pages,
            'total' => $query->found_posts,
            'page' => $args['paged'],
            'per_page' => $args['posts_per_page'],
            'total_pages' => $query->max_num_pages,
        ]);
    }

    public function getPage(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'page') {
            return $this->errorResponse('page_not_found', 'Page not found.', 404);
        }

        return $this->successResponse($this->formatPage($post));
    }

    public function createPage(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_title' => sanitize_text_field($request->get_param('title')),
            'post_content' => $request->get_param('content'),
            'post_excerpt' => sanitize_text_field($request->get_param('excerpt')),
            'post_status' => sanitize_key($request->get_param('status') ?? 'draft'),
            'post_author' => get_current_user_id(),
            'post_type' => 'page',
            'post_parent' => (int) ($request->get_param('parent') ?? 0),
            'template' => sanitize_key($request->get_param('template') ?? ''),
        ];

        $post_id = wp_insert_post($args, true);

        if (is_wp_error($post_id)) {
            $this->logMutation('create_page', 'page', false, 400, $post_id->get_error_code());
            return $this->errorResponse($post_id->get_error_code(), $post_id->get_error_message(), 400);
        }

        if ($args['template']) {
            update_post_meta($post_id, '_wp_page_template', $args['template']);
        }

        $this->logMutation('create_page', "page_{$post_id}", true, 201);
        return $this->successResponse($this->formatPage(get_post($post_id)), 201);
    }

    public function updatePage(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'page') {
            return $this->errorResponse('page_not_found', 'Page not found.', 404);
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot edit this page.', 403);
        }

        $args = ['ID' => $post_id];

        if ($request->get_param('title')) {
            $args['post_title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->get_param('content')) {
            $args['post_content'] = $request->get_param('content');
        }
        if ($request->get_param('excerpt')) {
            $args['post_excerpt'] = sanitize_text_field($request->get_param('excerpt'));
        }
        if ($request->get_param('status')) {
            $args['post_status'] = sanitize_key($request->get_param('status'));
        }
        if ($request->get_param('parent')) {
            $args['post_parent'] = (int) $request->get_param('parent');
        }

        $result = wp_update_post($args, true);

        if (is_wp_error($result)) {
            $this->logMutation('update_page', "page_{$post_id}", false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        if ($request->get_param('template')) {
            update_post_meta($post_id, '_wp_page_template', sanitize_key($request->get_param('template')));
        }

        $this->logMutation('update_page', "page_{$post_id}", true, 200);
        return $this->successResponse($this->formatPage(get_post($post_id)));
    }

    public function deletePage(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'page') {
            return $this->errorResponse('page_not_found', 'Page not found.', 404);
        }

        if (!current_user_can('delete_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot delete this page.', 403);
        }

        $force = $request->get_param('force') ?? false;
        $result = wp_delete_post($post_id, !$force);

        if (!$result) {
            $this->logMutation('delete_page', "page_{$post_id}", false, 500);
            return $this->errorResponse('delete_failed', 'Failed to delete page.', 500);
        }

        $this->logMutation('delete_page', "page_{$post_id}", true, 200);
        return $this->successResponse(['deleted' => true, 'page_id' => $post_id]);
    }

    private function formatPage(\WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'author' => $post->post_author,
            'parent' => $post->post_parent,
            'template' => get_page_template_slug($post->ID),
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'permalink' => get_permalink($post->ID),
            'featured_image' => get_post_thumbnail_id($post->ID),
        ];
    }

    public function checkEditPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('edit_pages');
    }
}
