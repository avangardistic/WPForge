<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Posts routes for WPForge API
 */
class PostsRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        // GET /posts - List posts
        register_rest_route($instance->namespace, '/posts', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPosts'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // GET /posts/{id} - Get single post
        register_rest_route($instance->namespace, '/posts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getPost'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        // POST /posts - Create post
        register_rest_route($instance->namespace, '/posts', [
            'methods' => 'POST',
            'callback' => [$instance, 'createPost'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        // PUT/PATCH /posts/{id} - Update post
        register_rest_route($instance->namespace, '/posts/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$instance, 'updatePost'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        // DELETE /posts/{id} - Delete post
        register_rest_route($instance->namespace, '/posts/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'deletePost'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);
    }

    public function getPosts(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => min((int) ($request->get_param('per_page') ?? 20), 100),
            'paged' => (int) ($request->get_param('page') ?? 1),
            'post_status' => $request->get_param('status') ?? 'any',
            'orderby' => $request->get_param('orderby') ?? 'date',
            'order' => $request->get_param('order') ?? 'DESC',
        ];

        $query = new \WP_Query($args);
        $posts = [];

        while ($query->have_posts()) {
            $query->the_post();
            $posts[] = $this->formatPost(get_post());
        }

        wp_reset_postdata();

        return $this->successResponse([
            'posts' => $posts,
            'total' => $query->found_posts,
            'page' => $args['paged'],
            'per_page' => $args['posts_per_page'],
            'total_pages' => $query->max_num_pages,
        ]);
    }

    public function getPost(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return $this->errorResponse('post_not_found', 'Post not found.', 404);
        }

        return $this->successResponse($this->formatPost($post));
    }

    public function createPost(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_title' => sanitize_text_field($request->get_param('title')),
            'post_content' => $request->get_param('content'),
            'post_excerpt' => sanitize_text_field($request->get_param('excerpt')),
            'post_status' => sanitize_key($request->get_param('status') ?? 'draft'),
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
            'post_category' => $request->get_param('categories') ?? [],
            'tags_input' => $request->get_param('tags') ?? [],
        ];

        $post_id = wp_insert_post($args, true);

        if (is_wp_error($post_id)) {
            $this->logMutation('create_post', 'post', false, 400, $post_id->get_error_code());
            return $this->errorResponse($post_id->get_error_code(), $post_id->get_error_message(), 400);
        }

        $this->logMutation('create_post', "post_{$post_id}", true, 201);
        
        return $this->successResponse($this->formatPost(get_post($post_id)), 201);
    }

    public function updatePost(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return $this->errorResponse('post_not_found', 'Post not found.', 404);
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot edit this post.', 403);
        }

        $args = [
            'ID' => $post_id,
        ];

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
        if ($request->get_param('categories')) {
            $args['post_category'] = $request->get_param('categories');
        }
        if ($request->get_param('tags')) {
            $args['tags_input'] = $request->get_param('tags');
        }

        $result = wp_update_post($args, true);

        if (is_wp_error($result)) {
            $this->logMutation('update_post', "post_{$post_id}", false, 400, $result->get_error_code());
            return $this->errorResponse($result->get_error_code(), $result->get_error_message(), 400);
        }

        $this->logMutation('update_post', "post_{$post_id}", true, 200);
        return $this->successResponse($this->formatPost(get_post($post_id)));
    }

    public function deletePost(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return $this->errorResponse('post_not_found', 'Post not found.', 404);
        }

        if (!current_user_can('delete_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot delete this post.', 403);
        }

        $force = $request->get_param('force') ?? false;
        $result = wp_delete_post($post_id, !$force);

        if (!$result) {
            $this->logMutation('delete_post', "post_{$post_id}", false, 500);
            return $this->errorResponse('delete_failed', 'Failed to delete post.', 500);
        }

        $this->logMutation('delete_post', "post_{$post_id}", true, 200);
        return $this->successResponse(['deleted' => true, 'post_id' => $post_id]);
    }

    private function formatPost(\WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'author' => $post->post_author,
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'categories' => wp_get_post_categories($post->ID, ['fields' => 'ids']),
            'tags' => wp_get_post_tags($post->ID, ['fields' => 'ids']),
            'featured_image' => get_post_thumbnail_id($post->ID),
            'permalink' => get_permalink($post->ID),
        ];
    }

    public function checkEditPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('edit_posts');
    }
}
