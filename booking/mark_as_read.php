<?php
session_start();
header('Content-Type: application/json');

// AJAX handler to mark notifications as read
if (isset($_SESSION['userid']) && isset($_POST['user_type'])) {
    $conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
    $user_type = $_POST['user_type'];
    
    if ($user_type === 'admin') {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_type = 'admin' AND is_read = 0";
    } else {
        $user_id = (int)$_SESSION['userid'];
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND user_type = '$user_type' AND is_read = 0";
    }
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}