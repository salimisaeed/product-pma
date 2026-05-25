<?php
if (!defined('ABSPATH')) { exit; }

class PMA_AttributeService {
    public function managed_taxonomies(): array { return ['pa_sizes','pa_colors','pa_surfaces','pa_thickness','pa_effects','pa_applications','product_brand']; }
    public function list_terms(): array {
        $out = [];
        foreach ($this->managed_taxonomies() as $taxonomy) {
            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
            $out[$taxonomy] = is_wp_error($terms) ? [] : array_map(static fn($t) => ['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug], $terms);
        }
        return $out;
    }
}
