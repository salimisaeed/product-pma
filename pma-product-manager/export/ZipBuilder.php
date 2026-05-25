<?php
if (!defined('ABSPATH')) { exit; }

class PMA_ZipBuilder {
    private ZipArchive $zip;
    public function open(string $zip_path): void {
        $this->zip = new ZipArchive();
        if ($this->zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new Exception('Cannot create ZIP'); }
    }
    public function add_json_line(string $path, array $rows): void {
        $tmp = wp_tempnam($path);
        $h = fopen($tmp, 'wb');
        fwrite($h, "[\n");
        $first = true;
        foreach ($rows as $row) {
            if (!$first) { fwrite($h, ",\n"); }
            fwrite($h, wp_json_encode($row));
            $first = false;
        }
        fwrite($h, "\n]\n");
        fclose($h);
        $this->zip->addFile($tmp, $path);
    }
    public function add_file(string $src, string $dest): void { if (file_exists($src)) { $this->zip->addFile($src, $dest); } }
    public function close(): void { $this->zip->close(); }
}
