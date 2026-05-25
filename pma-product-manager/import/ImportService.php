<?php
if (!defined('ABSPATH')) { exit; }

class PMA_ImportService {
    public function __construct(private PMA_ProductService $product_service, private PMA_VariationService $variation_service, private PMA_AttributeService $attribute_service) {}

    public function validate_upload(array $file): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) { throw new Exception('Invalid upload'); }
        $work_dir = trailingslashit(wp_upload_dir()['basedir']) . 'pma-import-' . wp_generate_uuid4();
        wp_mkdir_p($work_dir);
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) { throw new Exception('Zip open failed'); }
        $zip->extractTo($work_dir);
        $zip->close();

        $validator = new PMA_Validator();
        $check = $validator->validate_paths($work_dir);
        if (!$check['ok']) { throw new Exception($check['message']); }

        $products = json_decode((string) file_get_contents($work_dir . '/data/products.json'), true) ?: [];
        $variations = json_decode((string) file_get_contents($work_dir . '/data/variations.json'), true) ?: [];
        $dry = (new PMA_DryRun())->preview($products, $variations);
        $token = wp_generate_uuid4();
        set_transient('pma_import_' . $token, ['dir'=>$work_dir,'products'=>$products,'variations'=>$variations,'log'=>[]], HOUR_IN_SECONDS);
        return ['ok'=>true,'token'=>$token,'dry_run'=>$dry];
    }

    public function commit_import(string $token, int $offset = 0, int $limit = 20): array {
        $state = get_transient('pma_import_' . $token);
        if (!$state) { throw new Exception('Import token expired'); }
        $products = $state['products'];
        $slice = array_slice($products, $offset, $limit);
        $log = $state['log'];
        foreach ($slice as $payload) {
            $result = $this->product_service->create_or_update((array)$payload);
            $log[] = ['entity'=>'product','mode'=>$result['mode'],'product_id'=>$result['product_id']];
        }
        $state['log'] = $log;
        set_transient('pma_import_' . $token, $state, HOUR_IN_SECONDS);
        return ['ok'=>true,'processed'=>count($slice),'next_offset'=>$offset+count($slice),'total'=>count($products),'done'=>($offset+count($slice))>=count($products),'log'=>$log];
    }
}
