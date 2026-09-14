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

    // -------------------------------------------------------------
    // 1. Process receipt file upload if present
    // -------------------------------------------------------------
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

    // -------------------------------------------------------------
    // 2. Ensure primary order record exists or insert clean order
    // -------------------------------------------------------------
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    $customerName  = $data['name'] ?? $data['full_name'] ?? $data['customer_name'] ?? 'Customer';
    $customerEmail = $data['email'] ?? $data['customer_email'] ?? 'N/A';
    $customerPhone = $data['phone'] ?? $data['customer_phone'] ?? 'N/A';

    // Sanitize package_type ENUM('1-package', '2-package')
    $rawPackage = strtolower(trim($data['package_type'] ?? ''));
    if (in_array($rawPackage, ['2-package', '2', '2_package', 'double', '2package']) || strpos($rawPackage, 'kedua-dua') !== false) {
        $packageType = '2-package';
    } else {
        $packageType = '1-package';
    }

    // Sanitize side_type ENUM('lelaki', 'perempuan', 'both')
    $rawSide = strtolower(trim($data['side_type'] ?? $data['side'] ?? ''));
    if (in_array($rawSide, ['perempuan', 'p', 'female', 'bride']) || strpos($rawSide, 'perempuan') !== false) {
        $sideType = 'perempuan';
    } else if (in_array($rawSide, ['both', 'dua_dua', 'dua-dua', 'dua', 'kedua-dua pihak']) || strpos($rawSide, 'kedua-dua') !== false) {
        $sideType = 'both';
    } else {
        $sideType = 'lelaki';
    }

    $designCode = $data['design_code'] ?? $data['design_code_groom'] ?? $data['m1_design_code'] ?? null;

    if (!$order) {
        $stmtCreateOrder = $db->prepare("INSERT INTO orders (
            order_id, customer_name, customer_email, customer_phone, package_type, side_type, design_code, order_status, payment_receipt_path, created_at
        ) VALUES (
            :order_id, :customer_name, :customer_email, :customer_phone, :package_type, :side_type, :design_code, 'DETAILS_CONFIRMED', :payment_receipt_path, NOW()
        )");
        
        $stmtCreateOrder->execute([
            ':order_id'             => $orderId,
            ':customer_name'        => $customerName,
            ':customer_email'       => $customerEmail,
            ':customer_phone'       => $customerPhone,
            ':package_type'         => $packageType,
            ':side_type'            => $sideType,
            ':design_code'          => $designCode,
            ':payment_receipt_path' => $receiptPath
        ]);
    } else {
        $stmtUpdateOrder = $db->prepare("UPDATE orders SET 
            package_type = :package_type, 
            side_type = :side_type, 
            design_code = COALESCE(:design_code, design_code),
            payment_receipt_path = COALESCE(:payment_receipt_path, payment_receipt_path),
            order_status = 'DETAILS_CONFIRMED'
            WHERE order_id = :order_id");
            
        $stmtUpdateOrder->execute([
            ':package_type'         => $packageType,
            ':side_type'            => $sideType,
            ':design_code'          => $designCode,
            ':payment_receipt_path' => $receiptPath,
            ':order_id'             => $orderId
        ]);
    }

    // Refresh order array
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------
    // 3. Prepare Contacts & Shipping Information
    // -------------------------------------------------------------
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

    // Majlis 1 Contacts
    $m1Contacts = [];
    $c1Name = $data['contact1_name'] ?? $data['m1_contact1_name'] ?? null;
    $c1Phone = $data['contact1_phone'] ?? $data['m1_contact1_phone'] ?? null;
    if (!empty($c1Name) && !empty($c1Phone)) {
        $m1Contacts[] = ['name' => $c1Name, 'phone' => $c1Phone];
    }
    $c2Name = $data['contact2_name'] ?? $data['m1_contact2_name'] ?? null;
    $c2Phone = $data['contact2_phone'] ?? $data['m1_contact2_phone'] ?? null;
    if (!empty($c2Name) && !empty($c2Phone)) {
        $m1Contacts[] = ['name' => $c2Name, 'phone' => $c2Phone];
    }
    $c3Name = $data['contact3_name'] ?? $data['m1_contact3_name'] ?? null;
    $c3Phone = $data['contact3_phone'] ?? $data['m1_contact3_phone'] ?? null;
    if (!empty($c3Name) && !empty($c3Phone)) {
        $m1Contacts[] = ['name' => $c3Name, 'phone' => $c3Phone];
    }

    // Majlis 2 Contacts
    $m2Contacts = [];
    if (!empty($data['m2_contact1_name']) && !empty($data['m2_contact1_phone'])) {
        $m2Contacts[] = ['name' => $data['m2_contact1_name'], 'phone' => $data['m2_contact1_phone']];
    }
    if (!empty($data['m2_contact2_name']) && !empty($data['m2_contact2_phone'])) {
        $m2Contacts[] = ['name' => $data['m2_contact2_name'], 'phone' => $data['m2_contact2_phone']];
    }
    if (!empty($data['m2_contact3_name']) && !empty($data['m2_contact3_phone'])) {
        $m2Contacts[] = ['name' => $data['m2_contact3_name'], 'phone' => $data['m2_contact3_phone']];
    }

    // Date Sanitization (Convert '' to NULL)
    $m1DateRaw = $data['event_date'] ?? $data['m1_event_date'] ?? $data['groom_event_date'] ?? null;
    $m1EventDate = (!empty($m1DateRaw) && trim($m1DateRaw) !== '') ? trim($m1DateRaw) : null;

    $m2DateRaw = $data['m2_event_date'] ?? $data['bride_event_date'] ?? null;
    $m2EventDate = (!empty($m2DateRaw) && trim($m2DateRaw) !== '') ? trim($m2DateRaw) : null;

    // -------------------------------------------------------------
    // 4. Build Customer Details Array matching the new Schema
    // -------------------------------------------------------------
    $details = [
        'order_id'            => $orderId,
        'groom_name'          => $data['groom_name'] ?? $data['m1_groom_name'] ?? null,
        'groom_abbrev'        => $data['groom_short'] ?? $data['groom_abbrev'] ?? null,
        'bride_name'          => $data['bride_name'] ?? $data['m2_bride_name'] ?? null,
        'bride_abbrev'        => $data['bride_short'] ?? $data['bride_abbrev'] ?? null,
        'second_couple_notes' => $data['extra_couple_full'] ?? $data['second_couple_notes'] ?? null,
        
        // Majlis 1 Details
        'm1_host_side'        => ($sideType === 'perempuan') ? 'perempuan' : 'lelaki',
        'm1_card_title'       => $data['event_title'] ?? $data['m1_event_title'] ?? $data['card_title_groom'] ?? null,
        'm1_father_name'      => $data['father_name'] ?? $data['m1_father_name'] ?? $data['groom_father'] ?? null,
        'm1_mother_name'      => $data['mother_name'] ?? $data['m1_mother_name'] ?? $data['groom_mother'] ?? null,
        'm1_event_date'       => $m1EventDate,
        'm1_hijri_date'       => $data['hijri_date'] ?? $data['m1_hijri_date'] ?? null,
        'm1_event_time'       => $data['event_time'] ?? $data['m1_event_time'] ?? null,
        'm1_venue'            => $data['event_address'] ?? $data['m1_event_address'] ?? $data['groom_venue'] ?? null,
        'm1_address'          => $data['event_address'] ?? $data['m1_event_address'] ?? $data['groom_address'] ?? null,
        'm1_maps_url'         => $data['location_url'] ?? $data['m1_location_url'] ?? null,
        'm1_contacts'         => json_encode($m1Contacts),

        // Majlis 2 Details
        'm2_card_title'       => $data['m2_event_title'] ?? $data['card_title_bride'] ?? null,
        'm2_father_name'      => $data['m2_father_name'] ?? $data['bride_father'] ?? null,
        'm2_mother_name'      => $data['m2_mother_name'] ?? $data['bride_mother'] ?? null,
        'm2_event_date'       => $m2EventDate,
        'm2_hijri_date'       => $data['m2_hijri_date'] ?? null,
        'm2_event_time'       => $data['m2_event_time'] ?? null,
        'm2_venue'            => $data['m2_event_address'] ?? $data['bride_venue'] ?? null,
        'm2_address'          => $data['m2_event_address'] ?? $data['bride_address'] ?? null,
        'm2_maps_url'         => $data['m2_location_url'] ?? null,
        'm2_contacts'         => json_encode($m2Contacts),

        // Fulfillment
        'fulfillment_method'  => $data['delivery_method'] ?? $data['fulfillment_method'] ?? 'courier',
        'shipping_recipient'  => $data['name'] ?? $data['shipping_recipient'] ?? null,
        'shipping_phone'      => $data['phone'] ?? $data['shipping_phone'] ?? null,
        'shipping_address'    => $fullShippingAddress,
    ];

    // -------------------------------------------------------------
    // 5. Save / Upsert to customer_details table
    // -------------------------------------------------------------
    $upsertSql = "INSERT INTO customer_details (
        order_id, groom_name, groom_abbrev, bride_name, bride_abbrev, second_couple_notes,
        m1_host_side, m1_card_title, m1_father_name, m1_mother_name, m1_event_date, m1_hijri_date, m1_event_time, m1_venue, m1_address, m1_maps_url, m1_contacts,
        m2_card_title, m2_father_name, m2_mother_name, m2_event_date, m2_hijri_date, m2_event_time, m2_venue, m2_address, m2_maps_url, m2_contacts,
        fulfillment_method, shipping_recipient, shipping_phone, shipping_address, is_confirmed_by_customer, confirmed_at
    ) VALUES (
        :order_id, :groom_name, :groom_abbrev, :bride_name, :bride_abbrev, :second_couple_notes,
        :m1_host_side, :m1_card_title, :m1_father_name, :m1_mother_name, :m1_event_date, :m1_hijri_date, :m1_event_time, :m1_venue, :m1_address, :m1_maps_url, :m1_contacts,
        :m2_card_title, :m2_father_name, :m2_mother_name, :m2_event_date, :m2_hijri_date, :m2_event_time, :m2_venue, :m2_address, :m2_maps_url, :m2_contacts,
        :fulfillment_method, :shipping_recipient, :shipping_phone, :shipping_address, 1, NOW()
    ) ON DUPLICATE KEY UPDATE 
        groom_name=VALUES(groom_name), bride_name=VALUES(bride_name),
        m1_card_title=VALUES(m1_card_title), m1_father_name=VALUES(m1_father_name), m1_mother_name=VALUES(m1_mother_name), m1_event_date=VALUES(m1_event_date), m1_venue=VALUES(m1_venue),
        m2_card_title=VALUES(m2_card_title), m2_father_name=VALUES(m2_father_name), m2_mother_name=VALUES(m2_mother_name), m2_event_date=VALUES(m2_event_date), m2_venue=VALUES(m2_venue),
        shipping_recipient=VALUES(shipping_recipient), shipping_phone=VALUES(shipping_phone), shipping_address=VALUES(shipping_address),
        is_confirmed_by_customer=1, confirmed_at=NOW()";

    $stmtUpsert = $db->prepare($upsertSql);
    $stmtUpsert->execute($details);

    // -------------------------------------------------------------
    // 6. Map & Create Photoshop Jobs if Mapper exists
    // -------------------------------------------------------------
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
                // Ensure arrays are JSON encoded for JSON database fields
                if (is_array($job['contact_persons'])) {
                    $job['contact_persons'] = json_encode($job['contact_persons']);
                }
                $stmtJob->execute($job);
            }
        }
    }

    $db->commit();
    ob_end_clean();

    // Redirect for standard web forms, return JSON for AJAX calls
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