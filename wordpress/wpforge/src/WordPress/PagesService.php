<?php

namespace WPForge\WordPress;

/**
 * Pages service for WordPress page operations
 */
class PagesService
{
    public function getPages(array $args = []): array
    {
        $defaults = [
            'post_type' => 'page',
            'posts_per_page' => 20,
            'paged' => 1,
        ];
        
        $args = wp_parse_args($args, $defaults);
        $query = new \WP_Query($args);
        
        $pages = [];
        while ($query->have_posts()) {
            $query->the_post();
            $pages[] = $this->formatPage(get_post());
        }
        
        wp_reset_postdata();
        
        return [
            'pages' => $pages,
            'total' => $query->found_posts,
            'page' => $args['paged'],
        ];
    }
    
    private function formatPage(\WP_Post $post): array
    {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'parent' => $post->post_parent,
            'template' => get_page_template_slug($post->ID),
        ];
    }
}
