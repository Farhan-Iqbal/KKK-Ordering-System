<?php
// api/customer_submit.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Helper function to generate unique order ID
function generateOrderId($db) {
    do {
        $orderId = 'KKK-' . strtoupper(substr(uniqid(), -10));
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $orderId]);
    } while ($stmt->fetchColumn() > 0);
    return $orderId;
}

try {
    $db = Database::getConnection();

    // Check if payload is standard POST form or JSON
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $data = $jsonInput ?? $_POST;

    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'No data submitted']);
        exit();
    }

    // 1. Generate Order ID
    $orderId = generateOrderId($db);

    // 2. File Upload Handling
    $bridePhotoPath = null;
    $paymentReceiptPath = null;
    $uploadDir = __DIR__ . '/../uploads/receipts/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['payment_receipt']['name'], PATHINFO_EXTENSION);
        $fileName = $orderId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $uploadDir . $fileName)) {
            $paymentReceiptPath = 'uploads/receipts/' . $fileName;
        }
    }

    // 3. Process Base Order Data
    $customerName   = $data['name'] ?? '';
    $customerPhone  = $data['phone'] ?? '';
    $customerEmail  = $data['email'] ?? null;
    
    // Normalize package_type
    $rawPackage = $data['package_type'] ?? '';
    if (strpos($rawPackage, '2') !== false || strpos($rawPackage, 'Kedua') !== false) {
        $packageType = '2-package';
    } else {
        $packageType = '1-package';
    }

    // Normalize side_type to match database ENUM ('lelaki', 'perempuan', 'both')
    $rawSide = strtolower($data['side'] ?? $data['side_type'] ?? '');
    if (strpos($rawSide, 'lelaki') !== false && strpos($rawSide, 'perempuan') !== false) {
        $sideType = 'both';
    } elseif (strpos($rawSide, 'lelaki') !== false) {
        $sideType = 'lelaki';
    } elseif (strpos($rawSide, 'perempuan') !== false) {
        $sideType = 'perempuan';
    } elseif ($packageType === '2-package') {
        $sideType = 'both';
    } else {
        $sideType = !empty($rawSide) ? $rawSide : 'lelaki';
    }

    $designCode     = $data['design_code'] ?? '';
    $theme          = $data['theme'] ?? null;

    // Insert into 'orders' table
    $stmtOrder = $db->prepare("
        INSERT INTO orders (
            order_id, customer_name, customer_email, customer_phone,
            package_type, side_type, design_code, theme,
            order_status, payment_receipt_path, created_at, updated_at
        ) VALUES (
            :order_id, :customer_name, :customer_email, :customer_phone,
            :package_type, :side_type, :design_code, :theme,
            'DETAILS_CONFIRMED', :payment_receipt_path, NOW(), NOW()
        )
    ");

    $stmtOrder->execute([
        ':order_id'             => $orderId,
        ':customer_name'        => $customerName,
        ':customer_email'       => $customerEmail,
        ':customer_phone'       => $customerPhone,
        ':package_type'         => $packageType,
        ':side_type'            => $sideType,
        ':design_code'          => $designCode,
        ':theme'                => $theme,
        ':payment_receipt_path' => $paymentReceiptPath
    ]);

    // 4. Process Customer Details & Contacts
    $isDual = ($packageType === '2-package');

    if ($isDual) {
        // --- MAJLIS PERTAMA ---
        $m1Contacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $cName = $data["m1_contact{$i}_name"] ?? null;
            $cPhone = $data["m1_contact{$i}_phone"] ?? null;
            if (!empty($cName) && !empty($cPhone)) {
                $m1Contacts[] = ['name' => $cName, 'phone' => $cPhone];
            }
        }

        // --- MAJLIS KEDUA ---
        $m2Contacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $cName = $data["m2_contact{$i}_name"] ?? null;
            $cPhone = $data["m2_contact{$i}_phone"] ?? null;
            if (!empty($cName) && !empty($cPhone)) {
                $m2Contacts[] = ['name' => $cName, 'phone' => $cPhone];
            }
        }

        $m1Side = $data['majlis1_side'] ?? 'Pihak Lelaki';
        $m2Side = ($m1Side === 'Pihak Lelaki') ? 'Pihak Perempuan' : 'Pihak Lelaki';
        $eventTitle = $data['event_title'] ?? 'Walimatul Urus';

        $stmtDetails = $db->prepare("
            INSERT INTO customer_details (
                order_id,
                m1_host_side, m1_card_title, m1_father_name, m1_mother_name,
                m1_event_date, m1_hijri_date, m1_event_time, m1_sanding_time,
                m1_venue, m1_address, m1_maps_url, m1_contacts,
                m2_host_side, m2_card_title, m2_father_name, m2_mother_name,
                m2_event_date, m2_hijri_date, m2_event_time, m2_sanding_time,
                m2_venue, m2_address, m2_maps_url, m2_contacts
            ) VALUES (
                :order_id,
                :m1_host_side, :m1_card_title, :m1_father_name, :m1_mother_name,
                :m1_event_date, :m1_hijri_date, :m1_event_time, :m1_sanding_time,
                :m1_venue, :m1_address, :m1_maps_url, :m1_contacts,
                :m2_host_side, :m2_card_title, :m2_father_name, :m2_mother_name,
                :m2_event_date, :m2_hijri_date, :m2_event_time, :m2_sanding_time,
                :m2_venue, :m2_address, :m2_maps_url, :m2_contacts
            )
        ");

        $stmtDetails->execute([
            ':order_id'       => $orderId,
            ':m1_host_side'   => $m1Side,
            ':m1_card_title'  => $eventTitle,
            ':m1_father_name' => $data['m1_father_name'] ?? null,
            ':m1_mother_name' => $data['m1_mother_name'] ?? null,
            ':m1_event_date'  => !empty($data['m1_event_date']) ? $data['m1_event_date'] : null,
            ':m1_hijri_date'  => $data['m1_hijri_date'] ?? null,
            ':m1_event_time'  => $data['m1_event_time'] ?? null,
            ':m1_sanding_time'=> $data['m1_sanding_time'] ?? null,
            ':m1_venue'       => $data['m1_event_address'] ?? null,
            ':m1_address'     => $data['m1_event_address'] ?? null,
            ':m1_maps_url'    => $data['m1_location_url'] ?? null,
            ':m1_contacts'    => json_encode($m1Contacts),

            ':m2_host_side'   => $m2Side,
            ':m2_card_title'  => $eventTitle,
            ':m2_father_name' => $data['m2_father_name'] ?? null,
            ':m2_mother_name' => $data['m2_mother_name'] ?? null,
            ':m2_event_date'  => !empty($data['m2_event_date']) ? $data['m2_event_date'] : null,
            ':m2_hijri_date'  => $data['m2_hijri_date'] ?? null,
            ':m2_event_time'  => $data['m2_event_time'] ?? null,
            ':m2_sanding_time'=> $data['m2_sanding_time'] ?? null,
            ':m2_venue'       => $data['m2_event_address'] ?? null,
            ':m2_address'     => $data['m2_event_address'] ?? null,
            ':m2_maps_url'    => $data['m2_location_url'] ?? null,
            ':m2_contacts'    => json_encode($m2Contacts)
        ]);

    } else {
        // --- SINGLE PARTY ---
        $singleContacts = [];
        for ($i = 1; $i <= 3; $i++) {
            $cName = $data["contact{$i}_name"] ?? null;
            $cPhone = $data["contact{$i}_phone"] ?? null;
            if (!empty($cName) && !empty($cPhone)) {
                $singleContacts[] = ['name' => $cName, 'phone' => $cPhone];
            }
        }

        $eventTitle = $data['event_title'] ?? 'Walimatul Urus';

        $stmtDetails = $db->prepare("
            INSERT INTO customer_details (
                order_id,
                m1_host_side, m1_card_title, m1_father_name, m1_mother_name,
                m1_event_date, m1_hijri_date, m1_event_time, m1_sanding_time,
                m1_venue, m1_address, m1_maps_url, m1_contacts
            ) VALUES (
                :order_id,
                :m1_host_side, :m1_card_title, :m1_father_name, :m1_mother_name,
                :m1_event_date, :m1_hijri_date, :m1_event_time, :m1_sanding_time,
                :m1_venue, :m1_address, :m1_maps_url, :m1_contacts
            )
        ");

        $stmtDetails->execute([
            ':order_id'       => $orderId,
            ':m1_host_side'   => $sideType,
            ':m1_card_title'  => $eventTitle,
            ':m1_father_name' => $data['father_name'] ?? null,
            ':m1_mother_name' => $data['mother_name'] ?? null,
            ':m1_event_date'  => !empty($data['event_date']) ? $data['event_date'] : null,
            ':m1_hijri_date'  => $data['hijri_date'] ?? null,
            ':m1_event_time'  => $data['event_time'] ?? null,
            ':m1_sanding_time'=> $data['sanding_time'] ?? null,
            ':m1_venue'       => $data['event_address'] ?? null,
            ':m1_address'     => $data['event_address'] ?? null,
            ':m1_maps_url'    => $data['location_url'] ?? null,
            ':m1_contacts'    => json_encode($singleContacts)
        ]);
    }

    // Standard redirect for traditional form submit
    if (empty($jsonInput) && !empty($_POST)) {
        header("Location: ../customer/thank_you.html?order_id=" . urlencode($orderId));
        exit();
    }

    // JSON response for AJAX submit
    echo json_encode([
        'success'  => true,
        'message'  => 'Order submitted successfully',
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}