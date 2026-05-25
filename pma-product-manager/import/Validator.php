<?php
if (!defined('ABSPATH')) { exit; }

class PMA_Validator {
    public function validate_paths(string $dir): array {
        foreach (['data/products.json', 'data/variations.json', 'data/attributes.json'] as $req) {
            if (!file_exists(trailingslashit($dir) . $req)) { return ['ok'=>false,'message'=>'Missing ' . $req]; }
        }
        return ['ok'=>true,'message'=>'ok'];
    }
}
