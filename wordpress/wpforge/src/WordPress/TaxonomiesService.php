<?php

namespace WPForge\WordPress;

/**
 * Taxonomies service
 */
class TaxonomiesService
{
    public function getTaxonomies(): array
    {
        $taxonomies = get_taxonomies([], 'objects');
        $result = [];
        
        foreach ($taxonomies as $taxonomy) {
            $result[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
            ];
        }
        
        return $result;
    }
    
    public function getTerms(string $taxonomy): array
    {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        
        if (is_wp_error($terms)) {
            return [];
        }
        
        $result = [];
        foreach ($terms as $term) {
            $result[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }
        
        return $result;
    }
}
