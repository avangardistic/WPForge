<?php

namespace WPForge\WordPress;

/**
 * Posts service for WordPress post operations
 */
class PostsService
{
    public function getPosts(array $args = []): array
    {
        $defaults = [
            'post_type' => 'post',
            'posts_per_page' => 20,
            'paged' => 1,
        ];
        
        $args = wp_parse_args($args, $defaults);
        $query = new \WP_Query($args);
        
        $posts = [];
        while ($query->have_posts()) {
            $query->the_post();
            $posts[] = $this->formatPost(get_post());
        }
        
        wp_reset_postdata();
        
        return [
            'posts' => $posts,
            'total' => $query->found_posts,
            'page' => $args['paged'],
        ];
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
        ];
    }
}
