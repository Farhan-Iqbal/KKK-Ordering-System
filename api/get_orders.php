<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    $statusFilter = $_GET['status'] ?? 'ALL';

    // 1. Fetch Order Statistics
    $statsSql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN order_status = 'PENDING' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN order_status = 'DETAILS_CONFIRMED' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN order_status = 'COMPLETED' THEN 1 ELSE 0 END) as completed
    FROM orders";
    
    $statsStmt = $db->query($statsSql);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch Orders joined with Customer Details
    $sql = "SELECT 
        o.order_id,
        o.customer_name,
        o.customer_email,
        o.customer_phone,
        o.order_status,
        o.created_at,
        cd.groom_name,
        cd.bride_name,
        cd.fulfillment_method
    FROM orders o
    LEFT JOIN customer_details cd ON o.order_id = cd.order_id";

    $params = [];

    if ($statusFilter !== 'ALL') {
        $sql .= " WHERE o.order_status = :status";
        $params[':status'] = $statusFilter;
    }

    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats'   => [
            'total'     => (int)($stats['total'] ?? 0),
            'pending'   => (int)($stats['pending'] ?? 0),
            'confirmed' => (int)($stats['confirmed'] ?? 0),
            'completed' => (int)($stats['completed'] ?? 0),
        ],
        'orders'  => $orders
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}