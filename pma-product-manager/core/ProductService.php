<?php
if (!defined('ABSPATH')) { exit; }

class PMA_ProductService {
    public function list_products(array $args = []): array {
        $defaults = ['limit' => 20, 'page' => 1, 'search' => '', 'type' => ''];
        $args = wp_parse_args($args, $defaults);
        $limit = max(1, min(50, (int) $args['limit']));
        $page = max(1, (int) $args['page']);
        $search = sanitize_text_field((string) $args['search']);
        $type = sanitize_key((string) $args['type']);

        $cache_key = 'pma_pm_products_' . md5(wp_json_encode([$limit, $page, $search, $type]));
        $cached = wp_cache_get($cache_key, 'pma_pm');
        if ($cached !== false) { return $cached; }

        $query_args = [
            'limit' => $limit,
            'page' => $page,
            'status' => ['publish', 'draft', 'private'],
            'return' => 'objects',
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'paginate' => true,
        ];
        if ($search !== '') { $query_args['s'] = $search; }
        if (in_array($type, ['simple', 'variable'], true)) { $query_args['type'] = $type; }

        $result = (new WC_Product_Query($query_args))->get_products();
        $items = array_map([$this, 'normalize_product'], $result->products);
        $payload = ['items' => $items, 'total' => (int) $result->total, 'pages' => (int) $result->max_num_pages, 'page' => $page];
        wp_cache_set($cache_key, $payload, 'pma_pm', 30);
        return $payload;
    }

    public function normalize_product(WC_Product $product): array {
        $brand = wp_get_post_terms($product->get_id(), 'product_brand', ['fields' => 'names']);
        return [
            'id' => $product->get_id(), 'name' => $product->get_name(), 'type' => $product->get_type(),
            'price' => $product->get_regular_price(), 'stock' => $product->get_stock_quantity(), 'sku' => $product->get_sku(),
            'brand' => is_wp_error($brand) ? '' : implode(', ', $brand), 'variation_count' => $product->is_type('variable') ? count($product->get_children()) : 0,
            'menu_order' => $product->get_menu_order(), 'weight' => $product->get_weight(), 'length' => $product->get_length(), 'width' => $product->get_width(), 'height' => $product->get_height(),
        ];
    }

    public function find_by_sku(string $sku): ?WC_Product {
        $sku = sanitize_text_field($sku);
        if ($sku === '') { return null; }
        $id = wc_get_product_id_by_sku($sku);
        return $id ? wc_get_product($id) : null;
    }

    public function update_inline(int $product_id, array $data): array {
        $product = wc_get_product($product_id);
        if (!$product) { throw new Exception('Product not found'); }
        if (isset($data['price'])) { $product->set_regular_price(wc_format_decimal($data['price'])); }
        if (isset($data['stock'])) { $product->set_manage_stock(true); $product->set_stock_quantity((int) $data['stock']); }
        if (isset($data['sku'])) { $product->set_sku(sanitize_text_field($data['sku'])); }
        if (isset($data['name'])) { $product->set_name(sanitize_text_field($data['name'])); }
        foreach (['weight','length','width','height'] as $dim) { if (isset($data[$dim])) { $product->{'set_' . $dim}(wc_format_decimal($data[$dim])); } }
        if (isset($data['shipping_class_id'])) { $product->set_shipping_class_id((int) $data['shipping_class_id']); }
        if (isset($data['menu_order'])) { $product->set_menu_order((int) $data['menu_order']); }
        $product->save();
        if (isset($data['brand'])) {
            wp_set_post_terms($product->get_id(), array_filter(array_map('sanitize_text_field', (array) $data['brand'])), 'product_brand', false);
        }
        return $this->normalize_product(wc_get_product($product_id));
    }

    public function create_or_update(array $payload): array {
        $sku = sanitize_text_field((string)($payload['sku'] ?? ''));
        $existing = $this->find_by_sku($sku);
        if ($existing instanceof WC_Product) {
            $updated = $this->update_inline($existing->get_id(), $payload);
            return ['mode' => 'update', 'product_id' => $existing->get_id(), 'product' => $updated];
        }
        $type = (($payload['type'] ?? 'simple') === 'variable') ? 'variable' : 'simple';
        $product = $type === 'variable' ? new WC_Product_Variable() : new WC_Product_Simple();
        $product->set_name(sanitize_text_field((string)($payload['name'] ?? 'New Product')));
        if ($sku !== '') { $product->set_sku($sku); }
        $product->set_status('publish');
        $product->save();
        $updated = $this->update_inline($product->get_id(), $payload);
        return ['mode' => 'create', 'product_id' => $product->get_id(), 'product' => $updated];
    }
}
