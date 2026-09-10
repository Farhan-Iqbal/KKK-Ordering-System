<?php
// engine/PhotoshopExporter.php

class PhotoshopExporter {

    public static function exportToCSV($jobsList) {
        $contract = require __DIR__ . '/../config/psd_contract.php';
        $headers = $contract['headers'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kkk_photoshop_merge_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Output UTF-8 BOM for Photoshop unicode character compatibility
        fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write generic header contract
        fputcsv($output, $headers);

        // Write row items
        foreach ($jobsList as $job) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = isset($job[$header]) ? $job[$header] : '';
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit();
    }
}