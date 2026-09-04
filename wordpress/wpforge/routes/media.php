<?php

namespace WPForge\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Media routes for WPForge API
 */
class MediaRoutes extends BaseRoutes
{
    public static function register(): void
    {
        $instance = new self();

        register_rest_route($instance->namespace, '/media', [
            'methods' => 'GET',
            'callback' => [$instance, 'getMedia'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/media/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$instance, 'getMediaItem'],
            'permission_callback' => [$instance, 'checkAuth'],
        ]);

        register_rest_route($instance->namespace, '/media', [
            'methods' => 'POST',
            'callback' => [$instance, 'uploadMedia'],
            'permission_callback' => [$instance, 'checkUploadPermission'],
        ]);

        register_rest_route($instance->namespace, '/media/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$instance, 'updateMedia'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);

        register_rest_route($instance->namespace, '/media/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$instance, 'deleteMedia'],
            'permission_callback' => [$instance, 'checkEditPermission'],
        ]);
    }

    public function getMedia(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => $request->get_param('mime_type') ?? 'any',
            'posts_per_page' => min((int) ($request->get_param('per_page') ?? 20), 100),
            'paged' => (int) ($request->get_param('page') ?? 1),
        ];

        $query = new \WP_Query($args);
        $media = [];

        while ($query->have_posts()) {
            $query->the_post();
            $media[] = $this->formatMedia(get_post());
        }

        wp_reset_postdata();

        return $this->successResponse([
            'media' => $media,
            'total' => $query->found_posts,
            'page' => $args['paged'],
            'per_page' => $args['posts_per_page'],
        ]);
    }

    public function getMediaItem(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'attachment') {
            return $this->errorResponse('media_not_found', 'Media item not found.', 404);
        }

        return $this->successResponse($this->formatMedia($post));
    }

    public function uploadMedia(WP_REST_Request $request): WP_REST_Response
    {
        $file = $request->get_file('file');
        $title = sanitize_text_field($request->get_param('title'));
        $alt = sanitize_text_field($request->get_param('alt'));
        $caption = sanitize_textarea_field($request->get_param('caption'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $parent_id = (int) ($request->get_param('parent_id') ?? 0);

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->errorResponse('upload_error', 'File upload failed.', 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload('file', $parent_id);

        if (is_wp_error($attachment_id)) {
            $this->logMutation('upload_media', $file['name'], false, 400, $attachment_id->get_error_code());
            return $this->errorResponse($attachment_id->get_error_code(), $attachment_id->get_error_message(), 400);
        }

        // Update metadata
        if ($title) {
            wp_update_post(['ID' => $attachment_id, 'post_title' => $title]);
        }
        if ($alt) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }
        if ($caption) {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => $caption]);
        }
        if ($description) {
            wp_update_post(['ID' => $attachment_id, 'post_content' => $description]);
        }

        $this->logMutation('upload_media', $file['name'], true, 201);
        return $this->successResponse($this->formatMedia(get_post($attachment_id)), 201);
    }

    public function updateMedia(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'attachment') {
            return $this->errorResponse('media_not_found', 'Media item not found.', 404);
        }

        if (!current_user_can('edit_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot edit this media.', 403);
        }

        $args = ['ID' => $post_id];

        if ($request->get_param('title')) {
            $args['post_title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->get_param('alt')) {
            update_post_meta($post_id, '_wp_attachment_image_alt', sanitize_text_field($request->get_param('alt')));
        }
        if ($request->get_param('caption')) {
            $args['post_excerpt'] = sanitize_textarea_field($request->get_param('caption'));
        }
        if ($request->get_param('description')) {
            $args['post_content'] = sanitize_textarea_field($request->get_param('description'));
        }

        wp_update_post($args);

        $this->logMutation('update_media', "media_{$post_id}", true, 200);
        return $this->successResponse($this->formatMedia(get_post($post_id)));
    }

    public function deleteMedia(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'attachment') {
            return $this->errorResponse('media_not_found', 'Media item not found.', 404);
        }

        if (!current_user_can('delete_post', $post_id)) {
            return $this->errorResponse('insufficient_permissions', 'You cannot delete this media.', 403);
        }

        $force = $request->get_param('force') ?? false;
        $result = wp_delete_attachment($post_id, !$force);

        if (!$result) {
            $this->logMutation('delete_media', "media_{$post_id}", false, 500);
            return $this->errorResponse('delete_failed', 'Failed to delete media.', 500);
        }

        $this->logMutation('delete_media', "media_{$post_id}", true, 200);
        return $this->successResponse(['deleted' => true, 'media_id' => $post_id]);
    }

    private function formatMedia(\WP_Post $post): array
    {
        $meta = wp_get_attachment_metadata($post->ID);
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'alt' => get_post_meta($post->ID, '_wp_attachment_image_alt', true),
            'caption' => $post->post_excerpt,
            'description' => $post->post_content,
            'mime_type' => $post->post_mime_type,
            'url' => wp_get_attachment_url($post->ID),
            'sizes' => $meta['sizes'] ?? [],
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'filesize' => $meta['filesize'] ?? null,
            'author' => $post->post_author,
            'date' => $post->post_date_gmt,
            'parent' => $post->post_parent,
        ];
    }

    public function checkUploadPermission(): bool|WP_Error
    {
        $auth = $this->checkAuth();
        if (is_wp_error($auth)) {
            return $auth;
        }
        return $this->checkCapability('upload_files');
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
