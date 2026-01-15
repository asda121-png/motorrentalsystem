<?php
/**
 * Motorcycle/Scooter Rental System - Multi-file Architecture (Admin & Owner)
 * Tech Stack: PHP, MySQL, HTML, CSS
 * * LOCAL SETUP GUIDE:
 * 1. config.php      -> Database & Stats logic
 * 2. sidebar.php     -> Sidebar HTML
 * 3. header.php      -> Top bar HTML
 * 4. dashboard.php   -> Case 'dashboard' content (Owner)
 * 5. admin_master.php -> Case 'admin_master' content (Platform Overview)
 * 6. verify_owners.php -> Case 'verify_owners' content (User verification)
 */

session_start();

// --- SECTION: AUTHENTICATION ---
if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Logout Logic
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// --- SECTION: DATABASE (config.php) ---
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';
$db_name = 'moto_rental_db';

$conn = @mysqli_connect($db_host, $db_user, $db_pass);
if (!$conn) {
    die("<div style='padding:20px; background:#fee2e2; color:#b91c1c; border-radius:8px;'>Database Error</div>");
}
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db_name");
mysqli_select_db($conn, $db_name);

// --- SCHEMA UPDATE: Add Owner Status & Admin Flag ---
// Ensure 'status' column in 'owners' table exists and has correct ENUM values (lowercase)
$result = mysqli_query($conn, "SHOW COLUMNS FROM owners LIKE 'status'");
if (mysqli_num_rows($result) == 0) {
    mysqli_query($conn, "ALTER TABLE owners ADD COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'pending'");
} else {
    $row = mysqli_fetch_assoc($result);
    if (stripos($row['Type'], "'pending','active','disabled'") === false) {
        mysqli_query($conn, "ALTER TABLE owners MODIFY COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'pending'");
    }
}
// Ensure 'role' column in 'owners' table exists and has correct ENUM values (lowercase)
$result = mysqli_query($conn, "SHOW COLUMNS FROM owners LIKE 'role'");
if (mysqli_num_rows($result) == 0) {
    mysqli_query($conn, "ALTER TABLE owners ADD COLUMN role ENUM('owner', 'admin') DEFAULT 'owner'");
} else {
    $row = mysqli_fetch_assoc($result);
    if (stripos($row['Type'], "'owner','admin'") === false) {
        mysqli_query($conn, "ALTER TABLE owners MODIFY COLUMN role ENUM('owner', 'admin') DEFAULT 'owner'");
    }
}

// --- SCHEMA UPDATE: Customer Status & Verification ---
// Ensure default status is active so unverified customers can still login
$result = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'status'");
if (mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    if (stripos($row['Type'], "'pending','active','disabled'") === false) {
        mysqli_query($conn, "ALTER TABLE customers MODIFY COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'active'");
    }
}
mysqli_query($conn, "UPDATE customers SET status='active' WHERE status='pending'");
// Ensure is_verified column exists
$result = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'is_verified'");
if (mysqli_num_rows($result) == 0) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
}

// Ensure expected_return_date is DATETIME
$rental_date_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'expected_return_date'");
$rd_row = mysqli_fetch_assoc($rental_date_check);
if (stripos($rd_row['Type'], 'datetime') === false) {
    mysqli_query($conn, "ALTER TABLE rentals MODIFY COLUMN expected_return_date DATETIME NULL");
}

// Check rental_end_date type and modify to DATETIME if it is DATE
$rental_end_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'rental_end_date'");
$red_row = mysqli_fetch_assoc($rental_end_check);
if (stripos($red_row['Type'], 'datetime') === false) {
    mysqli_query($conn, "ALTER TABLE rentals MODIFY COLUMN rental_end_date DATETIME NULL");
}

$owner_name = $_SESSION['fullname']; // Use session data
$current_page = $_GET['page'] ?? 'admin_master';

// --- ACTION HANDLING ---
// Handle Owner Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_owner'])) {
    $target_id = (int)$_POST['owner_id'];
    mysqli_query($conn, "UPDATE owners SET status='active' WHERE ownerid=$target_id");
    require_once __DIR__ . '/../owner/owner_email_helper.php';
    send_owner_status_email($target_id, 'active');
    $owner_link = './dashboard.php?page=verify_owners&highlight_owner=' . $target_id;
    create_notification($conn, null, 'admin', 'Owner ID #' . $target_id . ' has been verified. <a href=\'" . $owner_link . "\'>View Owner</a>', $owner_link);
    $_SESSION['owner_verified_success'] = true;
    header("Location: ?page=verify_owners");
    exit();
}

// Handle Customer Verification
if (isset($_GET['verify_customer'])) {
    $target_id = (int)$_GET['verify_customer'];
    $check_docs = mysqli_query($conn, "SELECT email, fullname FROM customers WHERE userid=$target_id");
    $check_docs = mysqli_query($conn, "SELECT email, fullname, profile_image, phone_number, drivers_license_image, valid_id_image FROM customers WHERE userid=$target_id");
    $doc_row = mysqli_fetch_assoc($check_docs);

    // Check if required documents are uploaded
    if (empty($doc_row['profile_image']) || empty($doc_row['phone_number']) || empty($doc_row['drivers_license_image']) || empty($doc_row['valid_id_image'])) {
        header("Location: ?page=verify_customers&error=incomplete");
        exit();
    }

    mysqli_query($conn, "UPDATE customers SET status='active', is_verified=1 WHERE userid=$target_id");
    // Notify customer in-app
    create_notification($conn, $target_id, 'customer', 'Congratulations! Your account has been verified.', 'profile.php');
    // Notify customer by email
    require_once __DIR__ . '/../smtp_mailer.php';
    $to = $doc_row['email'];
    $subject = 'Your Account Has Been Approved';
    $message_body = '<p>Dear ' . htmlspecialchars($doc_row['fullname']) . ',</p>'
        . '<p>Congratulations! Your account at Mati City Moto Rentals has been <b>approved</b> and is now active. You may now log in and start booking motorcycles.</p>'
        . '<p>Thank you for registering!</p>';
    send_gmail($to, $subject, $message_body);
    header("Location: ?page=verify_customers");
    exit();
}

