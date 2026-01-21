<?php
session_start();
/**
 * manage_motorcycle.php - Fleet Management Page
 * Allows the owner to view the fleet and perform quick rental/return actions.
 */
require_once 'db.php';

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

// Set page identification for the header
$page_title = "Manage Motorcycle";
$active_nav = "manage";

// Check Owner Status
$status_check = mysqli_query($conn, "SELECT status FROM owners WHERE ownerid=" . $_SESSION['userid']);
$owner_status = mysqli_fetch_assoc($status_check)['status'] ?? 'pending';

// --- HANDLE FORM SUBMISSION (ADD UNIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $owner_id = $_SESSION['userid'];

    $model_name = mysqli_real_escape_string($conn, $_POST['model_name']);
    $plate_number = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $transmission = mysqli_real_escape_string($conn, $_POST['transmission']);
    $inclusions = mysqli_real_escape_string($conn, $_POST['inclusions']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $daily_rate = (float)$_POST['daily_rate'];
    $fuel_level = (int)$_POST['fuel_level'];
    $next_maintenance = $_POST['next_maintenance'];
    $registered_until = !empty($_POST['registered_until']) ? "'" . mysqli_real_escape_string($conn, $_POST['registered_until']) . "'" : "NULL";

    // New Fields
    $year_model = (int)$_POST['year_model'];
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $security_deposit = (float)$_POST['security_deposit'];
    $security_deposit_rules = mysqli_real_escape_string($conn, $_POST['security_deposit_rules']);
    $overtime_fee = (float)$_POST['overtime_fee'];
    $fuel_policy = mysqli_real_escape_string($conn, $_POST['fuel_policy']);
    $late_penalty = (float)$_POST['late_penalty'];
    $pickup_location = mysqli_real_escape_string($conn, $_POST['pickup_location']);
    $fuel_type = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    $engine_capacity = mysqli_real_escape_string($conn, $_POST['engine_capacity']);
    $max_speed = mysqli_real_escape_string($conn, $_POST['max_speed']);
    $mileage = mysqli_real_escape_string($conn, $_POST['mileage']);
    $displacement = (int)$_POST['displacement'];
    $insurance_coverage = mysqli_real_escape_string($conn, $_POST['insurance_coverage']);
    $condition_status = mysqli_real_escape_string($conn, $_POST['condition_status']);
    $last_maintenance = !empty($_POST['last_maintenance']) ? "'" . mysqli_real_escape_string($conn, $_POST['last_maintenance']) . "'" : "NULL";

    // Handle Image Upload
    $image_url = "NULL";
    if (isset($_FILES['bike_image']) && $_FILES['bike_image']['error'] == 0) {
        $target_dir = "../assets/picture of motors/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES["bike_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = uniqid("moto_") . "." . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["bike_image"]["tmp_name"], $target_file)) {
            // Save path relative to project root
            $db_path = "assets/picture of motors/" . $new_filename;
            $image_url = "'" . mysqli_real_escape_string($conn, $db_path) . "'";
        }
    }

    if ($owner_status !== 'active') {
        $error_msg = "Account not verified. You cannot add motorcycles until your account is approved.";
    } elseif ($daily_rate < 0) {
        $error_msg = "Daily rate cannot be less than 0.";
    } else {
        $insert_sql = "INSERT INTO bikes (owner_id, model_name, plate_number, type, transmission, inclusions, description, daily_rate, status, fuel_level, next_maintenance, registered_until, image_url, year_model, color, security_deposit, security_deposit_rules, overtime_fee, fuel_policy, late_penalty, pickup_location, fuel_type, engine_capacity, max_speed, mileage, displacement, insurance_coverage, condition_status, last_maintenance) 
                       VALUES ('$owner_id', '$model_name', '$plate_number', '$type', '$transmission', '$inclusions', '$description', '$daily_rate', 'Available', '$fuel_level', '$next_maintenance', $registered_until, $image_url, '$year_model', '$color', '$security_deposit', '$security_deposit_rules', '$overtime_fee', '$fuel_policy', '$late_penalty', '$pickup_location', '$fuel_type', '$engine_capacity', '$max_speed', '$mileage', '$displacement', '$insurance_coverage', '$condition_status', $last_maintenance)";
        
        // --- UPDATED ERROR HANDLING HERE ---
        try {
            if (mysqli_query($conn, $insert_sql)) {
                // Create notification for admin
                $admin_message = "A new unit '$model_name' was added to the fleet.";
                create_notification($conn, null, 'admin', $admin_message, 'admin/dashboard.php');

                header("Location: manage_motorcycle.php?msg=added");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            // Check if error code is 1062 (Duplicate Entry)
            if ($e->getCode() == 1062) {
                $error_msg = "Error: The plate number '$plate_number' already exists in the system. Please check details.";
            } else {
                $error_msg = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// --- HANDLE RETURN UNIT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_unit'])) {
    $bike_id = (int)$_POST['bike_id'];
    $damage_notes = mysqli_real_escape_string($conn, $_POST['damage_notes']);
    $repair_cost = (float)$_POST['repair_cost'];
    $penalty_amount = isset($_POST['penalty_amount']) ? (float)$_POST['penalty_amount'] : 0.00;
    $deposit_returned = isset($_POST['deposit_returned']) ? 1 : 0;
    
    // Capture new return details
    $fuel_level = isset($_POST['fuel_level']) ? (int)$_POST['fuel_level'] : 100;
    $condition_status = isset($_POST['condition_status']) ? mysqli_real_escape_string($conn, $_POST['condition_status']) : 'Good';
    
    // Handle Return Images Upload
    $return_imgs_json = '[]';
    if (isset($_FILES['return_images'])) {
        $uploaded = [];
        $target_dir = "../assets/rental_proofs/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        foreach ($_FILES['return_images']['name'] as $key => $name) {
            if ($_FILES['return_images']['error'][$key] == 0) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $fname = uniqid('ret_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['return_images']['tmp_name'][$key], $target_dir . $fname)) {
                        $uploaded[] = "assets/rental_proofs/" . $fname;
                    }
                }
            }
        }
        $return_imgs_json = mysqli_real_escape_string($conn, json_encode($uploaded));
    }

    // Determine status: If there is a repair cost, set to Maintenance, otherwise Available.
    $new_status = ($repair_cost > 0) ? 'Maintenance' : 'Available';

    // 1. Update Bike Status
    mysqli_query($conn, "UPDATE bikes SET status='$new_status', fuel_level='$fuel_level', condition_status='$condition_status' WHERE id=$bike_id");

    // 2. Close the Rental Record (Find the active one)
    $active_rental_query = "SELECT id FROM rentals WHERE bike_id=$bike_id AND status='Active' ORDER BY id DESC LIMIT 1";
    $active_res = mysqli_query($conn, $active_rental_query);
    if ($active_row = mysqli_fetch_assoc($active_res)) {
        $rental_id = $active_row['id'];
        // Record rental_end_date (Exact Return) as NOW() when owner clicks 'Return'
        $update_sql = "UPDATE rentals SET status='Completed', rental_end_date=NOW(), return_fuel_level='$fuel_level', return_condition='$condition_status', return_images='$return_imgs_json', damage_notes='$damage_notes', repair_cost='$repair_cost', penalty_amount='$penalty_amount', deposit_returned='$deposit_returned' WHERE id=$rental_id";
        mysqli_query($conn, $update_sql);

        // Notify Owner
        $bike_model_res = mysqli_query($conn, "SELECT model_name FROM bikes WHERE id=$bike_id");
        $bike_model = mysqli_fetch_assoc($bike_model_res)['model_name'];
        $owner_message = "'$bike_model' has been returned.";
        if ($repair_cost > 0) {
            $owner_message .= " A damage report was filed.";
        }
        create_notification($conn, $_SESSION['userid'], 'owner', $owner_message, 'history.php');

        // Notify Customer if damaged
        if ($repair_cost > 0) {
            $customer_id = mysqli_fetch_assoc(mysqli_query($conn, "SELECT customer_id FROM rentals WHERE id=$rental_id"))['customer_id'];
            $customer_message = "A damage report was filed for your rental of '$bike_model'. Repair cost: ₱" . number_format($repair_cost, 2);
            create_notification($conn, $customer_id, 'customer', $customer_message, 'mybooks.php');
        }
    }

    header("Location: manage_motorcycle.php?msg=returned");
    exit();
}

