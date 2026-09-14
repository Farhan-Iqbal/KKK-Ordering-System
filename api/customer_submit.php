<?php
// api/customer_submit.php

ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../engine/Normalizer.php';
require_once __DIR__ . '/../engine/Mapper.php';

// Accept both POST requests (from web forms) and JSON payloads
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);
$data = is_array($jsonInput) ? $jsonInput : $_POST;

$orderId = !empty($data['order_id']) ? trim($data['order_id']) : (!empty($data['order_no']) ? trim($data['order_no']) : null);

// Generate Order ID if not provided
if (!$orderId) {
    $orderId = "KKK-" . strtoupper(uniqid());
}

try {
    $db = Database::getConnection();
    $db->beginTransaction();

    // 1. Ensure primary order record exists or create new
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Process receipt file upload if present
    $receiptPath = null;
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/receipts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = $orderId . '_' . time() . '_' . basename($_FILES['payment_receipt']['name']);
        $receiptPath = 'uploads/receipts/' . $fileName;
        move_uploaded_file($_FILES['payment_receipt']['tmp_name'], __DIR__ . '/../' . $receiptPath);
    }

    if (!$order) {
        $customerName  = $data['name'] ?? $data['full_name'] ?? $data['customer_name'] ?? 'Customer';
        $customerEmail = $data['email'] ?? $data['customer_email'] ?? 'N/A';
        $customerPhone = $data['phone'] ?? $data['customer_phone'] ?? 'N/A';
        
        // Sanitize package_type to strictly match ENUM('1-package', '2-package')
        $rawPackage = strtolower(trim($data['package_type'] ?? ''));
        if (in_array($rawPackage, ['2-package', '2', '2_package', 'double', '2package']) || strpos($rawPackage, 'kedua-dua') !== false) {
            $packageType = '2-package';
        } else {
            $packageType = '1-package';
        }

        // Sanitize side_type to strictly match ENUM('lelaki', 'perempuan', 'both')
        $rawSide = strtolower(trim($data['side_type'] ?? $data['side'] ?? ''));
        if (in_array($rawSide, ['perempuan', 'p', 'female', 'bride']) || strpos($rawSide, 'perempuan') !== false) {
            $sideType = 'perempuan';
        } else if (in_array($rawSide, ['both', 'dua_dua', 'dua-dua', 'dua', 'kedua-dua pihak']) || strpos($rawSide, 'kedua-dua') !== false) {
            $sideType = 'both';
        } else {
            $sideType = 'lelaki';
        }

        $stmtCreateOrder = $db->prepare("INSERT INTO orders (
            order_id, package_type, side_type, customer_name, customer_email, customer_phone, order_status, created_at
        ) VALUES (
            :order_id, :package_type, :side_type, :customer_name, :customer_email, :customer_phone, 'PENDING', NOW()
        )");
        
        $stmtCreateOrder->execute([
            ':order_id'       => $orderId,
            ':package_type'   => $packageType,
            ':side_type'      => $sideType,
            ':customer_name'  => $customerName,
            ':customer_email' => $customerEmail,
            ':customer_phone' => $customerPhone
        ]);
        
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 2. Build full shipping address string
    $fullShippingAddress = null;
    if (!empty($data['shipping_address'])) {
        $addrParts = array_filter([
            $data['shipping_address'] ?? null,
            $data['shipping_postcode'] ?? null,
            $data['shipping_city'] ?? null,
            $data['shipping_state'] ?? null,
        ]);
        $fullShippingAddress = implode(', ', $addrParts);
    }

    // Pack contact persons into array (Single / Majlis 1)
    $groomContacts = [];
    $c1Name = $data['contact1_name'] ?? $data['m1_contact1_name'] ?? null;
    $c1Phone = $data['contact1_phone'] ?? $data['m1_contact1_phone'] ?? null;
    if (!empty($c1Name) && !empty($c1Phone)) {
        $groomContacts[] = ['name' => $c1Name, 'phone' => $c1Phone];
    }

    $c2Name = $data['contact2_name'] ?? $data['m1_contact2_name'] ?? null;
    $c2Phone = $data['contact2_phone'] ?? $data['m1_contact2_phone'] ?? null;
    if (!empty($c2Name) && !empty($c2Phone)) {
        $groomContacts[] = ['name' => $c2Name, 'phone' => $c2Phone];
    }

    $c3Name = $data['contact3_name'] ?? $data['m1_contact3_name'] ?? null;
    $c3Phone = $data['contact3_phone'] ?? $data['m1_contact3_phone'] ?? null;
    if (!empty($c3Name) && !empty($c3Phone)) {
        $groomContacts[] = ['name' => $c3Name, 'phone' => $c3Phone];
    }

    // Pack contact persons into array (Majlis 2)
    $brideContacts = [];
    if (!empty($data['m2_contact1_name']) && !empty($data['m2_contact1_phone'])) {
        $brideContacts[] = ['name' => $data['m2_contact1_name'], 'phone' => $data['m2_contact1_phone']];
    }
    if (!empty($data['m2_contact2_name']) && !empty($data['m2_contact2_phone'])) {
        $brideContacts[] = ['name' => $data['m2_contact2_name'], 'phone' => $data['m2_contact2_phone']];
    }
    if (!empty($data['m2_contact3_name']) && !empty($data['m2_contact3_phone'])) {
        $brideContacts[] = ['name' => $data['m2_contact3_name'], 'phone' => $data['m2_contact3_phone']];
    }

    $groomContactsList = !empty($groomContacts) ? $groomContacts : ($data['groom_contacts'] ?? []);
    $brideContactsList = !empty($brideContacts) ? $brideContacts : ($data['bride_contacts'] ?? []);

    // Sanitize dates to convert empty strings '' into null
    $groomDateRaw = $data['event_date'] ?? $data['m1_event_date'] ?? $data['groom_event_date'] ?? null;
    $groomEventDate = (!empty($groomDateRaw) && trim($groomDateRaw) !== '') ? trim($groomDateRaw) : null;

    $brideDateRaw = $data['m2_event_date'] ?? $data['bride_event_date'] ?? null;
    $brideEventDate = (!empty($brideDateRaw) && trim($brideDateRaw) !== '') ? trim($brideDateRaw) : null;

    // 3. Map customer form fields safely (with fallback aliases)
    $details = [
        'design_code_groom'  => $data['design_code'] ?? $data['design_code_groom'] ?? $data['m1_design_code'] ?? null,
        'design_code_bride'  => $data['design_code_bride'] ?? $data['m2_design_code'] ?? $data['design_code'] ?? null,
        'card_title_groom'   => $data['event_title'] ?? $data['card_title_groom'] ?? $data['m1_event_title'] ?? null,
        'card_title_bride'   => $data['card_title_bride'] ?? $data['m2_event_title'] ?? $data['event_title'] ?? null,
        
        'groom_name'         => $data['groom_name'] ?? $data['m1_groom_name'] ?? $data['full_name_groom'] ?? null,
        'groom_abbrev'       => $data['groom_short'] ?? $data['groom_abbrev'] ?? $data['m1_groom_short'] ?? null,
        'bride_name'         => $data['bride_name'] ?? $data['m2_bride_name'] ?? $data['full_name_bride'] ?? null,
        'bride_abbrev'       => $data['bride_short'] ?? $data['bride_abbrev'] ?? $data['m2_bride_short'] ?? null,
        'second_couple_notes'=> $data['extra_couple_full'] ?? $data['second_couple_notes'] ?? null,
        
        // Father & Mother: check generic, m1, groom, and bride fallbacks
        'groom_father'       => $data['father_name'] ?? $data['m1_father_name'] ?? $data['groom_father'] ?? $data['bride_father'] ?? null,
        'groom_mother'       => $data['mother_name'] ?? $data['m1_mother_name'] ?? $data['groom_mother'] ?? $data['bride_mother'] ?? null,
        
        // Event Date & Time details
        'groom_event_date'   => $groomEventDate ?? $brideEventDate ?? null,
        'groom_hijri_date'   => $data['hijri_date'] ?? $data['m1_hijri_date'] ?? $data['groom_hijri_date'] ?? $data['bride_hijri_date'] ?? null,
        'groom_event_time'   => $data['event_time'] ?? $data['m1_event_time'] ?? $data['groom_event_time'] ?? $data['bride_event_time'] ?? null,
        'groom_venue'        => $data['event_address'] ?? $data['m1_event_address'] ?? $data['groom_venue'] ?? $data['bride_venue'] ?? null,
        'groom_address'      => $data['event_address'] ?? $data['m1_event_address'] ?? $data['groom_address'] ?? $data['bride_address'] ?? null,
        'groom_maps_url'     => $data['location_url'] ?? $data['m1_location_url'] ?? $data['groom_maps_url'] ?? $data['bride_maps_url'] ?? null,
        'groom_contacts'     => $groomContactsList,
        
        // Bride side details (for 2-package or explicit bride side)
        'bride_father'       => $data['m2_father_name'] ?? $data['bride_father'] ?? $data['father_name'] ?? null,
        'bride_mother'       => $data['m2_mother_name'] ?? $data['bride_mother'] ?? $data['mother_name'] ?? null,
        'bride_event_date'   => $brideEventDate,
        'bride_hijri_date'   => $data['m2_hijri_date'] ?? $data['bride_hijri_date'] ?? null,
        'bride_event_time'   => $data['m2_event_time'] ?? $data['bride_event_time'] ?? null,
        'bride_venue'        => $data['m2_event_address'] ?? $data['bride_venue'] ?? null,
        'bride_address'      => $data['m2_event_address'] ?? $data['bride_address'] ?? null,
        'bride_maps_url'     => $data['m2_location_url'] ?? $data['bride_maps_url'] ?? null,
        'bride_contacts'     => $brideContactsList,
        
        'fulfillment_method' => $data['delivery_method'] ?? $data['fulfillment_method'] ?? 'courier',
        'shipping_recipient' => $data['name'] ?? $data['shipping_recipient'] ?? null,
        'shipping_phone'     => $data['phone'] ?? $data['shipping_phone'] ?? null,
        'shipping_address'   => $fullShippingAddress,
    ];

    // 4. Save / Upsert customer details
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
        groom_event_date=VALUES(groom_event_date), groom_venue=VALUES(groom_venue),
        bride_event_date=VALUES(bride_event_date), bride_venue=VALUES(bride_venue),
        shipping_recipient=VALUES(shipping_recipient), shipping_phone=VALUES(shipping_phone), shipping_address=VALUES(shipping_address),
        is_confirmed_by_customer=1, confirmed_at=NOW()";

    $dbParams = array_merge([':order_id' => $orderId], $details);
    $dbParams[':groom_contacts'] = is_array($details['groom_contacts']) ? json_encode($details['groom_contacts']) : $details['groom_contacts'];
    $dbParams[':bride_contacts'] = is_array($details['bride_contacts']) ? json_encode($details['bride_contacts']) : $details['bride_contacts'];

    $stmtUpsert = $db->prepare($upsertSql);
    $stmtUpsert->execute($dbParams);

    // 5. Run Mapper if available to populate merge jobs
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

    // 6. Update main order status to DETAILS_CONFIRMED
    $stmtUpdateOrder = $db->prepare("UPDATE orders SET order_status = 'DETAILS_CONFIRMED' WHERE order_id = :order_id");
    $stmtUpdateOrder->execute([':order_id' => $orderId]);

    $db->commit();

    ob_end_clean();

    // If request comes via direct form submit, redirect; else output JSON
    if (empty($jsonInput) && !empty($_POST)) {
        header("Location: ../customer/thank_you.html?order_id=" . urlencode($orderId));
        exit();
    }

    echo json_encode([
        'success'  => true,
        'order_id' => $orderId,
        'message'  => 'Order details saved successfully!'
    ]);
    exit();

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database Processing Error: ' . $e->getMessage()
    ]);
    exit();
}