if (isset($_GET['delete_customer'])) {
    $target_id = (int)$_GET['delete_customer'];
    // Get info for email before deletion
    $check = mysqli_query($conn, "SELECT email, fullname FROM customers WHERE userid=$target_id");
    $row = mysqli_fetch_assoc($check);
    
    require_once __DIR__ . '/../smtp_mailer.php';
    $to = $row['email'];
    $subject = 'Account Application Rejected';
    $message_body = '<p>Dear ' . htmlspecialchars($row['fullname']) . ',</p>'
        . '<p>We regret to inform you that your account application at Mati City Moto Rentals has been declined and your data has been removed from our system.</p>';
    send_gmail($to, $subject, $message_body);

    // Delete the customer
    mysqli_query($conn, "DELETE FROM customers WHERE userid=$target_id");

    header("Location: ?page=verify_customers");
    exit();
}

// Handle Owner Suspension
if (isset($_POST['suspend_owner']) && isset($_POST['owner_id']) && isset($_POST['suspend_reason'])) {
    $target_id = (int)$_POST['owner_id'];
    $reason = trim($_POST['suspend_reason']);
    mysqli_query($conn, "UPDATE owners SET status='disabled' WHERE ownerid=$target_id");
    require_once __DIR__ . '/../owner/owner_email_helper.php';
    send_owner_status_email($target_id, 'disabled', $reason);
    $owner_link = './dashboard.php?page=verify_owners&highlight_owner=' . $target_id;
    create_notification($conn, null, 'admin', 'Owner ID #' . $target_id . ' has been suspended. <a href=\'' . $owner_link . '\'>View Owner</a>', $owner_link);
    header("Location: ?page=verify_owners");
    exit();
}

// Handle Owner Deletion
if (isset($_GET['delete_owner'])) {
    $target_id = (int)$_GET['delete_owner'];
    // Get info for email before deletion
    $check = mysqli_query($conn, "SELECT email, fullname FROM owners WHERE ownerid=$target_id");
    if ($row = mysqli_fetch_assoc($check)) {
        require_once __DIR__ . '/../smtp_mailer.php';
        $to = $row['email'];
        $subject = 'Account Removed';
        $message_body = '<p>Dear ' . htmlspecialchars($row['fullname']) . ',</p><p>Your owner account has been removed from our system by the administrator.</p>';
        send_gmail($to, $subject, $message_body);
    }
    mysqli_query($conn, "DELETE FROM notifications WHERE user_id=$target_id AND user_type='owner'");
    mysqli_query($conn, "DELETE FROM owners WHERE ownerid=$target_id");
    header("Location: ?page=verify_owners&msg=deleted");
    exit();
}

// Handle Bike Deletion (Admin)
if (isset($_GET['delete_bike'])) {
    $bike_id = (int)$_GET['delete_bike'];
    mysqli_query($conn, "DELETE FROM bikes WHERE id=$bike_id");
    header("Location: ?page=manage_motorcycle&msg=deleted");
    exit();
}

// Handle Bike Suspension (Admin)
if (isset($_GET['suspend_bike'])) {
    $bike_id = (int)$_GET['suspend_bike'];
    mysqli_query($conn, "UPDATE bikes SET status='Maintenance' WHERE id=$bike_id");
    header("Location: ?page=manage_motorcycle&msg=suspended");
    exit();
}