// --- HANDLE STATUS UPDATE (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $bike_id = (int)$_POST['bike_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    mysqli_query($conn, "UPDATE bikes SET status='$new_status' WHERE id=$bike_id");
    header("Location: manage_motorcycle.php?msg=updated");
    exit();
}

// --- HANDLE WALKIN REGISTRATION (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_walkin'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['reg_fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['reg_email']);
    $phone = mysqli_real_escape_string($conn, $_POST['reg_phone']);
    $password = $_POST['reg_password'];
    
    // Check duplicates
    $check = mysqli_query($conn, "SELECT userid FROM customers WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $error_msg = "Email already registered.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // Insert with is_verified = 0
        $sql = "INSERT INTO customers (fullname, email, phone_number, hashedpassword, status, is_verified) VALUES ('$fullname', '$email', '$phone', '$hashed', 'active', 0)";
        if (mysqli_query($conn, $sql)) {
             // Notify Admin
             $admin_msg = "New walk-in customer registered: $fullname. Pending verification.";
             create_notification($conn, null, 'admin', $admin_msg, 'admin/dashboard.php?page=verify_customers');
             
             header("Location: manage_motorcycle.php?msg=cust_registered");
             exit();
        } else {
            $error_msg = "Registration failed: " . mysqli_error($conn);
        }
    }
}

