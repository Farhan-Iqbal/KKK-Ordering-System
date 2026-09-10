<?php
// api/export_merge.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../engine/PhotoshopExporter.php';

try {
    $db = Database::getConnection();

    // Fetch queued or pending photoshop merge jobs
    $orderId = $_GET['order_id'] ?? null;

    if ($orderId) {
        $stmt = $db->prepare("SELECT * FROM photoshop_merge_jobs WHERE order_id = :order_id ORDER BY id ASC");
        $stmt->execute([':order_id' => $orderId]);
    } else {
        $stmt = $db->query("SELECT * FROM photoshop_merge_jobs ORDER BY created_at DESC LIMIT 100");
    }

    $jobs = $stmt->fetchAll();

    if (empty($jobs)) {
        die("Tiada rekod Photoshop merge ditemui.");
    }

    // Trigger Photoshop Exporter
    PhotoshopExporter::exportToCSV($jobs);

} catch (Exception $e) {
    die("Ralat muat turun CSV: " . $e->getMessage());
}