<?php
// api/upload_proofing.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$orderId = trim($data['order_id'] ?? '');
$proofingUrl = trim($data['proofing_url'] ?? '');

if (empty($orderId) || empty($proofingUrl)) {
    echo json_encode(['status' => 'error', 'message' => 'Order ID and Proofing URL are required.']);
    exit();
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // Verify order exists
    $stmt = $db->prepare("SELECT order_id FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    if (!$stmt->fetch()) {
        throw new Exception("Order ID not found.");
    }

    // Update order with proofing URL and update status
    $updateSql = "UPDATE orders 
                  SET order_status = 'PROOF_READY' 
                  WHERE order_id = :order_id";
    $stmtUpdate = $db->prepare($updateSql);
    $stmtUpdate->execute([':order_id' => $orderId]);

    $db->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Proofing link successfully attached and status updated to PROOF_READY.'
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}