// --- HANDLE MANUAL RENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_rent'])) {
    $bike_id = (int)$_POST['bike_id'];
    $customer_email = mysqli_real_escape_string($conn, $_POST['customer_email']);
    $amount = (float)$_POST['amount_collected'];
    $start_date = $_POST['start_date'];
    $end_date = str_replace('T', ' ', $_POST['end_date']); // Handle datetime-local format
    $owner_id = $_SESSION['userid'];
    $deposit_collected = isset($_POST['deposit_collected']) ? 1 : 0;
    
    // Check if customer exists and is verified
    $check_cust = mysqli_query($conn, "SELECT userid, is_verified FROM customers WHERE email = '$customer_email' LIMIT 1");
    $customer_id = 0;
    $proceed = true;

    if (mysqli_num_rows($check_cust) > 0) {
        $cust_data = mysqli_fetch_assoc($check_cust);
        if ($cust_data['is_verified'] == 0) {
            $error_msg = "Cannot process rental: Customer is registered but NOT verified by Admin.";
            $proceed = false;
        } else {
            $customer_id = $cust_data['userid'];
        }
    } else {
        // Customer not found
        $error_msg = "Customer not found. Please register them first.";
        $proceed = false;
    }

    if ($proceed) {
        // Fetch current bike state for "Before" snapshot
        $bike_res = mysqli_query($conn, "SELECT fuel_level, condition_status FROM bikes WHERE id=$bike_id");
        $bike_row = mysqli_fetch_assoc($bike_res);
        $p_fuel = $bike_row['fuel_level'] ?? 100;
        $p_cond = $bike_row['condition_status'] ?? 'Good';

        // Handle Pickup Images Upload
        $pickup_imgs_json = '[]';
        if (isset($_FILES['pickup_images'])) {
            $uploaded = [];
            $target_dir = "../assets/rental_proofs/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            foreach ($_FILES['pickup_images']['name'] as $key => $name) {
                if ($_FILES['pickup_images']['error'][$key] == 0) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $fname = uniqid('pick_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['pickup_images']['tmp_name'][$key], $target_dir . $fname)) {
                            $uploaded[] = "assets/rental_proofs/" . $fname;
                        }
                    }
                }
            }
            $pickup_imgs_json = json_encode($uploaded);
        }

        // 1. Update bike status to Rented
        mysqli_query($conn, "UPDATE bikes SET status='Rented' WHERE id=$bike_id");

        // 2. Record the transaction
        $stmt = $conn->prepare("INSERT INTO rentals (bike_id, customer_id, owner_id, amount_collected, rental_start_date, expected_return_date, exact_pickup_date, pickup_fuel_level, pickup_condition, pickup_images, deposit_collected, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("iiidssissi", $bike_id, $customer_id, $owner_id, $amount, $start_date, $end_date, $p_fuel, $p_cond, $pickup_imgs_json, $deposit_collected);
        $stmt->execute();

        header("Location: manage_motorcycle.php?msg=rented");
        exit();
    }
}

// --- HANDLE PICKUP UNIT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pickup_unit'])) {
    $bike_id = (int)$_POST['bike_id'];
    $rental_id = (int)$_POST['rental_id'];
    $deposit_collected = isset($_POST['deposit_collected']) ? 1 : 0;
    
    // Fetch current bike state for "Before" snapshot
    $bike_res = mysqli_query($conn, "SELECT fuel_level, condition_status FROM bikes WHERE id=$bike_id");
    $bike_row = mysqli_fetch_assoc($bike_res);
    $p_fuel = $bike_row['fuel_level'] ?? 100;
    $p_cond = $bike_row['condition_status'] ?? 'Good';

    // Handle Pickup Images Upload
    $pickup_imgs_json = '[]';
    if (isset($_FILES['pickup_images'])) {
        $uploaded = [];
        $target_dir = "../assets/rental_proofs/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        foreach ($_FILES['pickup_images']['name'] as $key => $name) {
            if ($_FILES['pickup_images']['error'][$key] == 0) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if(in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $fname = uniqid('pick_') . '.' . $ext;
                    if (move_uploaded_file($_FILES['pickup_images']['tmp_name'][$key], $target_dir . $fname)) {
                        $uploaded[] = "assets/rental_proofs/" . $fname;
                    }
                }
            }
        }
        $pickup_imgs_json = json_encode($uploaded);
    }

    // 1. Update Bike Status
    mysqli_query($conn, "UPDATE bikes SET status='Rented' WHERE id=$bike_id");
    // 2. Update Rental Status
    $stmt = $conn->prepare("UPDATE rentals SET status='Active', exact_pickup_date=NOW(), pickup_fuel_level=?, pickup_condition=?, pickup_images=?, deposit_collected=? WHERE id=?");
    $stmt->bind_param("issii", $p_fuel, $p_cond, $pickup_imgs_json, $deposit_collected, $rental_id);
    $stmt->execute();

    header("Location: manage_motorcycle.php?msg=picked_up");
    exit();
}

// --- HANDLE DELETE UNIT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_unit'])) {
    $bike_id = (int)$_POST['bike_id'];
    $owner_id = $_SESSION['userid'];

    // Verify ownership and status
    $check_query = "SELECT status FROM bikes WHERE id=$bike_id AND owner_id=$owner_id";
    $check_res = mysqli_query($conn, $check_query);
    
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $bike_data = mysqli_fetch_assoc($check_res);
        // Prevent deletion if currently rented or reserved
        if (in_array($bike_data['status'], ['Rented', 'Reserved'])) {
             $error_msg = "Cannot delete a motorcycle that is currently Rented or Reserved.";
        } else {
            try {
                $delete_sql = "DELETE FROM bikes WHERE id=$bike_id";
                if (mysqli_query($conn, $delete_sql)) {
                    header("Location: manage_motorcycle.php?msg=deleted");
                    exit();
                }
            } catch (mysqli_sql_exception $e) {
                $error_msg = "Cannot delete this unit because it has associated rental history. Consider setting status to 'Maintenance' instead.";
            }
        }
    } else {
        $error_msg = "Invalid unit or permission denied.";
    }
}

