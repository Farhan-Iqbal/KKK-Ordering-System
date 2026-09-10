<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit;
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // Generate unique order ID if not provided
    $orderId = !empty($input['order_id']) ? $input['order_id'] : 'KKK-' . date('Ymd') . '-' . rand(1000, 9999);

    // 1. Insert or Update Order
    $sqlOrder = "INSERT INTO orders (
                    order_id, customer_name, customer_phone, 
                    groom_name, bride_name, groom_event_date, groom_venue,
                    shipping_recipient, shipping_phone, shipping_address,
                    order_status, created_at
                ) VALUES (
                    :order_id, :customer_name, :customer_phone,
                    :groom_name, :bride_name, :groom_event_date, :groom_venue,
                    :shipping_recipient, :shipping_phone, :shipping_address,
                    'DETAILS_CONFIRMED', NOW()
                ) ON DUPLICATE KEY UPDATE 
                    customer_name = VALUES(customer_name),
                    customer_phone = VALUES(customer_phone),
                    groom_name = VALUES(groom_name),
                    bride_name = VALUES(bride_name),
                    groom_event_date = VALUES(groom_event_date),
                    groom_venue = VALUES(groom_venue),
                    shipping_recipient = VALUES(shipping_recipient),
                    shipping_phone = VALUES(shipping_phone),
                    shipping_address = VALUES(shipping_address),
                    order_status = 'DETAILS_CONFIRMED',
                    updated_at = NOW()";

    $stmt = $db->prepare($sqlOrder);
    $stmt->execute([
        ':order_id'           => $orderId,
        ':customer_name'      => $input['customer_name'] ?? '',
        ':customer_phone'     => $input['customer_phone'] ?? '',
        ':groom_name'         => $input['groom_name'] ?? '',
        ':bride_name'         => $input['bride_name'] ?? '',
        ':groom_event_date'   => $input['groom_event_date'] ?? '',
        ':groom_venue'        => $input['groom_venue'] ?? '',
        ':shipping_recipient' => $input['shipping_recipient'] ?? '',
        ':shipping_phone'     => $input['shipping_phone'] ?? '',
        ':shipping_address'   => $input['shipping_address'] ?? ''
    ]);

    // 2. Queue Photoshop Merge Jobs (Groom & Bride sides)
    $sqlJob = "INSERT INTO photoshop_merge_jobs (
                    job_id, order_id, side, design_code, namapengantin1, namapengantin2, status, created_at
                ) VALUES (
                    :job_id, :order_id, :side, :design_code, :namapengantin1, :namapengantin2, 'pending', NOW()
                ) ON DUPLICATE KEY UPDATE
                    side = VALUES(side),
                    design_code = VALUES(design_code),
                    namapengantin1 = VALUES(namapengantin1),
                    namapengantin2 = VALUES(namapengantin2),
                    status = 'pending'";

    $stmtJob = $db->prepare($sqlJob);

    // Groom Side Job
    $stmtJob->execute([
        ':job_id'         => 'JOB-' . $orderId . '-GROOM',
        ':order_id'       => $orderId,
        ':side'           => 'groom',
        ':design_code'    => $input['design_code'] ?? 'DEFAULT-01',
        ':namapengantin1' => $input['groom_name'] ?? '',
        ':namapengantin2' => $input['bride_name'] ?? ''
    ]);

    // Bride Side Job
    $stmtJob->execute([
        ':job_id'         => 'JOB-' . $orderId . '-BRIDE',
        ':order_id'       => $orderId,
        ':side'           => 'bride',
        ':design_code'    => $input['design_code'] ?? 'DEFAULT-01',
        ':namapengantin1' => $input['bride_name'] ?? '',
        ':namapengantin2' => $input['groom_name'] ?? ''
    ]);

    $db->commit();

    echo json_encode([
        'success'  => true,
        'order_id' => $orderId,
        'message'  => 'Order details submitted successfully!'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}