// Global Stats for Master Dashboard (Aggregated across ALL owners)
$master_stats_res = mysqli_query($conn, "SELECT 
    (SELECT COUNT(*) FROM owners WHERE role='Owner') as total_owners,
    (SELECT COUNT(*) FROM bikes) as total_fleet,
    (SELECT COUNT(*) FROM rentals WHERE status != 'Pending') as total_bookings,
    (SELECT SUM(amount_collected) FROM rentals WHERE status != 'Pending') as platform_revenue,
    (SELECT COUNT(*) FROM customers WHERE role='customer') as total_customers");
$master_stats = mysqli_fetch_assoc($master_stats_res);

/**
 * Creates a new notification.
 */
function create_notification($conn, $user_id, $user_type, $message, $link = '#') {
    $user_id_sql = is_null($user_id) ? "NULL" : (int)$user_id;
    $message_sql = mysqli_real_escape_string($conn, $message);
    $link_sql = mysqli_real_escape_string($conn, $link);
    $user_type_sql = mysqli_real_escape_string($conn, $user_type);
    
    mysqli_query($conn, "INSERT INTO notifications (user_id, user_type, message, link) VALUES ($user_id_sql, '$user_type_sql', '$message_sql', '$link_sql')");
}
?>
<?php
// --- NOTIFICATION LOGIC ---
$admin_notif_query = "SELECT * FROM notifications WHERE user_type = 'admin' ORDER BY created_at DESC LIMIT 5";
$admin_notif_res = mysqli_query($conn, $admin_notif_query);
$admin_unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_type = 'admin' AND is_read = 0";
$admin_unread_count = mysqli_fetch_assoc(mysqli_query($conn, $admin_unread_query))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoRent | Admin Master Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0f172a;
            --primary: #3b82f6;
            --primary-light: #eff6ff;
            --accent: #10b981;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --sidebar-width: 260px;
        }
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-main); margin: 0; display: flex; height: 100vh; overflow: hidden; color: var(--text-main); }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); color: #f8fafc; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-header { padding: 30px 25px; font-size: 1.4rem; font-weight: 700; display: flex; align-items: center; gap: 12px; }
        .sidebar-header i { color: var(--primary); }
        .sidebar-menu { flex: 1; padding: 10px 15px; list-style: none; margin: 0; }
        .sidebar-label { padding: 20px 15px 10px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 1px; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu li a { padding: 12px 15px; display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; border-radius: 8px; font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
        .sidebar-menu li.active a { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .header { background: var(--card-bg); height: 70px; display: flex; align-items: center; justify-content: space-between; padding: 0 35px; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 90; }
        .container { padding: 35px; max-width: 1400px; margin: 0 auto; width: 100%; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 35px; }
        .stat-card { background: var(--card-bg); padding: 24px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-icon-wrapper { width: 54px; height: 54px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 30px; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px 25px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); }
        td { padding: 18px 25px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #dcfce7; color: #166534; }
        .badge-active { background: #dcfce7; color: #166534; }
        .btn { padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; border: none; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--accent); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-main); }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 90%; max-width: 500px; position: relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        .modal-header h2 { margin: 0; font-size: 1.25rem; color: var(--text-main); font-weight: 700; }
        .modal-header button { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); transition: 0.2s; }
        .modal-header button:hover { color: var(--text-main); }
        .modal-body p { margin: 12px 0; color: var(--text-main); font-size: 0.95rem; display: flex; }
        .modal-body strong { color: var(--text-muted); width: 140px; display: inline-block; flex-shrink: 0; }

        .notification-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; border: 2px solid var(--card-bg); }
        .notification-dropdown { display: none; position: absolute; right: 0; top: 120%; width: 320px; background: var(--card-bg); border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid var(--border-color); z-index: 100; }
        .notification-item { padding: 12px 15px; border-bottom: 1px solid var(--border-color); font-size: 0.85rem; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div style="width:32px; height:32px; border-radius:8px; background:var(--primary); display:flex; align-items:center; justify-content:center;">
                <svg class="w-5 h-5" style="width:20px; height:20px; color:#fff;" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <span style="font-size:1.25rem; font-weight:700; color:#fff; margin-left:8px; letter-spacing:1px;">MatiMotoRental</span>
        </div>
        <ul class="sidebar-menu">
            <div class="sidebar-label">Admin Portal</div>
            <li class="<?= $current_page == 'admin_master' ? 'active' : '' ?>"><a href="?page=admin_master"><i class="fa-solid fa-chart-line"></i><span>Master Dashboard</span></a></li>
            <li class="<?= $current_page == 'verify_owners' ? 'active' : '' ?>"><a href="?page=verify_owners"><i class="fa-solid fa-user-check"></i><span>Verify Owners</span></a></li>
            <li class="<?= $current_page == 'verify_customers' ? 'active' : '' ?>"><a href="?page=verify_customers"><i class="fa-solid fa-user-shield"></i><span>Verify Customers</span></a></li>
            
            <div class="sidebar-label">Owner View</div>
            <li class="<?= $current_page == 'dashboard' ? 'active' : '' ?>"><a href="?page=dashboard"><i class="fa-solid fa-house"></i><span>Bookings</span></a></li>
            <li class="<?= $current_page == 'manage_motorcycle' ? 'active' : '' ?>"><a href="?page=manage_motorcycle"><i class="fa-solid fa-motorcycle"></i><span>Manage Fleet</span></a></li>
            
            <div class="sidebar-label">Account</div>
            <li><a href="?logout=true" style="color: #ef4444;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header class="header">
            <div style="font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;">
                Platform Admin Area
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div style="position: relative;">
                    <button onclick="toggleNotif('adminNotif')" class="btn" style="font-size: 1.2rem; color: var(--text-muted); position: relative;">
                        <i class="fa-regular fa-bell"></i>
                        <?php if($admin_unread_count > 0): ?><span class="notification-dot"></span><?php endif; ?>
                    </button>
                    <div id="adminNotif" class="notification-dropdown">
                        <div style="padding:12px 15px; font-weight:bold; border-bottom:1px solid var(--border-color);">Notifications</div>
                        <?php while($notif = mysqli_fetch_assoc($admin_notif_res)): ?>
                            <a href="<?= $notif['link'] ?>" class="notification-item" style="display:block; color:var(--text-main); text-decoration:none; <?= !$notif['is_read'] ? 'background:#f1f5f9;' : '' ?>">
                                <p style="margin:0;"><?= $notif['message'] ?></p>
                                <small style="color:var(--text-muted);"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
                            </a>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($admin_notif_res) == 0): ?>
                            <p style="text-align:center; padding:20px; color:var(--text-muted); font-size:0.9rem;">No notifications yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="text-align: right;"><div style="font-weight: 600; font-size: 0.9rem;"><?= $owner_name ?></div><div style="font-size: 0.75rem; color: var(--text-muted);">Master Admin</div></div>
                <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--sidebar-bg); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; border: 2px solid white;">A</div>
            </div>
        </header>

        <div class="container">
            <?php 
            switch($current_page) {
                
                case 'admin_master': 
                    // -------------------------------------------------------------------
                    // MASTER DASHBOARD: OVERVIEW OF ENTIRE PLATFORM
                    // -------------------------------------------------------------------
                    ?>
                    <div style="margin-bottom: 35px;">
                        <h1 style="margin: 0; font-size: 1.8rem;">Master Dashboard</h1>
                        <p style="margin: 5px 0 0; color: var(--text-muted);">Overview of total bookings and activity across all owners in Mati City.</p>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div><h3>Total Platform Revenue</h3><div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">₱<?= number_format($master_stats['platform_revenue'] ?? 0, 2) ?></div></div>
                            <div class="stat-icon-wrapper" style="background: #eff6ff; color: #3b82f6;"><i class="fa-solid fa-coins"></i></div>
                        </div>
                        <div class="stat-card">
                            <div><h3>Admin Share</h3><div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">₱<?= number_format(($master_stats['platform_revenue'] ?? 0) * 0.05, 2) ?></div></div>
                            <div class="stat-icon-wrapper" style="background: #fdf4ff; color: #d946ef;"><i class="fa-solid fa-percent"></i></div>
                        </div>
                        <div class="stat-card">
                            <div><h3>Total Bookings</h3><div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;"><?= $master_stats['total_bookings'] ?> Rides</div></div>
                            <div class="stat-icon-wrapper" style="background: #f0fdf4; color: #22c55e;"><i class="fa-solid fa-receipt"></i></div>
                        </div>
                        <div class="stat-card">
                            <div><h3>Active Owners</h3><div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;"><?= $master_stats['total_owners'] ?> Users</div></div>
                            <div class="stat-icon-wrapper" style="background: #fff7ed; color: #f97316;"><i class="fa-solid fa-users-gear"></i></div>
                        </div>
                        <div class="stat-card">
                            <div><h3>Total Customers</h3><div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;"><?= $master_stats['total_customers'] ?> Users</div></div>
                            <div class="stat-icon-wrapper" style="background: #faf5ff; color: #a855f7;"><i class="fa-solid fa-users"></i></div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Top Performing Owners</h2>
                            <button class="btn btn-outline">Full Report</button>
                        </div>
                        <table>
                            <thead><tr><th>Owner Name</th><th>Fleet Count</th><th>Total Bookings</th><th>Revenue Generated</th></tr></thead>
                            <tbody>
                                <?php
                                $owner_perf = mysqli_query($conn, "SELECT o.fullname as full_name, o.shopname as username, 
                                    (SELECT COUNT(*) FROM bikes WHERE owner_id=o.ownerid) as fleet,
                                    (SELECT COUNT(*) FROM rentals r JOIN bikes b ON r.bike_id=b.id WHERE b.owner_id=o.ownerid AND r.status != 'Pending') as bookings,
                                    (SELECT SUM(amount_collected) FROM rentals r JOIN bikes b ON r.bike_id=b.id WHERE b.owner_id=o.ownerid AND r.status != 'Pending') as rev
                                    FROM owners o WHERE o.role='owner' ORDER BY rev DESC LIMIT 5");
                                while($row = mysqli_fetch_assoc($owner_perf)): ?>
                                    <tr>
                                        <td><strong><?= $row['full_name'] ?></strong><br><small style="color:var(--text-muted)">@<?= $row['username'] ?></small></td>
                                        <td><?= $row['fleet'] ?> units</td>
                                        <td><?= $row['bookings'] ?> trips</td>
                                        <td style="font-weight: 700; color: var(--accent);">₱<?= number_format($row['rev'] ?? 0, 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php 
                    break;


                case 'verify_owners': 
                    // -------------------------------------------------------------------
                    // OWNER VERIFICATION: OVERSEE USERS
                    // -------------------------------------------------------------------
                    // Get highlight_owner from URL if present
                    $highlight_owner = isset($_GET['highlight_owner']) ? (int)$_GET['highlight_owner'] : null;
                    $search_query = isset($_GET['owner_search']) ? trim($_GET['owner_search']) : '';
                    ?>
                    <style>
                        /* Make only the placeholder text smaller for the owner search input */
                        input[name="owner_search"]::placeholder {
                            font-size: 0.95em;
                            opacity: 0.85;
                        }
                    </style>
                    <div style="margin-bottom: 35px; display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                        <div>
                            <h1>Verify Platform Users</h1>
                            <p style="color: var(--text-muted);">Approve or suspend motorcycle owners operating in Mati City.</p>
                        </div>
                        <form method="get" style="margin-top:0; display:flex; gap:10px; max-width:400px; min-width:260px;">
                            <input type="hidden" name="page" value="verify_owners">
                            <input type="text" name="owner_search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by Name or Email" style="flex:1; padding:10px 14px; border-radius:8px; border:1.2px solid #e2e8f0; font-size:1.1rem; min-width:220px; max-width:420px;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                        </form>
                    </div>

                    <div class="card">
                        <table>
                            <thead><tr><th>Owner Name</th><th>Email</th><th>Business Name</th><th style="text-align:center">Documents</th><th>Status</th><th style="text-align:center">Action</th></tr></thead>
                            <tbody>
                                <?php
                                $owners_sql = "SELECT * FROM owners WHERE role='owner'";
                                if ($search_query !== '') {
                                    $safe = mysqli_real_escape_string($conn, $search_query);
                                    if (is_numeric($search_query)) {
                                        $owners_sql .= " AND ownerid = '$safe'";
                                    } else {
                                        $firstChar = mysqli_real_escape_string($conn, mb_substr($search_query, 0, 1));
                                        $owners_sql .= " AND (fullname LIKE '%$safe%' OR shopname LIKE '%$safe%' OR email LIKE '%$safe%'"
                                            . " OR fullname LIKE '$firstChar%' OR shopname LIKE '$firstChar%' OR email LIKE '$firstChar%')";
                                    }
                                }
                                $owners_sql .= " ORDER BY ownerid DESC";
                                $owners_res = mysqli_query($conn, $owners_sql);
                                while($row = mysqli_fetch_assoc($owners_res)):
                                    // Highlight row if ownerid matches highlight_owner
                                    $row_highlight = ($highlight_owner && $row['ownerid'] == $highlight_owner) ? 'background: #fef9c3; animation: highlightRow 2s;' : '';
                                ?>
                                    <tr<?= $row_highlight ? ' style="'.$row_highlight.'" id="highlighted-owner"' : '' ?>>
                                        <!-- Owner ID removed -->
                                        <td><strong><?= $row['fullname'] ?></strong></td>
                                        <td><code><?= htmlspecialchars($row['email']) ?></code></td>
                                        <td><code><?= $row['shopname'] ?></code></td>
                                        <td>
                                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                                <?php
                                                if (!function_exists('getOwnerDocLink')) {
                                                    function getOwnerDocLink($filename) {
                                                        if (!$filename) return '';
                                                        $file = basename($filename);
                                                        return "../assets/owner_uploads/" . $file;
                                                    }
                                                }
                                                ?>
                                                <?php if (!empty($row['business_permit'])): ?>
                                                    <a href="<?= getOwnerDocLink($row['business_permit']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;" title="<?= getOwnerDocLink($row['business_permit']) ?>">Permit</a>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #fee2e2; color: #991b1b; white-space: nowrap;">No Permit</span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['valid_id'])): ?>
                                                    <a href="<?= getOwnerDocLink($row['valid_id']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;" title="<?= getOwnerDocLink($row['valid_id']) ?>">Valid ID</a>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #fee2e2; color: #991b1b; white-space: nowrap;">No ID</span>
                                                <?php endif; ?>
                                                <?php if (!empty($row['barangay_clearance'])): ?>
                                                    <a href="<?= getOwnerDocLink($row['barangay_clearance']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;" title="<?= getOwnerDocLink($row['barangay_clearance']) ?>">Barangay Clearance</a>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #fee2e2; color: #991b1b; white-space: nowrap;">No Brgy Clearance</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge badge-<?= strtolower($row['status']) ?>"><?= $row['status'] ?></span></td>
                                        <td style="text-align:center;">
                                            <div style="display: flex; flex-direction: row; gap: 8px; justify-content: center; align-items: center;">
                                                <button class="btn btn-outline" onclick='openModal("owner", <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'><i class="fa-solid fa-eye"></i> View</button>
                                                <?php if (strtolower($row['status']) !== 'active'): ?>
                                                    <?php 
                                                    // Check if all documents are uploaded
                                                    $has_docs = !empty($row['business_permit']) && !empty($row['valid_id']) && !empty($row['barangay_clearance']);
                                                    if ($has_docs): ?>
                                                        <form method="POST" style="margin:0;">
                                                            <input type="hidden" name="owner_id" value="<?= $row['ownerid'] ?>">
                                                            <button type="submit" name="verify_owner" class="btn btn-success"><i class="fa-solid fa-check"></i> Verify</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline" style="color: #94a3b8; cursor: not-allowed;" title="Missing Documents"><i class="fa-solid fa-triangle-exclamation"></i> Incomplete</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <button class="btn btn-outline" style="color: #ef4444;" onclick="openSuspendModal(<?= $row['ownerid'] ?>, '<?= htmlspecialchars($row['fullname'], ENT_QUOTES) ?>')"><i class="fa-solid fa-ban"></i> Suspend</button>
                                                <a href="?delete_owner=<?= $row['ownerid'] ?>" onclick="return confirm('Are you sure you want to delete this owner? This will remove all their bikes and data.')" class="btn btn-outline" style="color: #b91c1c;"><i class="fa-solid fa-trash"></i> Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <style>
                    @keyframes highlightRow {
                        0% { background: #fef08a; }
                        100% { background: #fef9c3; }
                    }
                    </style>
                    <script>
                    // Scroll to highlighted owner row if present
                    window.addEventListener('DOMContentLoaded', function() {
                        var row = document.getElementById('highlighted-owner');
                        if(row) {
                            row.scrollIntoView({behavior: 'smooth', block: 'center'});
                        }
                    });
                    </script>
                    <?php 
                    break;

                case 'verify_customers':
                    ?>

                    <style>
                        /* Make only the placeholder text smaller for the customer search input */
                        input[name="customer_search"]::placeholder {
                            font-size: 0.95em;
                            opacity: 0.85;
                        }
                    </style>
                    <div style="margin-bottom: 35px; display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap;">
                        <div>
                            <h1>Manage Customer Accounts</h1>
                            <p style="color: var(--text-muted);">View and verify customer identifications.</p>
                        </div>
                        <form method="get" style="margin-top:0; display:flex; gap:10px; max-width:400px; min-width:260px;">
                            <input type="hidden" name="page" value="verify_customers">
                            <input type="text" name="customer_search" value="<?= isset($_GET['customer_search']) ? htmlspecialchars($_GET['customer_search']) : '' ?>" placeholder="Search by Name or Email" style="flex:1; padding:10px 14px; border-radius:8px; border:1.2px solid #e2e8f0; font-size:1.1rem; min-width:220px; max-width:420px;">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Search</button>
                        </form>
                    </div>

                    <?php if(isset($_GET['error']) && $_GET['error'] == 'incomplete'): ?>
                        <div style="padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fca5a5;">
                            <i class="fa-solid fa-circle-exclamation"></i> <strong>Cannot Verify:</strong> Customer is missing required documents (Profile Picture, Phone, License, or ID).
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <table>
                            <thead><tr><th>Customer Name</th><th>Email</th><th>Documents</th><th>Status</th><th style="text-align:center">Action</th></tr></thead>
                            <tbody>
                                <?php
                                $customer_search = isset($_GET['customer_search']) ? trim($_GET['customer_search']) : '';
                                $customers_sql = "SELECT * FROM customers WHERE role='customer'";
                                if ($customer_search !== '') {
                                    $safe = mysqli_real_escape_string($conn, $customer_search);
                                    if (is_numeric($customer_search)) {
                                        $customers_sql .= " AND userid = '$safe'";
                                    } else {
                                        $customers_sql .= " AND (fullname LIKE '%$safe%' OR email LIKE '%$safe%')";
                                    }
                                }
                                $customers_sql .= " ORDER BY is_verified ASC, userid DESC";
                                $customers_res = mysqli_query($conn, $customers_sql);
                                if (mysqli_num_rows($customers_res) > 0) {
                                    while($row = mysqli_fetch_assoc($customers_res)): ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <div style="width:40px; height:40px; border-radius:50%; background:#f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                                        <?php if(!empty($row['profile_image'])): ?>
                                                            <img src="../<?= htmlspecialchars($row['profile_image']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                                        <?php else: ?>
                                                            <span style="font-weight:bold; color:#64748b;"><?= strtoupper(substr($row['fullname'], 0, 1)) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <strong><?= htmlspecialchars($row['fullname']) ?></strong>
                                                </div>
                                            </td>
                                            <td><code><?= htmlspecialchars($row['email']) ?></code></td>
                                            <td>
                                                <?php if (!empty($row['drivers_license_image'])): ?>
                                                    <a href="../<?= htmlspecialchars($row['drivers_license_image']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;">License</a>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">No License</span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['valid_id_image'])): ?>
                                                    <a href="../<?= htmlspecialchars($row['valid_id_image']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 0.8rem;">Valid ID</a>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">No ID</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if($row['is_verified']): ?>
                                                    <span class="badge badge-verified">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge badge-pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center">
                                                <button class="btn btn-outline" onclick='openModal("customer", <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'><i class="fa-solid fa-eye"></i> View</button>
                                                <?php if (!$row['is_verified']): ?>
                                                    <a href="?verify_customer=<?= $row['userid'] ?>" class="btn btn-success"><i class="fa-solid fa-check"></i> Approve</a>
                                                <?php endif; ?>
                                                <a href="?delete_customer=<?= $row['userid'] ?>" onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.')" class="btn btn-outline" style="color: #ef4444;"><i class="fa-solid fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile;
                                } else {
                                    echo '<tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">No customers found.</td></tr>';
                                } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                case 'dashboard': 
                    ?>
                    <div style="margin-bottom: 35px;">
                        <h1>Approved Bookings</h1>
                        <p style="color: var(--text-muted);">List of all active and completed rentals across the platform.</p>
                    </div>

                    <div class="card">
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Bike Model</th>
                                    <th>Owner</th>
                                    <th>Start Date</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $bookings_query = "SELECT r.id, r.rental_start_date, r.status, r.amount_collected, c.fullname as customer_name, c.profile_image, b.model_name as bike_model, o.shopname as owner_shop FROM rentals r LEFT JOIN customers c ON r.customer_id = c.userid LEFT JOIN bikes b ON r.bike_id = b.id LEFT JOIN owners o ON r.owner_id = o.ownerid WHERE r.status IN ('Active', 'Completed') ORDER BY r.rental_start_date DESC";
                                $bookings_res = mysqli_query($conn, $bookings_query);
                                if (mysqli_num_rows($bookings_res) > 0) {
                                    while($row = mysqli_fetch_assoc($bookings_res)):
                                        $status_badge = ($row['status'] == 'Active') ? '<span class="badge" style="background: #e0f2fe; color: #0284c7;">Active</span>' : '<span class="badge badge-verified">Completed</span>';
                                ?>
                                    <tr>
                                        <td><strong>#<?= $row['id'] ?></strong></td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <div style="width:32px; height:32px; border-radius:50%; background:#f1f5f9; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                                    <?php if(!empty($row['profile_image'])): ?>
                                                        <img src="../<?= htmlspecialchars($row['profile_image']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                                    <?php else: ?>
                                                        <span style="font-weight:bold; color:#64748b; font-size:0.7rem;"><?= strtoupper(substr($row['customer_name'] ?? 'N', 0, 1)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($row['bike_model'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($row['owner_shop'] ?? 'N/A') ?></td>
                                        <td><?= date('M d, Y', strtotime($row['rental_start_date'])) ?></td>
                                        <td><?= $status_badge ?></td>
                                        <td style="font-weight: 700; color: var(--accent);">$<?= number_format($row['amount_collected'], 2) ?></td>
                                    </tr>
                                <?php
                                    endwhile;
                                } else {
                                    echo '<tr><td colspan="7" style="text-align:center; padding: 40px; color: var(--text-muted);">No approved bookings found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                    break;

                case 'manage_motorcycle': 
                    include_logic_fleet($conn); 
                    break;

                
                default:
                    echo "<div class='card' style='padding:100px; text-align:center;'><h2>Section Under Construction</h2></div>";
                    break;
            } ?>
        </div>
    </main>

<?php
// --- SIMULATED INCLUDES FOR PREVIEW ---
function include_logic_fleet($conn) { ?>
    <div style="margin-bottom: 30px;"><h1>Fleet Management</h1><p>Manage your motorcycle availability.</p></div>
    <div class="card">
        <table>
            <thead><tr><th>Bike Model</th><th>Plate Number</th><th>Daily Rate</th><th>Status</th><th>Owner</th><th style="text-align:center;">Action</th></tr></thead>
            <tbody>
                <?php
                $bikes_res = mysqli_query($conn, "SELECT b.*, o.fullname as owner_name, 
                    r.id as rental_id, r.rental_start_date, r.expected_return_date, 
                    c.fullname as customer_name, c.phone_number 
                    FROM bikes b 
                    LEFT JOIN owners o ON b.owner_id = o.ownerid 
                    LEFT JOIN rentals r ON b.id = r.bike_id AND r.status IN ('Active', 'Overdue')
                    LEFT JOIN customers c ON r.customer_id = c.userid 
                    ORDER BY b.id DESC");
                
                while($row = mysqli_fetch_assoc($bikes_res)): 
                    $is_rented = !empty($row['rental_id']);
                    $is_overdue = false;
                    $late_days = 0;
                    $penalty = 0;
                    $row_style = "";
                    
                    if ($is_rented && $row['expected_return_date']) {
                        $due = new DateTime($row['expected_return_date']);
                        $now = new DateTime();
                        $due->setTime(0,0,0);
                        $now->setTime(0,0,0);
                        if ($now > $due) {
                            $is_overdue = true;
                            $late_days = $now->diff($due)->days;
                            $penalty = ($late_days * $row['daily_rate']) * 1.10;
                            $row_style = "background-color: #fff1f2;"; // Light red highlight
                        }
                    }
                    
                    $bike_json = json_encode([
                        'model' => $row['model_name'],
                        'plate' => $row['plate_number'],
                        'type' => $row['type'],
                        'trans' => $row['transmission'],
                        'fuel' => $row['fuel_type'],
                        'engine' => $row['engine_capacity'],
                        'owner' => $row['owner_name'],
                        'status' => $row['status'],
                        'image' => $row['image_url'],
                        'rate' => $row['daily_rate']
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>
                    <tr style="<?= $row_style ?>">
                        <td><strong><?= $row['model_name'] ?></strong></td>
                        <td><code><?= $row['plate_number'] ?></code></td>
                        <td>$<?= number_format($row['daily_rate'], 2) ?></td>
                        <td>
                            <span class="badge" style="background: #f1f5f9; color: #475569;"><?= $row['status'] ?></span>
                            <?php if($is_overdue): ?>
                                <span class="badge" style="background: #ef4444; color: white; margin-left:5px;">Late</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['owner_name'] ?? 'Unknown') ?></td>
                        <td style="text-align:center;">
                            <div style="display:flex; gap:5px; justify-content:center;">
                                <button class="btn btn-outline" onclick='openModal("bike", <?= $bike_json ?>)' title="View Details"><i class="fa-solid fa-eye"></i></button>
                                <?php if($row['status'] !== 'Maintenance' && $row['status'] !== 'Rented'): ?>
                                    <a href="?page=manage_motorcycle&suspend_bike=<?= $row['id'] ?>" class="btn btn-outline" style="color:#f59e0b;" title="Suspend (Maintenance)" onclick="return confirm('Set this unit to Maintenance?')"><i class="fa-solid fa-ban"></i></a>
                                <?php endif; ?>
                                <a href="?page=manage_motorcycle&delete_bike=<?= $row['id'] ?>" class="btn btn-outline" style="color:#ef4444;" title="Delete" onclick="return confirm('Permanently delete this motorcycle?')"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php }

?>

<!-- Detail Modal -->
<div id="detailModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Details</h2>
            <button onclick="closeModal()">&times;</button>
        </div>
        <div id="modalBody" class="modal-body">
            <!-- Content injected by JS -->
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div id="suspendModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Suspend Owner</h2>
            <button onclick="closeSuspendModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="owner_id" id="suspend_owner_id">
            <div class="modal-body">
                <p>Provide a reason for suspending <span id="suspend_owner_name"></span>:</p>
                <textarea name="suspend_reason" id="suspend_reason" rows="3" required style="width:100%;"></textarea>
            </div>
            <div style="text-align:right; margin-top:15px;">
                <button type="button" class="btn btn-outline" onclick="closeSuspendModal()">Cancel</button>
                <button type="submit" name="suspend_owner" class="btn btn-primary">Suspend Owner</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(type, data) {
        const modal = document.getElementById('detailModal');
        const title = document.getElementById('modalTitle');
        const body = document.getElementById('modalBody');
        
        modal.style.display = 'flex';
        
        if (type === 'owner') {
            title.innerText = 'Owner Details';
            let ownerProfileImg = data.profile_image ? `../${data.profile_image}` : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.fullname) + '&background=random';
            function getFilename(path) {
                if (!path) return '';
                return path.split('\\').pop().split('/').pop();
            }
            let permitFile = getFilename(data.business_permit);
            let brgyFile = getFilename(data.barangay_clearance);
            let validIdFile = getFilename(data.valid_id);

            // Parse structured address fields if available
            let province = data.province || 'Davao Oriental';
            let city = data.city || 'Mati City';
            let barangay = data.barangay || '';
            let street = data.street_landmark || '';

            // If only address/location string is available, try to split it
            if ((!data.province || !data.city || !data.barangay) && data.location) {
                let parts = data.location.split(',').map(x => x.trim());
                province = parts[0] || province;
                city = parts[1] || city;
                barangay = parts[2] || '';
                street = parts[3] || '';
            }

            body.innerHTML = `
                <div style="text-align:center; margin-bottom:20px;">
                    <img src="${ownerProfileImg}" style="width:90px; height:90px; border-radius:50%; object-fit:cover; border:3px solid #e5e7eb;">
                </div>
                <p><strong>Full Name:</strong> <span>${data.fullname}</span></p>
                <p><strong>Shop Name:</strong> <span>${data.shopname}</span></p>
                <p><strong>Email:</strong> <span>${data.email}</span></p>
                <p><strong>Phone:</strong> <span>${data.phone_number || 'N/A'}</span></p>
                <p><strong>Province:</strong> <span>${province}</span></p>
                <p><strong>City/Municipality:</strong> <span>${city}</span></p>
                <p><strong>Barangay:</strong> <span>${barangay}</span></p>
                <p><strong>Street/Landmark:</strong> <span>${street || 'N/A'}</span></p>
                <p><strong>Status:</strong> <span style="text-transform:capitalize">${data.status}</span></p>
                <p><strong>Registered:</strong> <span>${data.created_at}</span></p>
                <hr style="margin:18px 0; border:0; border-top:1px solid #e2e8f0;">
                <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">
                    ${permitFile ? `<a href="../assets/owner_uploads/${permitFile}" target="_blank" class="btn btn-outline" style="flex:1; min-width:120px; justify-content:center;"><i class="fa-solid fa-file-contract"></i> Business Permit</a>` : ''}
                    ${brgyFile ? `<a href="../assets/owner_uploads/${brgyFile}" target="_blank" class="btn btn-outline" style="flex:1; min-width:120px; justify-content:center;"><i class="fa-solid fa-file-shield"></i> Barangay Clearance</a>` : ''}
                    ${validIdFile ? `<a href="../assets/owner_uploads/${validIdFile}" target="_blank" class="btn btn-outline" style="flex:1; min-width:120px; justify-content:center;"><i class="fa-solid fa-address-card"></i> Valid ID</a>` : ''}
                </div>
            `;
        } else if (type === 'bike') {
            title.innerText = 'Motorcycle Details';
            let img = data.image ? '../' + data.image : 'https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=800';
            body.innerHTML = `
                <div style="text-align:center; margin-bottom:20px;">
                    <img src="${img}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px;">
                </div>
                <p><strong>Model:</strong> <span>${data.model}</span></p>
                <p><strong>Plate No:</strong> <span>${data.plate}</span></p>
                <p><strong>Type:</strong> <span>${data.type}</span></p>
                <p><strong>Transmission:</strong> <span>${data.trans}</span></p>
                <p><strong>Fuel Type:</strong> <span>${data.fuel}</span></p>
                <p><strong>Engine:</strong> <span>${data.engine || 'N/A'}</span></p>
                <p><strong>Owner:</strong> <span>${data.owner}</span></p>
                <p><strong>Status:</strong> <span>${data.status}</span></p>
            `;
        } else if (type === 'rental') {
            title.innerText = 'Rental Details';
            let lateHtml = '';
            if (data.is_overdue) {
                lateHtml = `
                    <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-top:15px; border:1px solid #fca5a5;">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:5px;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <strong style="font-size:1.1em;">Overdue by ${data.late_days} Day(s)</strong>
                        </div>
                        <p style="margin:0; font-size:0.9em;">Total Late Fee (+10%): <strong style="font-size:1.2em;">$${parseFloat(data.penalty).toFixed(2)}</strong></p>
                    </div>
                `;
            }
            body.innerHTML = `
                <div style="display:grid; gap:10px;">
                    <p><strong>Booking Ref:</strong> <span>#${data.rental_id}</span></p>
                    <p><strong>Customer:</strong> <span>${data.customer_name}</span></p>
                    <p><strong>Phone:</strong> <span>${data.customer_phone || 'N/A'}</span></p>
                    <hr style="border:0; border-top:1px solid #eee; margin:5px 0;">
                    <p><strong>Picked Up:</strong> <span>${data.start_date}</span></p>
                    <p><strong>Return Due:</strong> <span>${data.due_date}</span></p>
                </div>
                ${lateHtml}
            `;
        } else {
            title.innerText = 'Customer Details';
            const verifiedStatus = data.is_verified == 1 ? '<span style="color:#166534; font-weight:bold;">Verified</span>' : '<span style="color:#92400e; font-weight:bold;">Unverified</span>';
            const profileImg = data.profile_image ? '../' + data.profile_image : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.fullname) + '&background=random';
            
            body.innerHTML = `
                <div style="text-align:center; margin-bottom:25px;">
                    <img src="${profileImg}" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:4px solid #f1f5f9; margin:0 auto;">
                </div>
                <p><strong>Full Name:</strong> <span>${data.fullname}</span></p>
                <p><strong>Email:</strong> <span>${data.email}</span></p>
                <p><strong>Phone:</strong> <span>${data.phone_number || 'N/A'}</span></p>
                <p><strong>Status:</strong> <span>${verifiedStatus}</span></p>
                <p><strong>Account State:</strong> <span style="text-transform:capitalize">${data.status}</span></p>
                <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">
                <div style="display:flex; gap:10px; justify-content:center;">
                    ${data.drivers_license_image ? `<a href="../${data.drivers_license_image}" target="_blank" class="btn btn-outline" style="flex:1; justify-content:center;"><i class="fa-solid fa-id-card"></i> License</a>` : ''}
                    ${data.valid_id_image ? `<a href="../${data.valid_id_image}" target="_blank" class="btn btn-outline" style="flex:1; justify-content:center;"><i class="fa-solid fa-address-card"></i> Valid ID</a>` : ''}
                </div>
            `;
        }
    }

    function closeModal() {
        document.getElementById('detailModal').style.display = 'none';
    }

    // Close on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('detailModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    function openSuspendModal(ownerId, ownerName) {
        document.getElementById('suspend_owner_id').value = ownerId;
        document.getElementById('suspend_owner_name').textContent = ownerName;
        document.getElementById('suspend_reason').value = '';
        document.getElementById('suspendModal').style.display = 'flex';
    }
    function closeSuspendModal() {
        document.getElementById('suspendModal').style.display = 'none';
    }

    // Close on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('suspendModal');
        if (event.target == modal) {
            closeSuspendModal();
        }
    }

    function toggleNotif(id) {
        const dropdown = document.getElementById(id);
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';

        if (!isVisible) {
            // Mark as read via AJAX
            fetch('../mark_as_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'user_type=admin'
            }).then(() => {
                const dot = dropdown.previousElementSibling.querySelector('.notification-dot');
                if(dot) dot.style.display = 'none';
            });
        }
    }

    window.addEventListener('click', function(e) {
        if (!e.target.closest('.notification-dropdown') && !e.target.closest('button[onclick^="toggleNotif"]')) {
            document.querySelectorAll('.notification-dropdown').forEach(d => d.style.display = 'none');
        }
    });

    window.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($_SESSION['owner_verified_success'])): ?>
            alert('Owner verified successfully!');
            <?php unset($_SESSION['owner_verified_success']); ?>
        <?php endif; ?>
    });
</script>

</body>
</html>