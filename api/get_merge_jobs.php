<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();

    $statusFilter = $_GET['status'] ?? 'ALL';

    $sql = "SELECT 
        j.*,
        o.customer_name,
        o.customer_phone
    FROM photoshop_merge_jobs j
    LEFT JOIN orders o ON j.order_id = o.order_id";

    $params = [];
    if ($statusFilter !== 'ALL') {
        $sql .= " WHERE j.status = :status";
        $params[':status'] = strtolower($statusFilter);
    }

    $sql .= " ORDER BY j.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'jobs'    => $jobs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}