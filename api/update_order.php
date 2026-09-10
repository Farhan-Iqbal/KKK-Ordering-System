<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$orderId = $data['order_id'] ?? null;
$orderStatus = $data['order_status'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Missing required Order ID']);
    exit();
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // 1. Update order status if provided
    if ($orderStatus) {
        $stmtStatus = $db->prepare("UPDATE orders SET order_status = :status WHERE order_id = :order_id");
        $stmtStatus->execute([':status' => $orderStatus, ':order_id' => $orderId]);
    }

    // 2. Update editable customer details
    $stmtDetails = $db->prepare("
        UPDATE customer_details SET
            groom_name = :groom_name,
            bride_name = :bride_name,
            groom_event_date = :groom_event_date,
            groom_venue = :groom_venue,
            shipping_recipient = :shipping_recipient,
            shipping_phone = :shipping_phone,
            shipping_address = :shipping_address
        WHERE order_id = :order_id
    ");

    $stmtDetails->execute([
        ':order_id'           => $orderId,
        ':groom_name'         => $data['groom_name'] ?? 'N/A',
        ':bride_name'         => $data['bride_name'] ?? 'N/A',
        ':groom_event_date'   => $data['groom_event_date'] ?? null,
        ':groom_venue'        => $data['groom_venue'] ?? null,
        ':shipping_recipient' => $data['shipping_recipient'] ?? null,
        ':shipping_phone'     => $data['shipping_phone'] ?? null,
        ':shipping_address'   => $data['shipping_address'] ?? null,
    ]);

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Order updated successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
}