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
        $insert_sql = "INSERT INTO bikes (owner_id, model_name, plate_number, type, transmission, inclusions, description, daily_rate, status, fuel_level, next_maintenance, image_url) 
                       VALUES ('$owner_id', '$model_name', '$plate_number', '$type', '$transmission', '$inclusions', '$description', '$daily_rate', 'Available', '$fuel_level', '$next_maintenance', $image_url)";
        
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
    
    // Determine status: If there is a repair cost, set to Maintenance, otherwise Available.
    $new_status = ($repair_cost > 0) ? 'Maintenance' : 'Available';

    // 1. Update Bike Status
    mysqli_query($conn, "UPDATE bikes SET status='$new_status' WHERE id=$bike_id");

    // 2. Close the Rental Record (Find the active one)
    $active_rental_query = "SELECT id FROM rentals WHERE bike_id=$bike_id AND status='Active' ORDER BY id DESC LIMIT 1";
    $active_res = mysqli_query($conn, $active_rental_query);
    if ($active_row = mysqli_fetch_assoc($active_res)) {
        $rental_id = $active_row['id'];
        $update_sql = "UPDATE rentals SET status='Completed', rental_end_date=NOW(), damage_notes='$damage_notes', repair_cost='$repair_cost' WHERE id=$rental_id";
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
        // 1. Update bike status to Rented
        mysqli_query($conn, "UPDATE bikes SET status='Rented' WHERE id=$bike_id");

        // 2. Record the transaction
        $stmt = $conn->prepare("INSERT INTO rentals (bike_id, customer_id, owner_id, amount_collected, rental_start_date, expected_return_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->bind_param("iiidss", $bike_id, $customer_id, $owner_id, $amount, $start_date, $end_date);
        $stmt->execute();

        header("Location: manage_motorcycle.php?msg=rented");
        exit();
    }
}

