<?php
if (!defined('ABSPATH')) { exit; }
class PMA_ProductGridUI {
    public function __construct() { add_action('admin_menu', [$this, 'register_menu']); add_action('admin_enqueue_scripts', [$this, 'enqueue']); }
    public function register_menu(): void { add_menu_page('PMA Product Manager','PMA Product Manager','manage_woocommerce','pma-product-manager',[$this,'render'],'dashicons-products',58); }
    public function enqueue(string $hook): void {
        if (strpos($hook, 'pma-product-manager') === false) { return; }
        wp_enqueue_style('pma-admin', PMA_PM_URL . 'assets/admin.css', [], '1.1.0');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('pma-admin', PMA_PM_URL . 'assets/admin.js', ['jquery','jquery-ui-sortable'], '1.1.0', true);
        wp_localize_script('pma-admin','PMA_PM',['ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('pma_pm_nonce')]);
    }
    public function render(): void {
        echo '<div class="wrap"><h1>PMA Product Manager</h1><div class="pma-toolbar"><input id="pma-search" placeholder="Search"><select id="pma-type"><option value="">All</option><option value="simple">Simple</option><option value="variable">Variable</option></select><button id="pma-refresh" class="button">Refresh</button></div><table class="wp-list-table widefat striped" id="pma-product-grid"><thead><tr><th></th><th>ID</th><th>Name</th><th>Type</th><th>Price</th><th>Stock</th><th>SKU</th><th>Brand</th><th>Variations</th></tr></thead><tbody></tbody></table><div id="pma-pagination"></div><h2>Variations</h2><div id="pma-variation-grid"></div></div>';
    }
}
