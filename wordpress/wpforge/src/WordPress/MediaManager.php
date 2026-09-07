<?php

namespace WPForge\WordPress;

/**
 * Media manager — upload, list, get, delete media attachments.
 */
class MediaManager
{
    /**
     * List media attachments.
     */
    public function getMedia(array $args = []): array
    {
        $defaults = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'paged'          => 1,
        ];

        $args = wp_parse_args($args, $defaults);

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = $this->formatMedia($post);
        }

        return [
            'media'       => $items,
            'total'       => (int) $query->found_posts,
            'page'        => (int) $args['paged'],
            'per_page'    => (int) $args['posts_per_page'],
            'total_pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * Get a single media item.
     */
    public function getMediaItem(int $id): ?array
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'attachment') {
            return null;
        }
        return $this->formatMedia($post);
    }

    /**
     * Upload a file from a URL.
     */
    public function uploadFromUrl(string $url, array $args = []): array
    {
        $fileArray = [
            'name'     => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => download_url($url),
        ];

        if (is_wp_error($fileArray['tmp_name'])) {
            throw new \RuntimeException($fileArray['tmp_name']->get_error_message());
        }

        $defaults = [
            'post_title'    => sanitize_text_field($args['title'] ?? pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME)),
            'post_content'  => '',
            'post_status'   => 'inherit',
            'post_mime_type' => wp_check_filetype(basename($url))['type'] ?? 'application/octet-stream',
        ];

        $attachmentId = media_handle_sideload($fileArray, 0, $defaults['post_title']);

        if (is_wp_error($attachmentId)) {
            @unlink($fileArray['tmp_name']);
            throw new \RuntimeException($attachmentId->get_error_message());
        }

        return $this->formatMedia(get_post($attachmentId));
    }

    /**
     * Delete a media attachment.
     */
    public function deleteMedia(int $id, bool $force = false): bool
    {
        $result = wp_delete_attachment($id, $force);
        return $result !== null;
    }

    /**
     * Get attachment metadata.
     */
    public function getMetadata(int $id): ?array
    {
        $meta = wp_get_attachment_metadata($id);
        if (!$meta) {
            return null;
        }

        return [
            'width'       => $meta['width'] ?? null,
            'height'      => $meta['height'] ?? null,
            'file'        => $meta['file'] ?? '',
            'sizes'       => $meta['sizes'] ?? [],
            'image_meta'  => $meta['image_meta'] ?? null,
        ];
    }

    /* ------------------------------------------------------------------ */

    private function formatMedia(\WP_Post $post): array
    {
        $meta = wp_get_attachment_metadata($post->ID);
        return [
            'id'          => (int) $post->ID,
            'title'       => $post->post_title,
            'filename'    => basename(get_attached_file($post->ID) ?: ''),
            'url'         => wp_get_attachment_url($post->ID),
            'type'        => $post->post_mime_type,
            'description' => $post->post_content,
            'caption'     => $post->post_excerpt,
            'date'        => $post->post_date,
            'modified'    => $post->post_modified,
            'author'      => (int) $post->post_author,
            'file_size'   => (int) ($meta['filesize'] ?? 0),
            'width'       => $meta['width'] ?? null,
            'height'      => $meta['height'] ?? null,
            'parent_id'   => (int) $post->post_parent,
            'alt_text'    => get_post_meta($post->ID, '_wp_attachment_image_alt', true) ?: '',
        ];
    }
}
