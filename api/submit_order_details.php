<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// 1. Retrieve basic form data
$customerName  = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$orderSide     = trim($_POST['order_side'] ?? '');
$groomParents  = trim($_POST['groom_parents'] ?? '');
$brideParents  = trim($_POST['bride_parents'] ?? '');
$groomName     = trim($_POST['groom_name'] ?? '');
$brideName     = trim($_POST['bride_name'] ?? '');
$designCode    = trim($_POST['design_code'] ?? '');

// Retrieve Extra Contacts
$contact1Name  = trim($_POST['contact_name_1'] ?? '');
$contact1Phone = trim($_POST['contact_phone_1'] ?? '');
$contact2Name  = trim($_POST['contact_name_2'] ?? '');
$contact2Phone = trim($_POST['contact_phone_2'] ?? '');
$contact3Name  = trim($_POST['contact_name_3'] ?? '');
$contact3Phone = trim($_POST['contact_phone_3'] ?? '');

// 2. Validate mandatory customer & couple info
if (empty($customerName) || empty($customerPhone) || empty($groomParents) || empty($brideParents) || empty($groomName) || empty($brideName)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required customer, parents, and couple details.']);
    exit;
}

// 3. Handle Event Date & Venue validation based on Package Option
$eventDate = null;
$eventVenue = null;

if ($orderSide === 'two_packages' || $orderSide === 'bifold') {
    $groomDate  = trim($_POST['groom_side_date'] ?? '');
    $groomVenue = trim($_POST['groom_side_venue'] ?? '');
    $brideDate  = trim($_POST['bride_side_date'] ?? '');
    $brideVenue = trim($_POST['bride_side_venue'] ?? '');

    if (empty($groomDate) || empty($groomVenue) || empty($brideDate) || empty($brideVenue)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in event dates and locations for both sides.']);
        exit;
    }

    $eventDate  = "Lelaki: " . $groomDate . " | Perempuan: " . $brideDate;
    $eventVenue = "Lelaki: " . $groomVenue . "\nPerempuan: " . $brideVenue;

} else {
    // Single Side (groom or bride)
    $eventDate  = trim($_POST['groom_event_date'] ?? '');
    $eventVenue = trim($_POST['groom_venue'] ?? '');

    if (empty($eventDate) || empty($eventVenue)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in event date and venue.']);
        exit;
    }
}

// 4. Validate Receipt Upload
if (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please attach a valid payment receipt.']);
    exit;
}

// 5. Generate Order ID
$orderId = "KKK-" . strtoupper(uniqid());

// Save or insert data into DB here as needed...

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'message' => 'Order submitted successfully!'
]);
exit;