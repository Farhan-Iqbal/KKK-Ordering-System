<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing Order ID']);
    exit();
}

try {
    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT 
            o.order_id, o.customer_name, o.customer_email, o.customer_phone, o.order_status, o.created_at,
            cd.*
        FROM orders o
        LEFT JOIN customer_details cd ON o.order_id = cd.order_id
        WHERE o.order_id = :order_id
        LIMIT 1
    ");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit();
    }

    // Decode JSON contact lists safely
    $order['groom_contacts'] = json_decode($order['groom_contacts'] ?? '[]', true);
    $order['bride_contacts'] = json_decode($order['bride_contacts'] ?? '[]', true);

    echo json_encode(['success' => true, 'order' => $order]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}