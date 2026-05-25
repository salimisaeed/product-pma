<?php
/**
 * Plugin Name: PMA Product Manager
 * Description: Admin-layer ERP for WooCommerce product, variation, attribute, export, and import management.
 * Version: 1.0.0
 * Author: PMA
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PMA_PM_PATH', plugin_dir_path(__FILE__));
define('PMA_PM_URL', plugin_dir_url(__FILE__));

require_once PMA_PM_PATH . 'core/ProductService.php';
require_once PMA_PM_PATH . 'core/VariationService.php';
require_once PMA_PM_PATH . 'core/AttributeService.php';
require_once PMA_PM_PATH . 'admin/ProductGridUI.php';
require_once PMA_PM_PATH . 'admin/VariationGridUI.php';
require_once PMA_PM_PATH . 'admin/AttributeUI.php';
require_once PMA_PM_PATH . 'api/AjaxController.php';
require_once PMA_PM_PATH . 'export/ExportService.php';
require_once PMA_PM_PATH . 'export/ZipBuilder.php';
require_once PMA_PM_PATH . 'import/Validator.php';
require_once PMA_PM_PATH . 'import/DryRun.php';
require_once PMA_PM_PATH . 'import/ImportService.php';

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        return;
    }

    $product_service = new PMA_ProductService();
    $variation_service = new PMA_VariationService($product_service);
    $attribute_service = new PMA_AttributeService();
    $export_service = new PMA_ExportService($product_service, $variation_service, $attribute_service);
    $import_service = new PMA_ImportService($product_service, $variation_service, $attribute_service);

    new PMA_ProductGridUI();
    new PMA_VariationGridUI();
    new PMA_AttributeUI();
    new PMA_AjaxController($product_service, $variation_service, $attribute_service, $export_service, $import_service);
});
