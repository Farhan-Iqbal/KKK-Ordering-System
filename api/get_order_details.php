<?php
// api/get_order_details.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing Order ID']);
    exit();
}

try {
    $db = Database::getConnection();

    // 1. Fetch main order record
    $stmtOrder = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmtOrder->execute([':order_id' => $orderId]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // 2. Fetch customer details record
    $stmtDetails = $db->prepare("SELECT * FROM customer_details WHERE order_id = :order_id LIMIT 1");
    $stmtDetails->execute([':order_id' => $orderId]);
    $details = $stmtDetails->fetch(PDO::FETCH_ASSOC) ?: [];

    // Decode JSON contacts safely
    if (!empty($details['m1_contacts'])) {
        $details['m1_contacts'] = json_decode($details['m1_contacts'], true);
    }
    if (!empty($details['m2_contacts'])) {
        $details['m2_contacts'] = json_decode($details['m2_contacts'], true);
    }

    // Return separated structures matching thank_you.html script expectations
    echo json_encode([
        'success' => true,
        'order'   => $order,
        'details' => $details
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}