include 'header.php';
?>

<?php if (isset($error_msg)): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-2xl mb-6 border border-red-100 flex items-center gap-3 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-xl"></i> 
        <span class="font-medium"><?php echo htmlspecialchars($error_msg); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl mb-6 border border-emerald-100 flex items-center gap-3 shadow-sm animate-pulse">
        <i class="fa-solid fa-circle-check text-xl"></i> 
        <span class="font-medium">
        <?php 
            if ($_GET['msg'] == 'rented') echo "Rental processed successfully! Cash payment recorded.";
            if ($_GET['msg'] == 'returned') echo "Motorcycle marked as available for next customer.";
            if ($_GET['msg'] == 'added') echo "New motorcycle unit added to your fleet successfully.";
            if ($_GET['msg'] == 'updated') echo "Motorcycle status updated successfully.";
            if ($_GET['msg'] == 'picked_up') echo "Motorcycle picked up! Status updated to 'Rented'.";
            if ($_GET['msg'] == 'cust_registered') echo "Customer registered successfully! Please wait for Admin verification.";
            if ($_GET['msg'] == 'deleted') echo "Motorcycle unit deleted successfully.";
        ?>
        </span>
    </div>
<?php endif; ?>

<div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Fleet Management</h2>
            <p class="text-slate-400 text-sm font-medium mt-1">Control availability and process walk-in rentals.</p>
        </div>
        <?php if ($owner_status === 'active'): ?>
            <button onclick="document.getElementById('addUnitModal').classList.remove('hidden')" class="bg-primary text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all">
                <i class="fa-solid fa-plus mr-2"></i> Add New Unit
            </button>
        <?php else: ?>
            <button class="bg-slate-200 text-slate-400 px-6 py-3 rounded-xl font-bold text-sm cursor-not-allowed" title="Account verification required"><i class="fa-solid fa-lock mr-2"></i> Add New Unit</button>
        <?php endif; ?>
    </div>

    <table class="w-full">
        <thead class="bg-slate-50/50">
            <tr>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Vehicle Details</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Plate No.</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Next Maintenance</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Rate / Day</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Status</th>
                <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Quick Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php
            $owner_id = $_SESSION['userid'];
            $bikes_res = mysqli_query($conn, "SELECT b.*, 
                r.id as rental_id, r.rental_start_date, r.expected_return_date, 
                c.fullname as customer_name, c.phone_number 
                FROM bikes b 
                LEFT JOIN rentals r ON b.id = r.bike_id AND r.status IN ('Active', 'Overdue', 'Approved')
                LEFT JOIN customers c ON r.customer_id = c.userid 
                WHERE b.owner_id = $owner_id
                ORDER BY b.model_name ASC");
            
            if (mysqli_num_rows($bikes_res) > 0) {
                while($row = mysqli_fetch_assoc($bikes_res)) {
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
                            $penalty = ($late_days == 0 ? 1 : $late_days) * ($row['daily_rate'] * 2); // Double daily rate penalty
                            $row_style = "background-color: #fff1f2;"; // Light red highlight
                        }
                    }

                    $status_class = strtolower($row['status']);
                    $status_color = match($status_class) {
                        'available' => 'bg-emerald-100 text-emerald-600',
                        'reserved' => 'bg-purple-100 text-purple-600',
                        'rented' => 'bg-amber-100 text-amber-600',
                        'maintenance' => 'bg-red-100 text-red-600',
                        default => 'bg-slate-100 text-slate-500'
                    };

                    $rental_json = $is_rented ? json_encode([
                        'rental_id' => $row['rental_id'],
                        'customer_name' => $row['customer_name'],
                        'customer_phone' => $row['phone_number'],
                        'start_date' => date('M d, Y h:i A', strtotime($row['rental_start_date'])),
                        'due_date' => date('M d, Y h:i A', strtotime($row['expected_return_date'])),
                        'is_overdue' => $is_overdue,
                        'late_days' => $late_days,
                        'penalty' => $penalty
                    ], JSON_HEX_APOS | JSON_HEX_QUOT) : 'null';
                    ?>
                    <tr class="group hover:bg-slate-50/50 transition-colors" style="<?php echo $row_style; ?>">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 text-lg overflow-hidden">
                                    <?php if (!empty($row['image_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" alt="Bike" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <i class="fa-solid fa-motorcycle"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-700 text-base"><?php echo htmlspecialchars($row['model_name']); ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?php echo htmlspecialchars($row['type'] ?? 'Scooter'); ?> • <?php echo $row['fuel_level']; ?>% Fuel</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <span class="font-mono text-sm bg-slate-100 px-3 py-1 rounded-lg text-slate-600 font-bold"><?php echo htmlspecialchars($row['plate_number']); ?></span>
                            <?php if(!empty($row['registered_until'])): ?>
                                <div class="text-[10px] font-medium text-slate-400 mt-1" title="Registration Expiry">Reg: <?php echo date('M Y', strtotime($row['registered_until'])); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-6">
                            <div class="text-xs text-slate-500">
                                <div class="flex items-center gap-2 mb-1" title="Next Scheduled Maintenance">
                                    <i class="fa-solid fa-screwdriver-wrench text-[10px] text-slate-300"></i> 
                                    <?php echo $row['next_maintenance'] ? date('M d, Y', strtotime($row['next_maintenance'])) : 'N/A'; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 font-black text-slate-700">₱<?php echo number_format($row['daily_rate'], 0); ?></td>
                        <td class="px-8 py-6">
                            <?php if (in_array($row['status'], ['Rented', 'Reserved'])): ?>
                                <div class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest text-center <?php echo $status_color; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </div>
                            <?php else: ?>
                                <form action="manage_motorcycle.php" method="POST" class="inline-block">
                                    <input type="hidden" name="bike_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest border-none focus:ring-0 cursor-pointer <?php echo $status_color; ?>">
                                        <?php foreach(['Available', 'Maintenance'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $row['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php endif; ?>
                            <?php if($is_overdue): ?>
                                <div class="mt-1 text-[10px] font-bold text-red-500 uppercase tracking-wider text-center"><i class="fa-solid fa-triangle-exclamation"></i> Late</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <?php if ($row['status'] == 'Available'): ?>
                                <button onclick="openRentModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['model_name']); ?>', <?php echo $row['daily_rate']; ?>)"
                                   class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-primary-hover transition-colors shadow-md shadow-primary/20">
                                    <i class="fa-solid fa-hand-holding-dollar"></i> Rent Now
                                </button>
                                <form action="manage_motorcycle.php" method="POST" class="inline-block ml-1" onsubmit="return confirm('Are you sure you want to delete this unit? This cannot be undone.');">
                                    <input type="hidden" name="bike_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="delete_unit" value="1">
                                    <button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors" title="Delete Unit">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            <?php elseif ($row['status'] == 'Reserved'): ?>
                                <button onclick="openPickupModal(<?php echo $row['id']; ?>, <?php echo $row['rental_id']; ?>, '<?php echo htmlspecialchars($row['model_name']); ?>')" 
                                   class="inline-flex items-center gap-2 bg-purple-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-purple-700 transition-colors shadow-md shadow-purple-200">
                                    <i class="fa-solid fa-key"></i> Pick Up
                                </button>
                            <?php elseif ($row['status'] == 'Rented'): ?>
                                <button onclick='openViewModal(<?php echo $rental_json; ?>)' class="inline-flex items-center justify-center w-8 h-8 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition-colors mr-1" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button onclick="openReturnModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['model_name']); ?>', <?php echo $row['daily_rate']; ?>, '<?php echo $row['expected_return_date']; ?>')" 
                                   class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-arrow-rotate-left"></i> Return & Inspect
                                </button>
                            <?php else: ?>
                                <div class="flex items-center justify-end gap-2">
                                    <span class="text-slate-300 text-xs font-bold italic mr-2">Unavailable</span>
                                    <form action="manage_motorcycle.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this unit? This cannot be undone.');">
                                        <input type="hidden" name="bike_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="delete_unit" value="1">
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-red-50 text-red-600 rounded-xl hover:bg-red-100 transition-colors" title="Delete Unit">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="5" class="text-center py-20">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300 text-3xl">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <p class="text-slate-400 font-medium">Your fleet is currently empty.</p>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="mt-6 flex items-center gap-3 text-slate-400 text-xs font-medium bg-slate-100/50 p-4 rounded-xl border border-slate-100">
    <i class="fa-solid fa-circle-info text-primary"></i>
    <span>System Note: All transactions are logged as <strong>Cash Only</strong>. Ensure you have collected the payment before clicking "Pick up".</span>
</div>

<div id="addUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-[2rem] bg-white text-left shadow-2xl transition-all w-full max-w-6xl my-8">
                
                <div class="bg-white px-8 pb-8 pt-8">
                    <div class="mb-6 border-b border-slate-100 pb-5">
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight">Add New Motorcycle</h3>
                        <p class="text-sm text-slate-500 font-medium mt-1">Fill in the vehicle specifications to expand your fleet.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <!-- COLUMN 1: Basic Vehicle Information -->
                        <div class="space-y-5">
                            <div class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest border-b border-slate-100 pb-2">
                                <i class="fa-solid fa-motorcycle"></i> Basic Vehicle Info
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Model Name</label>
                                <input type="text" name="model_name" required placeholder="e.g. Yamaha NMAX 155" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Year Model</label>
                                    <input type="number" name="year_model" placeholder="2024" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Color</label>
                                    <input type="text" name="color" placeholder="Matte Black" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Plate No.</label>
                                    <input type="text" name="plate_number" required placeholder="ABC 123" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Type</label>
                                    <select name="type" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                        <option value="Scooter">Scooter</option>
                                        <option value="Underbone">Underbone</option>
                                        <option value="Big Bike">Big Bike</option>
                                        <option value="Sports">Sports</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Transmission</label>
                                    <select name="transmission" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                        <option value="Automatic">Automatic</option>
                                        <option value="Manual">Manual</option>
                                        <option value="Semi-Automatic">Semi-Auto</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Fuel Type</label>
                                    <select name="fuel_type" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                        <option value="Gasoline">Gasoline</option>
                                        <option value="Diesel">Diesel</option>
                                        <option value="Electric">Electric</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Main Vehicle Image</label>
                                <label class="flex items-center gap-4 w-full cursor-pointer group p-3 rounded-xl border border-dashed border-slate-300 hover:border-primary hover:bg-slate-50 transition-all">
                                    <div class="bg-primary/10 text-primary w-10 h-10 rounded-lg flex items-center justify-center transition-colors">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>
                                    <div class="flex-1">
                                        <span class="block text-xs font-bold text-slate-700 group-hover:text-primary">Upload Image</span>
                                        <span id="fileName" class="block text-[10px] text-slate-400">PNG, JPG up to 5MB</span>
                                    </div>
                                    <input type="file" name="bike_image" accept="image/*" class="hidden" onchange="document.getElementById('fileName').textContent = this.files[0] ? this.files[0].name : 'File selected'">
                                </label>
                            </div>
                        </div>

                        <!-- COLUMN 2: Technical Specs & Condition -->
                        <div class="space-y-5">
                            <div class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest border-b border-slate-100 pb-2">
                                <i class="fa-solid fa-screwdriver-wrench"></i> Technical Specs
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Displacement (cc)</label>
                                    <input type="number" name="displacement" placeholder="155" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Engine Cap.</label>
                                    <input type="text" name="engine_capacity" placeholder="155cc" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Max Speed</label>
                                    <input type="text" name="max_speed" placeholder="120 km/h" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Mileage</label>
                                    <input type="text" name="mileage" placeholder="45 km/L" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Current Condition</label>
                                <select name="condition_status" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                    <option value="Excellent">Excellent (Like New)</option>
                                    <option value="Good" selected>Good (Minor Wear)</option>
                                    <option value="Fair">Fair (Visible Wear)</option>
                                </select>
                            </div>

                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <label class="block text-[10px] font-black text-slate-400 mb-3 uppercase tracking-widest">Maintenance Log</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-500 mb-1 block">Last Maintenance</span>
                                        <input type="date" name="last_maintenance" class="w-full rounded-lg border-slate-200 text-xs font-bold text-slate-700 p-2 focus:border-primary focus:ring-primary">
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-500 mb-1 block">Next Due</span>
                                        <input type="date" name="next_maintenance" required class="w-full rounded-lg border-slate-200 text-xs font-bold text-slate-700 p-2 focus:border-primary focus:ring-primary">
                                    </div>
                                </div>
                            </div>

                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <label class="flex items-center gap-3 mb-3 cursor-pointer">
                                    <input type="checkbox" id="is_registered" checked onchange="toggleRegistration()" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Unit is Registered</span>
                                </label>
                                <div id="reg_date_container">
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1 uppercase tracking-wide">Registration Expiry</label>
                                    <input type="date" name="registered_until" id="registered_until" required class="w-full rounded-lg border-slate-200 text-xs font-bold text-slate-700 p-2 focus:border-primary focus:ring-primary bg-white">
                                </div>
                            </div>
                            <script>
                                function toggleRegistration() {
                                    const isReg = document.getElementById('is_registered').checked;
                                    const container = document.getElementById('reg_date_container');
                                    const input = document.getElementById('registered_until');
                                    if (isReg) {
                                        container.style.display = 'block';
                                        input.required = true;
                                    } else {
                                        container.style.display = 'none';
                                        input.required = false;
                                        input.value = '';
                                    }
                                }
                            </script>
                        </div>

                        <!-- COLUMN 3: Rental Terms & Financials -->
                        <div class="space-y-5">
                            <div class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest border-b border-slate-100 pb-2">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Rental Terms
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Daily Rate (₱)</label>
                                    <input type="number" name="daily_rate" min="0" required placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Security Dep. (₱)</label>
                                    <input type="number" name="security_deposit" min="0" placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Deposit Rules</label>
                                <textarea name="security_deposit_rules" rows="2" placeholder="e.g. Refundable upon return" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary resize-none shadow-sm"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Overtime Fee (₱/hr)</label>
                                    <input type="number" name="overtime_fee" min="0" placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Late Penalty (₱)</label>
                                    <input type="number" name="late_penalty" min="0" placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Fuel Policy</label>
                                    <select name="fuel_policy" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                        <option value="Full-to-Full">Full-to-Full</option>
                                        <option value="Same-to-Same">Same Level</option>
                                        <option value="Empty-to-Empty">Empty</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Current Fuel (%)</label>
                                    <input type="number" name="fuel_level" value="100" max="100" min="0" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Pickup & Return Location</label>
                                <input type="text" name="pickup_location" placeholder="e.g. Shop Address or City Center" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Inclusions</label>
                                <input type="text" name="inclusions" placeholder="e.g. 2 Helmets, Raincoat" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary shadow-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Insurance Coverage</label>
                                <textarea name="insurance_coverage" rows="2" placeholder="e.g. Third Party Liability Only" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3 focus:border-primary focus:ring-primary resize-none shadow-sm"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-8 py-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('addUnitModal').classList.add('hidden')" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-white text-sm font-bold text-slate-600 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50 transition-all">Cancel</button>
                    <button type="submit" name="add_unit" class="w-full sm:w-auto px-8 py-3 rounded-xl bg-primary text-sm font-bold text-white shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all">
                        <i class="fa-solid fa-check mr-2"></i> Save Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="returnUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all w-full max-w-2xl">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6 border-b border-slate-100 pb-4">
                        <h3 class="text-xl font-black text-slate-800">Return Inspection</h3>
                        <p class="text-sm text-slate-500">Processing return for: <span id="returnBikeName" class="font-bold text-primary"></span></p>
                    </div>
                    
                    <input type="hidden" name="bike_id" id="returnBikeId">
                    <input type="hidden" name="return_unit" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Return Checklist</p>
                                <label class="flex items-center gap-3 cursor-pointer mb-2 group">
                                    <input type="checkbox" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Helmet(s) Returned</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer mb-2 group">
                                    <input type="checkbox" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Keys Returned</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Accessories (Raincoat/Tools)</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="deposit_returned" value="1" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Security Deposit Returned</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Fuel Level (%)</label>
                                    <input type="number" name="fuel_level" value="100" max="100" min="0" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Condition</label>
                                    <select name="condition_status" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                                        <option value="Excellent">Excellent</option>
                                        <option value="Good" selected>Good</option>
                                        <option value="Fair">Fair</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Return Photos</label>
                                <input type="file" name="return_images[]" multiple accept="image/*" class="w-full rounded-xl border border-slate-200 text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                            </div>

                            <div id="penaltyField" class="hidden bg-red-50 p-3 rounded-xl border border-red-100">
                                <label class="block text-xs font-bold uppercase text-red-500 mb-1">Late Penalty (Double Rate)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-red-400 font-bold">₱</span>
                                    <input type="number" name="penalty_amount" id="penaltyAmount" value="0" readonly class="w-full pl-6 bg-transparent border-none text-red-600 font-black text-lg focus:ring-0 p-0">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Damage Report / Notes</label>
                                <textarea name="damage_notes" rows="5" placeholder="Describe any scratches, dents, or issues..." class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Repair Cost / Penalty (₱)</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold">₱</span>
                                    <input type="number" name="repair_cost" value="0" min="0" step="0.01" class="w-full pl-8 rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Leave as 0 if unit is in good condition.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:px-6 border-t border-slate-100">
                    <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-lg shadow-primary/20 hover:bg-primary-hover sm:ml-3 sm:w-auto transition-all">
                        <i class="fa-solid fa-check-double mr-2"></i> Confirm Return
                    </button>
                    <button type="button" onclick="document.getElementById('returnUnitModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-600 shadow-sm ring-1 ring-inset ring-slate-200 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="rentUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800">Manual Rental</h3>
                        <p class="text-sm text-slate-500">Process a walk-in rental for: <span id="rentBikeName" class="font-bold text-primary"></span></p>
                    </div>
                    
                    <input type="hidden" name="bike_id" id="rentBikeId">
                    <input type="hidden" name="manual_rent" value="1">
                    <input type="hidden" id="hiddenDailyRate">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Customer Email</label>
                            <input type="email" name="customer_email" required placeholder="customer@example.com" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div class="text-right">
                            <button type="button" onclick="openRegisterModal()" class="text-xs font-bold text-primary hover:underline">Customer not registered? Click here</button>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Start Date</label>
                                <input type="datetime-local" name="start_date" id="rentStartDate" required onchange="calculateTotal()" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Return Date</label>
                                <input type="datetime-local" name="end_date" id="rentEndDate" required onchange="calculateTotal()" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Amount Collected (₱)</label>
                            <input type="number" name="amount_collected" id="rentAmount" required min="0" step="0.01" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer group bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <input type="checkbox" name="deposit_collected" value="1" class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                            <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Security Deposit Collected</span>
                        </label>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Pickup Photos</label>
                            <input type="file" name="pickup_images[]" multiple accept="image/*" class="w-full rounded-xl border border-slate-200 text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">Confirm Rental</button>
                    <button type="button" onclick="document.getElementById('rentUnitModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="registerWalkinModal" class="fixed inset-0 z-[110] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800">Register Walk-in Customer</h3>
                        <p class="text-sm text-slate-500">Create a new account for verification.</p>
                    </div>
                    
                    <input type="hidden" name="register_walkin" value="1">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Full Name</label>
                            <input type="text" name="reg_fullname" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Email Address</label>
                            <input type="email" name="reg_email" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Phone Number</label>
                            <input type="text" name="reg_phone" required maxlength="11" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Password</label>
                            <input type="password" name="reg_password" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">Register</button>
                    <button type="button" onclick="document.getElementById('registerWalkinModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="pickupUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800">Pickup Inspection</h3>
                        <p class="text-sm text-slate-500">Confirming pickup for: <span id="pickupBikeName" class="font-bold text-primary"></span></p>
                    </div>
                    
                    <input type="hidden" name="bike_id" id="pickupBikeId">
                    <input type="hidden" name="rental_id" id="pickupRentalId">
                    <input type="hidden" name="pickup_unit" value="1">
                    
                    <div class="space-y-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Pre-Rental Checklist</p>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" required class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                            <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Verify Customer License & ID</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" name="deposit_collected" value="1" required class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                            <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Collect Security Deposit</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" required class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                            <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Inspect Vehicle Condition</span>
                        </label>
                        
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" required class="w-5 h-5 rounded text-primary focus:ring-primary border-gray-300">
                            <span class="text-sm font-bold text-slate-600 group-hover:text-primary transition-colors">Handover Keys & Helmet</span>
                        </label>
                    </div>
                    
                    <div class="mt-4 text-left">
                        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Pickup Photos</label>
                        <input type="file" name="pickup_images[]" multiple accept="image/*" class="w-full rounded-xl border border-slate-200 text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">
                        <i class="fa-solid fa-check mr-2"></i> Confirm Pickup
                    </button>
                    <button type="button" onclick="document.getElementById('pickupUnitModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="viewRentalModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:w-full sm:max-w-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-slate-800">Rental Details</h3>
                    <button onclick="document.getElementById('viewRentalModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                
                <div class="space-y-4 text-sm">
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500 font-medium">Booking Ref</span>
                        <span class="font-bold text-slate-700" id="viewRentalRef"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500 font-medium">Customer</span>
                        <span class="font-bold text-slate-700" id="viewCustomer"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500 font-medium">Phone</span>
                        <span class="font-bold text-slate-700" id="viewPhone"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500 font-medium">Picked Up</span>
                        <span class="font-bold text-slate-700" id="viewStartDate"></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-50 pb-2">
                        <span class="text-slate-500 font-medium">Return Due</span>
                        <span class="font-bold text-slate-700" id="viewDueDate"></span>
                    </div>
                    
                    <div id="viewLateContainer" class="hidden bg-red-50 p-4 rounded-xl border border-red-100 mt-4">
                        <div class="flex items-center gap-2 text-red-600 font-bold mb-2">
                            <i class="fa-solid fa-triangle-exclamation"></i> Overdue by <span id="viewLateDays"></span> Day(s)
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-red-800 text-xs font-bold uppercase tracking-wide">Total Penalty (+10%)</span>
                            <span class="text-xl font-black text-red-600" id="viewPenalty"></span>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button onclick="document.getElementById('viewRentalModal').classList.add('hidden')" class="w-full py-3 rounded-xl bg-slate-100 text-slate-600 font-bold hover:bg-slate-200 transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openReturnModal(id, name, dailyRate, expectedReturnDate) {
        document.getElementById('returnBikeId').value = id;
        document.getElementById('returnBikeName').textContent = name;
        
        // Calculate Penalty
        const now = new Date();
        const expected = new Date(expectedReturnDate);
        const penaltyField = document.getElementById('penaltyField');
        const penaltyInput = document.getElementById('penaltyAmount');
        
        if (now > expected) {
            const diffMs = now - expected;
            // Calculate days late (round up to ensure at least 1 day penalty if late)
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            // Penalty: Days Late * (Daily Rate * 2)
            const penalty = diffDays * (dailyRate * 2);
            
            penaltyInput.value = penalty.toFixed(2);
            penaltyField.classList.remove('hidden');
        } else {
            penaltyInput.value = 0;
            penaltyField.classList.add('hidden');
        }

        document.getElementById('returnUnitModal').classList.remove('hidden');
    }

    function openRentModal(id, name, rate) {
        document.getElementById('rentBikeId').value = id;
        document.getElementById('rentBikeName').textContent = name;
        document.getElementById('hiddenDailyRate').value = rate;
        document.getElementById('rentAmount').value = rate;
        // Reset dates
        document.getElementById('rentStartDate').value = '';
        document.getElementById('rentEndDate').value = '';
        document.getElementById('rentUnitModal').classList.remove('hidden');
    }

    function openPickupModal(bikeId, rentalId, bikeName) {
        document.getElementById('pickupBikeId').value = bikeId;
        document.getElementById('pickupRentalId').value = rentalId;
        document.getElementById('pickupBikeName').textContent = bikeName;
        document.getElementById('pickupUnitModal').classList.remove('hidden');
    }

    function calculateTotal() {
        const startVal = document.getElementById('rentStartDate').value;
        const endVal = document.getElementById('rentEndDate').value;
        const rate = parseFloat(document.getElementById('hiddenDailyRate').value);
        
        if(startVal && endVal && rate) {
            const start = new Date(startVal);
            const end = new Date(endVal);
            const diffTime = end - start;
            
            if (diffTime > 0) {
                // Calculate days, rounding up (minimum 1 day)
                let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays < 1) diffDays = 1;
                
                const total = diffDays * rate;
                document.getElementById('rentAmount').value = total.toFixed(2);
            }
        }
    }

    function openRegisterModal() {
        document.getElementById('rentUnitModal').classList.add('hidden');
        document.getElementById('registerWalkinModal').classList.remove('hidden');
    }

    function openViewModal(data) {
        document.getElementById('viewRentalRef').textContent = '#' + data.rental_id;
        document.getElementById('viewCustomer').textContent = data.customer_name;
        document.getElementById('viewPhone').textContent = data.customer_phone || 'N/A';
        document.getElementById('viewStartDate').textContent = data.start_date;
        document.getElementById('viewDueDate').textContent = data.due_date;
        
        const lateContainer = document.getElementById('viewLateContainer');
        if (data.is_overdue) {
            lateContainer.classList.remove('hidden');
            document.getElementById('viewLateDays').textContent = data.late_days;
            document.getElementById('viewPenalty').textContent = '₱' + parseFloat(data.penalty).toFixed(2) + ' (2x Rate)';
        } else {
            lateContainer.classList.add('hidden');
        }
        
        document.getElementById('viewRentalModal').classList.remove('hidden');
    }
</script>

<?php include 'footer.php'; ?>