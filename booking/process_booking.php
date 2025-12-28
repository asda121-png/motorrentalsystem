<?php
session_start();
header('Content-Type: application/json');
require_once 'owner/db.php'; // Use the central db connection and helper functions

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_SESSION['userid'];
    $bike_id = (int)$_POST['bike_id'];
    $pickup_date = $_POST['pickupDate'];
    $return_date = $_POST['returnDate'];

    // Basic Validation
    if (empty($bike_id) || empty($pickup_date) || empty($return_date)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // Fetch Bike Details (Rate & Owner)
    $bike_query = "SELECT owner_id, daily_rate, model_name FROM bikes WHERE id = $bike_id LIMIT 1";
    $bike_res = mysqli_query($conn, $bike_query);
    $bike = mysqli_fetch_assoc($bike_res);

    if (!$bike) {
        echo json_encode(['success' => false, 'message' => 'Bike not found']);
        exit();
    }

    $owner_id = $bike['owner_id'];
    $daily_rate = $bike['daily_rate'];

    // Calculate Duration & Total Cost
    $start = new DateTime($pickup_date);
    $end = new DateTime($return_date);
    
    if ($end <= $start) {
        echo json_encode(['success' => false, 'message' => 'Return date must be after pickup date']);
        exit();
    }

    $interval = $start->diff($end);
    $days = $interval->days;
    if ($days == 0) $days = 1; // Minimum 1 day
    
    $total_amount = $days * $daily_rate;

    // Insert into Rentals Table
    // Note: expected_return_date is DATETIME in schema
    $stmt = $conn->prepare("INSERT INTO rentals (bike_id, customer_id, owner_id, amount_collected, rental_start_date, expected_return_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
    $expected_return_str = $end->format('Y-m-d H:i:s');
    $stmt->bind_param("iiidss", $bike_id, $customer_id, $owner_id, $total_amount, $pickup_date, $expected_return_str);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        $customer_name = $_SESSION['fullname'] ?? 'A customer';
        $model_name = $bike['model_name'] ?? 'a bike';
        
        // For Owner
        $owner_message = "New booking request from $customer_name for " . $model_name . ".";
        create_notification($conn, $owner_id, 'owner', $owner_message, 'rental_requests.php');

        // For Admin
        $admin_message = "New booking (#$last_id) for " . $model_name . " by $customer_name.";
        create_notification($conn, null, 'admin', $admin_message, '#');

        echo json_encode(['success' => true, 'message' => 'Booking request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
}