<?php
if (!defined('ABSPATH')) { exit; }

class PMA_DryRun {
    public function preview(array $products, array $variations): array {
        $creates = 0; $updates = 0;
        foreach ($products as $p) {
            $sku = sanitize_text_field((string)($p['sku'] ?? ''));
            if ($sku !== '' && wc_get_product_id_by_sku($sku)) { $updates++; } else { $creates++; }
        }
        return ['products_to_create'=>$creates,'products_to_update'=>$updates,'variations_to_process'=>count($variations),'will_delete_existing'=>false];
    }
}
