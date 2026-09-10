<?php
// api/staff_orders.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    // Fetch orders with their customer details and merge job counts
    $sql = "SELECT 
                o.id,
                o.order_id,
                o.package_type,
                o.side_type,
                o.customer_name,
                o.customer_email,
                o.customer_phone,
                o.order_status,
                o.created_at,
                cd.groom_name,
                cd.bride_name,
                cd.fulfillment_method,
                cd.confirmed_at,
                COUNT(pmj.id) AS total_jobs
            FROM orders o
            LEFT JOIN customer_details cd ON o.order_id = cd.order_id
            LEFT JOIN photoshop_merge_jobs pmj ON o.order_id = pmj.order_id
            GROUP BY o.id
            ORDER BY o.created_at DESC";

    $stmt = $db->query($sql);
    $orders = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $orders
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}