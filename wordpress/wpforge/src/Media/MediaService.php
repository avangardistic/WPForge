<?php

namespace WPForge\Media;

/**
 * Media service
 */
class MediaService
{
    public function getMediaItems(array $args = []): array
    {
        $defaults = [
            'post_type' => 'attachment',
            'posts_per_page' => 20,
            'paged' => 1,
        ];
        
        $args = wp_parse_args($args, $defaults);
        $query = new \WP_Query($args);
        
        $items = [];
        while ($query->have_posts()) {
            $query->the_post();
            $items[] = $this->formatMedia(get_post());
        }
        
        wp_reset_postdata();
        
        return [
            'media' => $items,
            'total' => $query->found_posts,
        ];
    }
    
    private function formatMedia(\WP_Post $post): array
    {
        $meta = wp_get_attachment_metadata($post->ID);
        
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'url' => wp_get_attachment_url($post->ID),
            'mime_type' => $post->post_mime_type,
            'sizes' => $meta['sizes'] ?? [],
        ];
    }
}
