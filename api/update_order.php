<?php
// api/update_order.php

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

    // 1. Update main order status & primary contact details if provided
    $customerName  = $data['customer_name'] ?? null;
    $customerPhone = $data['customer_phone'] ?? null;
    $customerEmail = $data['customer_email'] ?? null;

    $updateOrderSql = "UPDATE orders SET ";
    $orderUpdates = [];
    $orderParams = [':order_id' => $orderId];

    if ($orderStatus !== null) {
        $orderUpdates[] = "order_status = :order_status";
        $orderParams[':order_status'] = $orderStatus;
    }
    if ($customerName !== null) {
        $orderUpdates[] = "customer_name = :customer_name";
        $orderParams[':customer_name'] = $customerName;
    }
    if ($customerPhone !== null) {
        $orderUpdates[] = "customer_phone = :customer_phone";
        $orderParams[':customer_phone'] = $customerPhone;
    }
    if ($customerEmail !== null) {
        $orderUpdates[] = "customer_email = :customer_email";
        $orderParams[':customer_email'] = $customerEmail;
    }

    if (!empty($orderUpdates)) {
        $updateOrderSql .= implode(", ", $orderUpdates) . " WHERE order_id = :order_id";
        $stmtOrder = $db->prepare($updateOrderSql);
        $stmtOrder->execute($orderParams);
    }

    // 2. Upsert customer details (groom, bride, venue, shipping details)
    $stmtDetails = $db->prepare("
        INSERT INTO customer_details (
            order_id, groom_name, bride_name, groom_event_date, groom_venue, 
            shipping_recipient, shipping_phone, shipping_address
        ) VALUES (
            :order_id, :groom_name, :bride_name, :groom_event_date, :groom_venue, 
            :shipping_recipient, :shipping_phone, :shipping_address
        ) ON DUPLICATE KEY UPDATE
            groom_name = VALUES(groom_name),
            bride_name = VALUES(bride_name),
            groom_event_date = VALUES(groom_event_date),
            groom_venue = VALUES(groom_venue),
            shipping_recipient = VALUES(shipping_recipient),
            shipping_phone = VALUES(shipping_phone),
            shipping_address = VALUES(shipping_address)
    ");

    $stmtDetails->execute([
        ':order_id'           => $orderId,
        ':groom_name'         => $data['groom_name'] ?? null,
        ':bride_name'         => $data['bride_name'] ?? null,
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