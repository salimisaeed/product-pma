<?php
if (!defined('ABSPATH')) { exit; }
class PMA_AjaxController {
    public function __construct(private PMA_ProductService $product_service, private PMA_VariationService $variation_service, private PMA_AttributeService $attribute_service, private PMA_ExportService $export_service, private PMA_ImportService $import_service) {
        foreach (['list_products','update_product','list_variations','update_variation','generate_variations','reorder_products','export','import_validate','import_commit'] as $action) { add_action('wp_ajax_pma_pm_' . $action, [$this, $action]); }
    }
    private function auth(): void { check_ajax_referer('pma_pm_nonce', 'nonce'); if (!current_user_can('manage_woocommerce')) { wp_send_json_error(['message'=>'Forbidden'],403); } }
    public function list_products(): void { $this->auth(); wp_send_json_success($this->product_service->list_products(['limit'=>(int)($_POST['limit']??20),'page'=>(int)($_POST['page']??1),'search'=>sanitize_text_field($_POST['search']??''),'type'=>sanitize_text_field($_POST['type']??'')])); }
    public function update_product(): void { $this->auth(); wp_send_json_success($this->product_service->update_inline((int)$_POST['id'], (array)($_POST['data'] ?? []))); }
    public function reorder_products(): void { $this->auth(); $rows=(array)($_POST['rows']??[]); foreach($rows as $r){ $this->product_service->update_inline((int)$r['id'], ['menu_order'=>(int)$r['menu_order']]); } wp_send_json_success(['ok'=>true]); }
    public function list_variations(): void { $this->auth(); wp_send_json_success($this->variation_service->list_variations((int)$_POST['product_id'], (int)($_POST['limit']??50), (int)($_POST['page']??1))); }
    public function update_variation(): void { $this->auth(); wp_send_json_success($this->variation_service->update_inline((int)$_POST['id'], (array)($_POST['data'] ?? []))); }
    public function generate_variations(): void { $this->auth(); wp_send_json_success($this->variation_service->generate_variations_batch((int)$_POST['product_id'], (array)($_POST['attributes']??[]), (int)($_POST['offset']??0), (int)($_POST['limit']??30))); }
    public function export(): void { $this->auth(); wp_send_json_success($this->export_service->export_all()); }
    public function import_validate(): void { $this->auth(); wp_send_json_success($this->import_service->validate_upload($_FILES['zip_file'] ?? [])); }
    public function import_commit(): void { $this->auth(); wp_send_json_success($this->import_service->commit_import(sanitize_text_field($_POST['token'] ?? ''), (int)($_POST['offset']??0), (int)($_POST['limit']??20))); }
}
