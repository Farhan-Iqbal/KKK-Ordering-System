<?php
// api/customer_submit.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

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

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $data = $jsonInput ?? $_POST;

    if (empty($data)) {
        echo json_encode(['success' => false, 'message' => 'No data submitted']);
        exit();
    }

    $orderId = generateOrderId($db);

    // Setup upload directories
    $receiptDir = __DIR__ . '/../uploads/receipts/';
    $photoDir   = __DIR__ . '/../uploads/bride_photos/';

    if (!is_dir($receiptDir)) mkdir($receiptDir, 0755, true);
    if (!is_dir($photoDir))   mkdir($photoDir, 0755, true);

    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];

    // 1. Upload Payment Receipt / Attachment (supporting HTML input name 'attachment' or 'payment_receipt')
    $paymentReceiptPath = null;
    $fileKey = isset($_FILES['attachment']) ? 'attachment' : (isset($_FILES['payment_receipt']) ? 'payment_receipt' : null);

    if ($fileKey && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $fileName = $orderId . '_receipt_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $receiptDir . $fileName)) {
                $paymentReceiptPath = 'uploads/receipts/' . $fileName;
            }
        }
    }

    // 2. Upload Bride Photo
    $bridePhotoPath = null;
    if (isset($_FILES['bride_photo']) && $_FILES['bride_photo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['bride_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts)) {
            $fileName = $orderId . '_photo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['bride_photo']['tmp_name'], $photoDir . $fileName)) {
                $bridePhotoPath = 'uploads/bride_photos/' . $fileName;
            }
        }
    }

    // 3. Prepare Orders Data (handling both 'full_name' from HTML and 'name' from JSON)
    $customerName  = $data['full_name'] ?? $data['name'] ?? '';
    $customerPhone = $data['phone'] ?? '';
    $customerEmail = $data['email'] ?? '';
    $designCode    = $data['design_code'] ?? '';
    $theme         = $data['theme'] ?? null;

    $rawPackage  = $data['package_type'] ?? '';
    $packageType = (strpos($rawPackage, '2') !== false || strpos($rawPackage, 'Kedua') !== false) ? '2-package' : '1-package';

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

    // Insert into orders table
    $stmtOrder = $db->prepare("
        INSERT INTO orders (
            order_id, customer_name, customer_email, customer_phone,
            package_type, theme, side_type, design_code,
            order_status, payment_receipt_path, created_at, updated_at
        ) VALUES (
            :order_id, :customer_name, :customer_email, :customer_phone,
            :package_type, :theme, :side_type, :design_code,
            'DETAILS_CONFIRMED', :payment_receipt_path, NOW(), NOW()
        )
    ");

    $stmtOrder->execute([
        ':order_id'             => $orderId,
        ':customer_name'        => $customerName,
        ':customer_email'       => $customerEmail,
        ':customer_phone'       => $customerPhone,
        ':package_type'         => $packageType,
        ':theme'                => $theme,
        ':side_type'            => $sideType,
        ':design_code'          => $designCode,
        ':payment_receipt_path' => $paymentReceiptPath
    ]);

    // 4. Prepare Customer & Shipping Details
    $groomName  = $data['groom_name'] ?? null;
    $groomShort = $data['groom_short'] ?? null;
    $brideName  = $data['bride_name'] ?? null;
    $brideShort = $data['bride_short'] ?? null;

    $deliveryMethod = $data['delivery_method'] ?? 'courier';
    $recipientName  = $customerName;
    $shippingPhone  = $customerPhone;
    
    // Combine full address lines into single text column (supporting single 'venue' field or multi-line address)
    $addressParts = array_filter([
        $data['venue'] ?? '',
        $data['shipping_address'] ?? '',
        $data['shipping_postcode'] ?? '',
        $data['shipping_city'] ?? '',
        $data['shipping_state'] ?? ''
    ]);
    $fullAddress = implode(', ', $addressParts);

    $isDual = ($packageType === '2-package');

    if ($isDual) {
        $m1Contacts = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($data["m1_contact{$i}_name"]) && !empty($data["m1_contact{$i}_phone"])) {
                $m1Contacts[] = ['name' => $data["m1_contact{$i}_name"], 'phone' => $data["m1_contact{$i}_phone"]];
            }
        }

        $m2Contacts = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($data["m2_contact{$i}_name"]) && !empty($data["m2_contact{$i}_phone"])) {
                $m2Contacts[] = ['name' => $data["m2_contact{$i}_name"], 'phone' => $data["m2_contact{$i}_phone"]];
            }
        }

        $m1Side = $data['majlis1_side'] ?? 'Pihak Lelaki';
        $m2Side = ($m1Side === 'Pihak Lelaki') ? 'Pihak Perempuan' : 'Pihak Lelaki';
        $eventTitle = $data['event_title'] ?? 'Walimatul Urus';

        $stmtDetails = $db->prepare("
            INSERT INTO customer_details (
                order_id, groom_name, groom_abbrev, bride_name, bride_abbrev, bride_photo_path,
                m1_host_side, m1_card_title, m1_father_name, m1_mother_name,
                m1_event_date, m1_hijri_date, m1_event_time, m1_sanding_time,
                m1_venue, m1_address, m1_maps_url, m1_contacts,
                m2_host_side, m2_card_title, m2_father_name, m2_mother_name,
                m2_event_date, m2_hijri_date, m2_event_time, m2_sanding_time,
                m2_venue, m2_address, m2_maps_url, m2_contacts,
                fulfillment_method, shipping_recipient, shipping_phone, shipping_address, created_at
            ) VALUES (
                :order_id, :groom_name, :groom_abbrev, :bride_name, :bride_abbrev, :bride_photo_path,
                :m1_host_side, :m1_card_title, :m1_father_name, :m1_mother_name,
                :m1_event_date, :m1_hijri_date, :m1_event_time, :m1_sanding_time,
                :m1_venue, :m1_address, :m1_maps_url, :m1_contacts,
                :m2_host_side, :m2_card_title, :m2_father_name, :m2_mother_name,
                :m2_event_date, :m2_hijri_date, :m2_event_time, :m2_sanding_time,
                :m2_venue, :m2_address, :m2_maps_url, :m2_contacts,
                :fulfillment_method, :shipping_recipient_name, :shipping_phone, :shipping_address, NOW()
            )
        ");

        $stmtDetails->execute([
            ':order_id'                 => $orderId,
            ':groom_name'               => $groomName,
            ':groom_abbrev'             => $groomShort,
            ':bride_name'               => $brideName,
            ':bride_abbrev'             => $brideShort,
            ':bride_photo_path'         => $bridePhotoPath,
            ':m1_host_side'             => $m1Side,
            ':m1_card_title'            => $eventTitle,
            ':m1_father_name'           => $data['m1_father_name'] ?? null,
            ':m1_mother_name'           => $data['m1_mother_name'] ?? null,
            ':m1_event_date'            => !empty($data['m1_event_date']) ? $data['m1_event_date'] : null,
            ':m1_hijri_date'            => $data['m1_hijri_date'] ?? null,
            ':m1_event_time'            => $data['m1_event_time'] ?? null,
            ':m1_sanding_time'          => $data['m1_sanding_time'] ?? null,
            ':m1_venue'                 => $data['m1_event_address'] ?? null,
            ':m1_address'               => $data['m1_event_address'] ?? null,
            ':m1_maps_url'              => $data['m1_location_url'] ?? null,
            ':m1_contacts'              => json_encode($m1Contacts),
            ':m2_host_side'             => $m2Side,
            ':m2_card_title'            => $eventTitle,
            ':m2_father_name'           => $data['m2_father_name'] ?? null,
            ':m2_mother_name'           => $data['m2_mother_name'] ?? null,
            ':m2_event_date'            => !empty($data['m2_event_date']) ? $data['m2_event_date'] : null,
            ':m2_hijri_date'            => $data['m2_hijri_date'] ?? null,
            ':m2_event_time'            => $data['m2_event_time'] ?? null,
            ':m2_sanding_time'          => $data['m2_sanding_time'] ?? null,
            ':m2_venue'                 => $data['m2_event_address'] ?? null,
            ':m2_address'               => $data['m2_event_address'] ?? null,
            ':m2_maps_url'              => $data['m2_location_url'] ?? null,
            ':m2_contacts'              => json_encode($m2Contacts),
            ':fulfillment_method'       => $deliveryMethod,
            ':shipping_recipient_name'  => $recipientName,
            ':shipping_phone'           => $shippingPhone,
            ':shipping_address'         => $fullAddress
        ]);

    } else {
        $singleContacts = [];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($data["contact{$i}_name"]) && !empty($data["contact{$i}_phone"])) {
                $singleContacts[] = ['name' => $data["contact{$i}_name"], 'phone' => $data["contact{$i}_phone"]];
            }
        }

        $eventTitle = $data['event_title'] ?? 'Walimatul Urus';
        $eventVenue = $data['venue'] ?? $data['event_address'] ?? null;
        $eventDate  = !empty($data['event_date']) ? $data['event_date'] : (!empty($data['m1_event_date']) ? $data['m1_event_date'] : null);
        $eventTime  = $data['event_time'] ?? $data['m1_event_time'] ?? null;

        $stmtDetails = $db->prepare("
            INSERT INTO customer_details (
                order_id, groom_name, groom_abbrev, bride_name, bride_abbrev, bride_photo_path,
                m1_host_side, m1_card_title, m1_father_name, m1_mother_name,
                m1_event_date, m1_hijri_date, m1_event_time, m1_sanding_time,
                m1_venue, m1_address, m1_maps_url, m1_contacts,
                fulfillment_method, shipping_recipient, shipping_phone, shipping_address, created_at
            ) VALUES (
                :order_id, :groom_name, :groom_abbrev, :bride_name, :bride_abbrev, :bride_photo_path,
                :m1_host_side, :m1_card_title, :m1_father_name, :m1_mother_name,
                :m1_event_date, :m1_hijri_date, :m1_event_time, :m1_sanding_time,
                :m1_venue, :m1_address, :m1_maps_url, :m1_contacts,
                :fulfillment_method, :shipping_recipient_name, :shipping_phone, :shipping_address, NOW()
            )
        ");

        $stmtDetails->execute([
            ':order_id'                 => $orderId,
            ':groom_name'               => $groomName,
            ':groom_abbrev'             => $groomShort,
            ':bride_name'               => $brideName,
            ':bride_abbrev'             => $brideShort,
            ':bride_photo_path'         => $bridePhotoPath,
            ':m1_host_side'             => $sideType,
            ':m1_card_title'            => $eventTitle,
            ':m1_father_name'           => $data['father_name'] ?? null,
            ':m1_mother_name'           => $data['mother_name'] ?? null,
            ':m1_event_date'            => $eventDate,
            ':m1_hijri_date'            => $data['hijri_date'] ?? null,
            ':m1_event_time'            => $eventTime,
            ':m1_sanding_time'          => $data['sanding_time'] ?? null,
            ':m1_venue'                 => $eventVenue,
            ':m1_address'               => $eventVenue,
            ':m1_maps_url'              => $data['location_url'] ?? null,
            ':m1_contacts'              => json_encode($singleContacts),
            ':fulfillment_method'       => $deliveryMethod,
            ':shipping_recipient_name'  => $recipientName,
            ':shipping_phone'           => $shippingPhone,
            ':shipping_address'         => $fullAddress
        ]);
    }

    if (empty($jsonInput) && !empty($_POST)) {
        header("Location: ../thank_you.html?order_id=" . urlencode($orderId));
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Order submitted successfully', 'order_id' => $orderId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}