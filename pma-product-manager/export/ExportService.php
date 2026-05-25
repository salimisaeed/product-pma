<?php
if (!defined('ABSPATH')) { exit; }

class PMA_ExportService {
    public function __construct(private PMA_ProductService $product_service, private PMA_VariationService $variation_service, private PMA_AttributeService $attribute_service) {}
    public function export_all(): array {
        $upload = wp_upload_dir();
        $zip_path = trailingslashit($upload['basedir']) . 'pma-products-' . gmdate('Ymd-His') . '.zip';
        $zip = new PMA_ZipBuilder();
        $zip->open($zip_path);

        $products = [];
        $variations = [];
        $media_ids = [];
        $page = 1;
        do {
            $batch = $this->product_service->list_products(['limit' => 50, 'page' => $page]);
            foreach ($batch['items'] as $p) {
                $products[] = $p;
                $wc_product = wc_get_product((int)$p['id']);
                if ($wc_product && $wc_product->get_image_id()) { $media_ids[$wc_product->get_image_id()] = true; }
                if ($wc_product && $wc_product->is_type('variable')) {
                    $vPage = 1;
                    do {
                        $vBatch = $this->variation_service->list_variations($wc_product->get_id(), 50, $vPage);
                        $variations = array_merge($variations, $vBatch['items']);
                        $vPage++;
                    } while (count($vBatch['items']) === 50);
                }
            }
            $page++;
        } while ($page <= $batch['pages']);

        $zip->add_json_line('data/products.json', $products);
        $zip->add_json_line('data/variations.json', $variations);
        $zip->add_json_line('data/attributes.json', [$this->attribute_service->list_terms()]);

        foreach (array_keys($media_ids) as $id) {
            $path = get_attached_file((int)$id);
            if ($path && file_exists($path)) { $zip->add_file($path, 'media/' . basename($path)); }
        }
        $zip->close();
        return ['file'=>$zip_path,'url'=>trailingslashit($upload['baseurl']) . basename($zip_path),'products'=>count($products),'variations'=>count($variations),'media'=>count($media_ids)];
    }
}
