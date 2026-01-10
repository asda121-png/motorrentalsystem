<?php
session_start();
require_once 'db.php';

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id = $_SESSION['userid'];
    $owner_id = mysqli_real_escape_string($conn, $owner_id);

    // Delete related records (Rentals, Bikes)
    // Adjust table names if necessary based on your database schema
    mysqli_query($conn, "DELETE FROM rentals WHERE owner_id = '$owner_id'");
    mysqli_query($conn, "DELETE FROM bikes WHERE owner_id = '$owner_id'");

    // Delete user account
    // Assuming 'users' table. If your users are in 'owners' table, please update 'users' to 'owners'
    $delete_query = "DELETE FROM users WHERE id = '$owner_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        session_destroy();
        header("Location: ../login.php?msg=account_deleted");
        exit();
    }
}
header("Location: dashboard.php");
exit();
?>