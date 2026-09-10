<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../engine/Normalizer.php';
require_once __DIR__ . '/../engine/Mapper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../customer/customer.html?error=Invalid+request+method");
    exit();
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$data = is_array($jsonInput) ? $jsonInput : $_POST;

$orderId = !empty($data['order_id']) ? trim($data['order_id']) : (!empty($data['order_no']) ? trim($data['order_no']) : null);

if (!$orderId) {
    header("Location: ../customer/customer.html?error=Missing+required+order+ID+payload");
    exit();
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // 1. Ensure primary order record exists with all required columns
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $customerName  = $data['full_name'] ?? $data['groom_name'] ?? 'Customer';
        $customerEmail = $data['email'] ?? 'N/A';
        $customerPhone = $data['phone'] ?? 'N/A';

        $stmtCreateOrder = $db->prepare("INSERT INTO orders (
            order_id, customer_name, customer_email, customer_phone, order_status, created_at
        ) VALUES (
            :order_id, :customer_name, :customer_email, :customer_phone, 'PENDING', NOW()
        )");
        
        $stmtCreateOrder->execute([
            ':order_id'       => $orderId,
            ':customer_name'  => $customerName,
            ':customer_email' => $customerEmail,
            ':customer_phone' => $customerPhone
        ]);
        
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Map form fields safely
    $details = [
        'design_code_groom'  => $data['design_code_groom'] ?? null,
        'design_code_bride'  => $data['design_code_bride'] ?? null,
        'card_title_groom'   => $data['card_title_groom'] ?? null,
        'card_title_bride'   => $data['card_title_bride'] ?? null,
        'groom_name'         => $data['groom_name'] ?? $data['full_name'] ?? 'N/A',
        'groom_abbrev'       => $data['groom_abbrev'] ?? null,
        'bride_name'         => $data['bride_name'] ?? 'N/A',
        'bride_abbrev'       => $data['bride_abbrev'] ?? null,
        'second_couple_notes'=> $data['notes'] ?? $data['second_couple_notes'] ?? null,
        'groom_father'       => $data['groom_father'] ?? null,
        'groom_mother'       => $data['groom_mother'] ?? null,
        'groom_event_date'   => $data['event_date'] ?? $data['groom_event_date'] ?? null,
        'groom_hijri_date'   => $data['groom_hijri_date'] ?? null,
        'groom_event_time'   => $data['event_time'] ?? $data['groom_event_time'] ?? null,
        'groom_venue'        => $data['venue'] ?? $data['groom_venue'] ?? null,
        'groom_address'      => $data['groom_address'] ?? null,
        'groom_maps_url'     => $data['groom_maps_url'] ?? null,
        'groom_contacts'     => json_encode($data['groom_contacts'] ?? []),
        'bride_father'       => $data['bride_father'] ?? null,
        'bride_mother'       => $data['bride_mother'] ?? null,
        'bride_event_date'   => $data['bride_event_date'] ?? null,
        'bride_hijri_date'   => $data['bride_hijri_date'] ?? null,
        'bride_event_time'   => $data['bride_event_time'] ?? null,
        'bride_venue'        => $data['bride_venue'] ?? null,
        'bride_address'      => $data['bride_address'] ?? null,
        'bride_maps_url'     => $data['bride_maps_url'] ?? null,
        'bride_contacts'     => json_encode($data['bride_contacts'] ?? []),
        'fulfillment_method' => $data['package_type'] ?? $data['fulfillment_method'] ?? 'courier',
        'shipping_recipient' => $data['full_name'] ?? $data['shipping_recipient'] ?? null,
        'shipping_phone'     => $data['phone'] ?? $data['shipping_phone'] ?? null,
        'shipping_address'   => $data['email'] ?? $data['shipping_address'] ?? null,
    ];

    // 3. Save / Upsert customer details
    $upsertSql = "INSERT INTO customer_details (
        order_id, design_code_groom, design_code_bride, card_title_groom, card_title_bride,
        groom_name, groom_abbrev, bride_name, bride_abbrev, second_couple_notes,
        groom_father, groom_mother, groom_event_date, groom_hijri_date, groom_event_time, groom_venue, groom_address, groom_maps_url, groom_contacts,
        bride_father, bride_mother, bride_event_date, bride_hijri_date, bride_event_time, bride_venue, bride_address, bride_maps_url, bride_contacts,
        fulfillment_method, shipping_recipient, shipping_phone, shipping_address, is_confirmed_by_customer, confirmed_at
    ) VALUES (
        :order_id, :design_code_groom, :design_code_bride, :card_title_groom, :card_title_bride,
        :groom_name, :groom_abbrev, :bride_name, :bride_abbrev, :second_couple_notes,
        :groom_father, :groom_mother, :groom_event_date, :groom_hijri_date, :groom_event_time, :groom_venue, :groom_address, :groom_maps_url, :groom_contacts,
        :bride_father, :bride_mother, :bride_event_date, :bride_hijri_date, :bride_event_time, :bride_venue, :bride_address, :bride_maps_url, :bride_contacts,
        :fulfillment_method, :shipping_recipient, :shipping_phone, :shipping_address, 1, NOW()
    ) ON DUPLICATE KEY UPDATE 
        groom_name=VALUES(groom_name), bride_name=VALUES(bride_name),
        is_confirmed_by_customer=1, confirmed_at=NOW()";

    $stmtUpsert = $db->prepare($upsertSql);
    $stmtUpsert->execute(array_merge([':order_id' => $orderId], $details));

    // 4. Run Mapper if available
    if (class_exists('Mapper') && method_exists('Mapper', 'generateMergeJobs')) {
        $mergeJobs = Mapper::generateMergeJobs($order, $details);

        $stmtClear = $db->prepare("DELETE FROM photoshop_merge_jobs WHERE order_id = :order_id");
        $stmtClear->execute([':order_id' => $orderId]);

        if (!empty($mergeJobs)) {
            $insertJobSql = "INSERT INTO photoshop_merge_jobs (
                job_id, order_id, side, design_code, card_title, namapengantin1, namapengantin2, 
                namabapa, namaibu, tarikh, tarikh_hijri, masa, venue, alamat, maps_url, contact_persons, status
            ) VALUES (
                :job_id, :order_id, :side, :design_code, :card_title, :namapengantin1, :namapengantin2, 
                :namabapa, :namaibu, :tarikh, :tarikh_hijri, :masa, :venue, :alamat, :maps_url, :contact_persons, 'pending'
            )";
            $stmtJob = $db->prepare($insertJobSql);

            foreach ($mergeJobs as $job) {
                $stmtJob->execute($job);
            }
        }
    }

    // 5. Update main order status
    $stmtUpdateOrder = $db->prepare("UPDATE orders SET order_status = 'DETAILS_CONFIRMED' WHERE order_id = :order_id");
    $stmtUpdateOrder->execute([':order_id' => $orderId]);

    $db->commit();

    ob_end_clean();
    header("Location: ../customer/thank_you.html?order_id=" . urlencode($orderId));
    exit();

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    ob_end_clean();
    die("Database Processing Error: " . $e->getMessage());
}