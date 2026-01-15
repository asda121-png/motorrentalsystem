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
    status ENUM('pending', 'active', 'disabled') DEFAULT 'active',
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
    business_permit VARCHAR(255) DEFAULT NULL,
    valid_id VARCHAR(255) DEFAULT NULL,
    barangay_clearance VARCHAR(255) DEFAULT NULL,
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
    registered_until DATE NULL,
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
    exact_pickup_date DATETIME NULL,
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

// Check for next_maintenance column
$maint_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'next_maintenance'");
if (mysqli_num_rows($maint_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN next_maintenance DATE NULL AFTER status");
}

// Check for registered_until column
$reg_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE 'registered_until'");
if (mysqli_num_rows($reg_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN registered_until DATE NULL AFTER plate_number");
}

// --- OWNER SCHEMA UPDATES ---
$owner_doc_cols = ['business_permit', 'valid_id', 'barangay_clearance'];
foreach ($owner_doc_cols as $col) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM owners LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE owners ADD COLUMN $col VARCHAR(255) DEFAULT NULL");
    }
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
    mysqli_query($conn, "ALTER TABLE customers MODIFY COLUMN status ENUM('pending', 'active', 'disabled') DEFAULT 'active'");
}

// Ensure default status is active so unverified customers can still login
mysqli_query($conn, "ALTER TABLE customers ALTER COLUMN status SET DEFAULT 'active'");

// Update existing pending customers to active so they can login (verification is handled by is_verified)
mysqli_query($conn, "UPDATE customers SET status='active' WHERE status='pending'");

// Check for is_verified column
$cust_verified_check = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'is_verified'");
if (mysqli_num_rows($cust_verified_check) == 0) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER status");
}

// Check for exact_pickup_date column
$pickup_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'exact_pickup_date'");
if (mysqli_num_rows($pickup_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN exact_pickup_date DATETIME NULL AFTER rental_start_date");
}

// Check for exact_pickup_date column
$pickup_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'exact_pickup_date'");
if (mysqli_num_rows($pickup_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN exact_pickup_date DATETIME NULL AFTER rental_start_date");
}

// Check for inspection columns in rentals
$inspection_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'pickup_fuel_level'");
if (mysqli_num_rows($inspection_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN pickup_fuel_level INT DEFAULT 100 AFTER exact_pickup_date");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN pickup_condition VARCHAR(50) DEFAULT 'Good' AFTER pickup_fuel_level");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN return_fuel_level INT DEFAULT NULL AFTER rental_end_date");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN return_condition VARCHAR(50) DEFAULT NULL AFTER return_fuel_level");
}

// Check for image proof columns
$img_proof_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'pickup_images'");
if (mysqli_num_rows($img_proof_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN pickup_images TEXT NULL AFTER pickup_condition");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN return_images TEXT NULL AFTER return_condition");
}

// Check expected_return_date type and modify to DATETIME if it is DATE so we can store time
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

// Check for damage columns in rentals
$rental_damage_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'damage_notes'");
if (mysqli_num_rows($rental_damage_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN damage_notes TEXT DEFAULT NULL AFTER status");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN repair_cost DECIMAL(10, 2) DEFAULT 0.00 AFTER damage_notes");
}

// Check specifically for penalty_amount (Fix for undefined array key warning)
$penalty_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'penalty_amount'");
if (mysqli_num_rows($penalty_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN penalty_amount DECIMAL(10, 2) DEFAULT 0.00 AFTER repair_cost");
}

// Check for deposit tracking columns
$deposit_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'deposit_collected'");
if (mysqli_num_rows($deposit_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN deposit_collected TINYINT(1) DEFAULT 0 AFTER amount_collected");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN deposit_returned TINYINT(1) DEFAULT 0 AFTER penalty_amount");
}

// Check for feedback columns in rentals
$rental_feedback_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'feedback'");
if (mysqli_num_rows($rental_feedback_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN rating INT DEFAULT NULL AFTER repair_cost");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN feedback TEXT DEFAULT NULL AFTER rating");
}

// Check for 'Approved' in rentals status
$rental_status_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals WHERE Field = 'status' AND Type LIKE '%Approved%'");
if (mysqli_num_rows($rental_status_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals MODIFY COLUMN status ENUM('Pending', 'Approved', 'Active', 'Completed', 'Overdue') DEFAULT 'Active'");
}

// Check for 'Reserved' in bikes status
$bike_status_check = mysqli_query($conn, "SHOW COLUMNS FROM bikes WHERE Field = 'status' AND Type LIKE '%Reserved%'");
if (mysqli_num_rows($bike_status_check) == 0) {
    mysqli_query($conn, "ALTER TABLE bikes MODIFY COLUMN status ENUM('Available', 'Reserved', 'Rented', 'Maintenance') DEFAULT 'Available'");
}

// --- NEW COLUMNS FOR ENHANCED VEHICLE DETAILS ---
$new_bike_cols = [
    'year_model' => "INT NULL",
    'color' => "VARCHAR(50) NULL",
    'security_deposit' => "DECIMAL(10, 2) DEFAULT 0.00",
    'security_deposit_rules' => "TEXT NULL",
    'overtime_fee' => "DECIMAL(10, 2) DEFAULT 0.00",
    'fuel_policy' => "VARCHAR(50) DEFAULT 'Full-to-Full'",
    'late_penalty' => "DECIMAL(10, 2) DEFAULT 0.00",
    'pickup_location' => "VARCHAR(255) NULL",
    'fuel_type' => "VARCHAR(50) DEFAULT 'Gasoline'",
    'engine_capacity' => "VARCHAR(50) NULL",
    'max_speed' => "VARCHAR(50) NULL",
    'mileage' => "VARCHAR(50) NULL",
    'displacement' => "INT NULL",
    'insurance_coverage' => "TEXT NULL",
    'condition_status' => "ENUM('Excellent', 'Good', 'Fair') DEFAULT 'Good'",
    'last_maintenance' => "DATE NULL"
];

foreach ($new_bike_cols as $col => $def) {
    $check = mysqli_query($conn, "SHOW COLUMNS FROM bikes LIKE '$col'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "ALTER TABLE bikes ADD COLUMN $col $def");
    }
}

// --- AUTOMATIC MAINTENANCE CHECK ---
// If next_maintenance date has passed, set status to Maintenance (only if currently Available)
mysqli_query($conn, "UPDATE bikes SET status='Maintenance' WHERE next_maintenance < CURDATE() AND status='Available'");

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