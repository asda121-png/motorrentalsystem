<?php
/**
 * db.php - Database Configuration & Initialization
 */
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';
$db_name = 'moto_rental_db';

$conn = @mysqli_connect($db_host, $db_user, $db_pass);

if (!$conn) {
    die("<div style='padding:20px; background:#ffdddd; color:#aa0000; font-family:sans-serif;'>
            <strong>Database Connection Error:</strong> Please ensure MySQL is running.
         </div>");
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS $db_name");
mysqli_select_db($conn, $db_name);

// Schema Creation
$tables = [
    "CREATE TABLE IF NOT EXISTS customers (
    userid INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    status ENUM('pending', 'active', 'disabled') DEFAULT 'pending',
    is_verified TINYINT(1) DEFAULT 0,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20),
    profile_image VARCHAR(255),
    drivers_license_image VARCHAR(255),
    valid_id_image VARCHAR(255),
    hashedpassword VARCHAR(255) NOT NULL,
    last_login DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_last_login (last_login)
) ENGINE=InnoDB;",
    "CREATE TABLE IF NOT EXISTS owners (
    ownerid INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    shopname VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    hashedpassword VARCHAR(255) NOT NULL,
    role ENUM('owner') DEFAULT 'owner',
    status ENUM('active', 'disabled', 'pending') DEFAULT 'pending',
    last_activity DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB;",
    "CREATE TABLE IF NOT EXISTS bikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    daily_rate DECIMAL(10, 2) NOT NULL,
    status ENUM('Available', 'Rented', 'Maintenance') DEFAULT 'Available',
    type VARCHAR(50) DEFAULT 'Scooter',
    transmission VARCHAR(50) DEFAULT 'Automatic',
    inclusions VARCHAR(255) DEFAULT 'Helmet',
    description TEXT,
    last_tire_change DATE NULL,
    last_oil_change DATE NULL,
    fuel_level INT DEFAULT 100,
    image_url VARCHAR(255) DEFAULT NULL,
    
    FOREIGN KEY (owner_id) REFERENCES owners(ownerid) ON DELETE CASCADE,
    
    INDEX idx_owner (owner_id),
    INDEX idx_status (status),
    INDEX idx_plate (plate_number)
) ENGINE=InnoDB;" , 
 "CREATE TABLE IF NOT EXISTS rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bike_id INT NOT NULL,
    customer_id INT NOT NULL,
    owner_id INT NOT NULL,  -- For easy owner reporting
    amount_collected DECIMAL(10, 2) NOT NULL,
    rental_start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    rental_end_date DATETIME NULL,
    expected_return_date DATE NULL,
    status ENUM('Pending', 'Active', 'Completed', 'Overdue') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (bike_id) REFERENCES bikes(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(userid) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES owners(ownerid) ON DELETE CASCADE,
    
    INDEX idx_customer (customer_id),
    INDEX idx_bike (bike_id),
    INDEX idx_owner (owner_id),
    INDEX idx_status (status),
    INDEX idx_dates (rental_start_date, rental_end_date)
) ENGINE=InnoDB;" 
,
    "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('admin', 'owner', 'customer') NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, user_type)
) ENGINE=InnoDB;"
];

foreach ($tables as $sql) { mysqli_query($conn, $sql); }

// --- SCHEMA UPDATES (ALTER if columns missing) ---
$cols_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'type'");
if (mysqli_num_rows($cols_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN type VARCHAR(50) DEFAULT 'Scooter' AFTER model_name");
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN last_tire_change DATE NULL AFTER status");
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN last_oil_change DATE NULL AFTER last_tire_change");
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN fuel_level INT DEFAULT 100 AFTER last_oil_change");
}

// Check for image_url column
$img_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'image_url'");
if (mysqli_num_rows($img_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER fuel_level");
}

// Check for transmission column
$trans_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'transmission'");
if (mysqli_num_rows($trans_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN transmission VARCHAR(50) DEFAULT 'Automatic' AFTER type");
}

// Check for inclusions column
$inc_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'inclusions'");
if (mysqli_num_rows($inc_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN inclusions VARCHAR(255) DEFAULT 'Helmet' AFTER transmission");
}

// Check for description column
$desc_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'description'");
if (mysqli_num_rows($desc_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN description TEXT AFTER inclusions");
}

// --- CUSTOMER PROFILE UPDATES ---
$cust_phone = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'phone_number'");
if (mysqli_num_rows($cust_phone) == 0) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN phone_number VARCHAR(20) AFTER email");
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER phone_number");
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN drivers_license_image VARCHAR(255) DEFAULT NULL AFTER profile_image");
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN valid_id_image VARCHAR(255) DEFAULT NULL AFTER drivers_license_image");
}

// Check for customer status update to include 'pending'
$cust_status_check = mysqli_query($conn, "SHOW COLUMNS FROM customers WHERE Field = 'status' AND Type LIKE '%pending%'");
if (mysqli_num_rows($cust_status_check) == 0) {
    mysqli_query($conn, "ALTER TABLE customers MODIFY COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'pending'");
}

// Check for is_verified column
$cust_verified_check = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'is_verified'");
if (mysqli_num_rows($cust_verified_check) == 0) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER status");
}

// Check for damage columns in rentals
$rental_damage_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'damage_notes'");
if (mysqli_num_rows($rental_damage_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN damage_notes TEXT DEFAULT NULL AFTER status");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN repair_cost DECIMAL(10, 2) DEFAULT 0.00 AFTER damage_notes");
}

// Check for feedback columns in rentals
$rental_feedback_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'feedback'");
if (mysqli_num_rows($rental_feedback_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN rating INT DEFAULT NULL AFTER repair_cost");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN feedback TEXT DEFAULT NULL AFTER rating");
}

// --- AUTOMATIC MAINTENANCE CHECK ---
// If last_tire_change is > 2 months ago, set status to Maintenance (only if currently Available)
mysqli_query($conn, "UPDATE bikes SET status='Maintenance' WHERE last_tire_change < DATE_SUB(NOW(), INTERVAL 2 MONTH) AND status='Available'");

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

// Helper function for Global Stats
function getGlobalStats($conn) {
    $stats = ['total_bikes' => 0, 'rented_count' => 0, 'total_cash' => 0.00];
    
    $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM bikes");
    $stats['total_bikes'] = mysqli_fetch_assoc($res)['total'];
    
    $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM bikes WHERE status='Rented'");
    $stats['rented_count'] = mysqli_fetch_assoc($res)['total'];
    
    $res = mysqli_query($conn, "SELECT SUM(amount_collected) as total FROM rentals");
    $stats['total_cash'] = mysqli_fetch_assoc($res)['total'] ?? 0;
    
    return $stats;
}