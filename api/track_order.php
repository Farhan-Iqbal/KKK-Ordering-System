<?php
// api/track_order.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$orderId = trim($_GET['order_id'] ?? '');

if (empty($orderId)) {
    echo json_encode(['success' => false, 'message' => 'Sila masukkan ID Pesanan (Order ID).']);
    exit();
}

try {
    $db = Database::getConnection();
    
    // Fetch order status and timestamps
    $stmt = $db->prepare("
        SELECT 
            order_id, customer_name, package_type, order_status, created_at, updated_at
        FROM orders 
        WHERE order_id = :order_id
    ");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Pesanan tidak dijumpai. Sila semak ID Pesanan anda.']);
        exit();
    }

    // Workflow status mapping
    $allStatuses = [
        'DETAILS_CONFIRMED' => ['title' => 'Butiran Diterima', 'desc' => 'Maklumat kad dan tempahan telah disahkan.'],
        'IN_DESIGN'         => ['title' => 'Dalam Perekaan', 'desc' => 'Pereka kami sedang menyiapkan draf kad anda.'],
        'REVIEW_READY'      => ['title' => 'Semakan Draf', 'desc' => 'Draf sedia untuk disemak oleh anda.'],
        'APPROVED'          => ['title' => 'Disahkan & Diluluskan', 'desc' => 'Draf telah diluluskan.'],
        'COMPLETED'         => ['title' => 'Selesai / Sedia Digunakan', 'desc' => 'Pautan kad digital anda sedia digunakan.']
    ];

    // Compute progress percentage based on current order_status index
    $statusKeys = array_keys($allStatuses);
    $currentIndex = array_search($order['order_status'], $statusKeys);
    if ($currentIndex === false) { 
        $currentIndex = 0; 
    }
    
    $progressPercent = round((($currentIndex + 1) / count($statusKeys)) * 100);

    echo json_encode([
        'success'          => true,
        'order_id'         => $order['order_id'],
        'customer_name'    => $order['customer_name'],
        'package_type'     => $order['package_type'],
        'current_status'   => $order['order_status'],
        'progress_percent' => $progressPercent,
        'created_at'       => date('d M Y, h:i A', strtotime($order['created_at'])),
        'updated_at'       => date('d M Y, h:i A', strtotime($order['updated_at'])),
        'steps'            => $allStatuses
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ralat pelayan: ' . $e->getMessage()]);
}