// --- HANDLE PICKUP UNIT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pickup_unit'])) {
    $bike_id = (int)$_POST['bike_id'];
    $rental_id = (int)$_POST['rental_id'];
    
    // 1. Update Bike Status
    mysqli_query($conn, "UPDATE bikes SET status='Rented' WHERE id=$bike_id");
    // 2. Update Rental Status
    mysqli_query($conn, "UPDATE rentals SET status='Active' WHERE id=$rental_id");

    header("Location: manage_motorcycle.php?msg=picked_up");
    exit();
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
                            $penalty = ($late_days * $row['daily_rate']) * 1.10; // Late fee + 10%
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
                        <td class="px-8 py-6"><span class="font-mono text-sm bg-slate-100 px-3 py-1 rounded-lg text-slate-600 font-bold"><?php echo htmlspecialchars($row['plate_number']); ?></span></td>
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
                            <form action="manage_motorcycle.php" method="POST" class="inline-block">
                                <input type="hidden" name="bike_id" value="<?php echo $row['id']; ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" onchange="this.form.submit()" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest border-none focus:ring-0 cursor-pointer <?php echo $status_color; ?>">
                                    <?php foreach(['Available', 'Reserved', 'Rented', 'Maintenance'] as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $row['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if($is_overdue): ?>
                                    <div class="mt-1 text-[10px] font-bold text-red-500 uppercase tracking-wider text-center"><i class="fa-solid fa-triangle-exclamation"></i> Late</div>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <?php if ($row['status'] == 'Available'): ?>
                                <button onclick="openRentModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['model_name']); ?>', <?php echo $row['daily_rate']; ?>)"
                                   class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-primary-hover transition-colors shadow-md shadow-primary/20">
                                    <i class="fa-solid fa-hand-holding-dollar"></i> Rent Now
                                </button>
                            <?php elseif ($row['status'] == 'Reserved'): ?>
                                <form action="manage_motorcycle.php" method="POST" class="inline-block">
                                    <input type="hidden" name="bike_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="rental_id" value="<?php echo $row['rental_id']; ?>">
                                    <input type="hidden" name="pickup_unit" value="1">
                                    <button type="submit" class="inline-flex items-center gap-2 bg-purple-600 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-purple-700 transition-colors shadow-md shadow-purple-200">
                                        <i class="fa-solid fa-key"></i> Pick Up
                                    </button>
                                </form>
                            <?php elseif ($row['status'] == 'Rented'): ?>
                                <button onclick='openViewModal(<?php echo $rental_json; ?>)' class="inline-flex items-center justify-center w-8 h-8 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition-colors mr-1" title="View Details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button onclick="openReturnModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['model_name']); ?>')" 
                                   class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl text-xs font-bold hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-arrow-rotate-left"></i> Return & Inspect
                                </button>
                            <?php else: ?>
                                <span class="text-slate-300 text-xs font-bold italic">Unavailable</span>
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
    <span>System Note: All transactions are logged as <strong>Cash Only</strong>. Ensure you have collected the payment before clicking "Rent Now".</span>
</div>

<div id="addUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-[2rem] bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
                
                <div class="bg-white px-8 pb-8 pt-8">
                    <div class="mb-8 border-b border-slate-100 pb-5">
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight">Add New Motorcycle</h3>
                        <p class="text-sm text-slate-500 font-medium mt-1">Fill in the vehicle specifications to expand your fleet.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                        
                        <div class="space-y-6">
                            <div class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">
                                <i class="fa-solid fa-motorcycle"></i> Vehicle Identity
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Model Name</label>
                                <input type="text" name="model_name" required placeholder="e.g. Yamaha NMAX 155" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary shadow-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Plate No.</label>
                                    <input type="text" name="plate_number" required placeholder="ABC 123" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Type</label>
                                    <div class="relative">
                                        <select name="type" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary appearance-none shadow-sm">
                                            <option value="Scooter">Scooter</option>
                                            <option value="Underbone">Underbone</option>
                                            <option value="Big Bike">Big Bike</option>
                                        </select>
                                        <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Transmission</label>
                                <div class="relative">
                                    <select name="transmission" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary appearance-none shadow-sm">
                                        <option value="Automatic">Automatic</option>
                                        <option value="Manual">Manual</option>
                                        <option value="Semi-Automatic">Semi-Automatic</option>
                                    </select>
                                    <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Description</label>
                                <textarea name="description" rows="3" placeholder="Color, features, condition..." class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary resize-none shadow-sm"></textarea>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="flex items-center gap-2 text-xs font-black text-primary uppercase tracking-widest border-b border-slate-100 pb-2 mb-4">
                                <i class="fa-solid fa-file-contract"></i> Rental & Status
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Daily Rate (₱)</label>
                                    <input type="number" name="daily_rate" min="0" required placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Fuel (%)</label>
                                    <input type="number" name="fuel_level" value="100" max="100" min="0" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Inclusions</label>
                                <input type="text" name="inclusions" placeholder="e.g. 2 Helmets, Raincoat" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 p-3.5 focus:border-primary focus:ring-primary shadow-sm">
                            </div>

                            <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                <label class="block text-[10px] font-black text-slate-400 mb-3 uppercase tracking-widest">Maintenance Check</label>
                                <div>
                                    <span class="text-xs font-bold text-slate-500 mb-1 block">Next Scheduled Maintenance</span>
                                    <input type="date" name="next_maintenance" required class="w-full rounded-lg border-slate-200 text-xs font-bold text-slate-700 p-2.5 focus:border-primary focus:ring-primary">
                                </div>
                            </div>
                            
                            <div>
                                 <label class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">Motorcycle Image</label>
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
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800">Return Inspection</h3>
                        <p class="text-sm text-slate-500">Processing return for: <span id="returnBikeName" class="font-bold text-primary"></span></p>
                    </div>
                    
                    <input type="hidden" name="bike_id" id="returnBikeId">
                    <input type="hidden" name="return_unit" value="1">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Damage Report / Notes</label>
                            <textarea name="damage_notes" rows="3" placeholder="Describe any scratches, dents, or issues..." class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Repair Cost / Penalty (₱)</label>
                            <input type="number" name="repair_cost" value="0" min="0" step="0.01" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                            <p class="text-[10px] text-slate-400 mt-1">Leave as 0 if unit is in good condition.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">Confirm Return</button>
                    <button type="button" onclick="document.getElementById('returnUnitModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="rentUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800">Manual Rental</h3>
                        <p class="text-sm text-slate-500">Process a walk-in rental for: <span id="rentBikeName" class="font-bold text-primary"></span></p>
                    </div>
                    
                    <input type="hidden" name="bike_id" id="rentBikeId">
                    <input type="hidden" name="manual_rent" value="1">
                    
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
                                <input type="datetime-local" name="start_date" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Return Date</label>
                                <input type="datetime-local" name="end_date" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Amount Collected (₱)</label>
                            <input type="number" name="amount_collected" id="rentAmount" required min="0" step="0.01" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
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
    function openReturnModal(id, name) {
        document.getElementById('returnBikeId').value = id;
        document.getElementById('returnBikeName').textContent = name;
        document.getElementById('returnUnitModal').classList.remove('hidden');
    }

    function openRentModal(id, name, rate) {
        document.getElementById('rentBikeId').value = id;
        document.getElementById('rentBikeName').textContent = name;
        document.getElementById('rentAmount').value = rate;
        document.getElementById('rentUnitModal').classList.remove('hidden');
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
            document.getElementById('viewPenalty').textContent = '₱' + parseFloat(data.penalty).toFixed(2);
        } else {
            lateContainer.classList.add('hidden');
        }
        
        document.getElementById('viewRentalModal').classList.remove('hidden');
    }
</script>

<?php include 'footer.php'; ?>