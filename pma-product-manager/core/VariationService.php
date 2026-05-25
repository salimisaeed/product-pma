<?php
if (!defined('ABSPATH')) { exit; }

class PMA_VariationService {
    private PMA_ProductService $product_service;
    private const MANAGED_ATTRS = ['pa_sizes','pa_colors','pa_surfaces','pa_thickness','pa_effects','pa_applications'];
    public function __construct(PMA_ProductService $product_service) { $this->product_service = $product_service; }

    public function list_variations(int $product_id, int $limit = 50, int $page = 1): array {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) { return ['items'=>[], 'total'=>0]; }
        $children = $product->get_children();
        $ids = array_slice($children, max(0, ($page - 1) * $limit), $limit);
        $rows = [];
        foreach ($ids as $id) {
            $v = wc_get_product($id); if (!$v) { continue; }
            $attrs = $v->get_attributes();
            $rows[] = ['id'=>$v->get_id(),'product_id'=>$product_id,'size'=>$attrs['pa_sizes']??'','color'=>$attrs['pa_colors']??'','surface'=>$attrs['pa_surfaces']??'','thickness'=>$attrs['pa_thickness']??'','sku'=>$v->get_sku(),'price'=>$v->get_regular_price(),'stock'=>$v->get_stock_quantity()];
        }
        return ['items'=>$rows, 'total'=>count($children)];
    }

    public function update_inline(int $variation_id, array $data): array {
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) { throw new Exception('Variation not found'); }
        if (isset($data['price'])) { $variation->set_regular_price(wc_format_decimal($data['price'])); }
        if (isset($data['stock'])) { $variation->set_manage_stock(true); $variation->set_stock_quantity((int) $data['stock']); }
        if (isset($data['sku'])) { $variation->set_sku(sanitize_text_field($data['sku'])); }
        $variation->save();
        return ['id'=>$variation->get_id(),'sku'=>$variation->get_sku(),'price'=>$variation->get_regular_price(),'stock'=>$variation->get_stock_quantity()];
    }

    public function generate_variations_batch(int $product_id, array $attribute_terms, int $offset = 0, int $limit = 30): array {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) { throw new Exception('Variable product not found'); }

        $matrix = $this->sanitize_attribute_matrix($attribute_terms);
        $combinations = $this->cartesian_product($matrix);
        $total = count($combinations);
        $slice = array_slice($combinations, $offset, $limit);

        $existing = $this->existing_hashes($product);
        $created = 0;

        foreach ($slice as $combo) {
            $hash = $this->combo_hash($combo);
            if (isset($existing[$hash])) { continue; }
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $variation->set_attributes($combo);
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity(0);
            $variation->set_regular_price('0');
            $variation->save();
            if ($variation->get_sku() === '') {
                $variation->set_sku($this->build_variation_sku($product, $combo, $variation->get_id()));
                $variation->save();
            }
            $existing[$hash] = true;
            $created++;
        }

        return ['created' => $created, 'processed' => count($slice), 'next_offset' => $offset + count($slice), 'total' => $total, 'done' => ($offset + count($slice)) >= $total];
    }

    private function sanitize_attribute_matrix(array $matrix): array {
        $clean = [];
        foreach (self::MANAGED_ATTRS as $taxonomy) {
            if (empty($matrix[$taxonomy]) || !is_array($matrix[$taxonomy])) { continue; }
            $clean[$taxonomy] = array_values(array_filter(array_map('sanitize_title', $matrix[$taxonomy])));
        }
        return $clean;
    }

    private function cartesian_product(array $input): array {
        $result = [[]];
        foreach ($input as $taxonomy => $values) {
            $append = [];
            foreach ($result as $base) {
                foreach ($values as $value) { $append[] = array_merge($base, [$taxonomy => $value]); }
            }
            $result = $append;
        }
        return $result;
    }

    private function existing_hashes(WC_Product $product): array {
        $hashes = [];
        foreach ($product->get_children() as $child_id) {
            $v = wc_get_product($child_id); if (!$v) { continue; }
            $attrs = [];
            foreach (self::MANAGED_ATTRS as $t) { if (!empty($v->get_attributes()[$t])) { $attrs[$t] = sanitize_title($v->get_attributes()[$t]); } }
            $hashes[$this->combo_hash($attrs)] = true;
        }
        return $hashes;
    }

    private function combo_hash(array $combo): string { ksort($combo); return md5(wp_json_encode($combo)); }

    private function build_variation_sku(WC_Product $parent, array $combo, int $variation_id): string {
        $base = $parent->get_sku() ?: 'P' . $parent->get_id();
        $suffix = implode('-', array_map(static fn($v) => strtoupper(substr((string)$v, 0, 3)), array_values($combo)));
        return sanitize_text_field($base . '-' . $suffix . '-' . $variation_id);
    }
}
