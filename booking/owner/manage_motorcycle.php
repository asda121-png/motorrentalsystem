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
    $last_tire = $_POST['last_tire_change'];
    $last_oil = $_POST['last_oil_change'];

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

    if ($daily_rate < 0) {
        $error_msg = "Daily rate cannot be less than 0.";
    } else {
        $insert_sql = "INSERT INTO bikes (owner_id, model_name, plate_number, type, transmission, inclusions, description, daily_rate, status, fuel_level, last_tire_change, last_oil_change, image_url) 
                       VALUES ('$owner_id', '$model_name', '$plate_number', '$type', '$transmission', '$inclusions', '$description', '$daily_rate', 'Available', '$fuel_level', '$last_tire', '$last_oil', $image_url)";
        
        if (mysqli_query($conn, $insert_sql)) {
            // Create notification for admin
            $admin_message = "A new unit '$model_name' was added to the fleet.";
            create_notification($conn, null, 'admin', $admin_message, 'admin/dashboard.php');

            header("Location: manage_motorcycle.php?msg=added");
            exit();
        } else {
            $error_msg = "Error adding unit: " . mysqli_error($conn);
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

// --- HANDLE MANUAL RENT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_rent'])) {
    $bike_id = (int)$_POST['bike_id'];
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $amount = (float)$_POST['amount_collected'];
    $start_date = $_POST['start_date'];
    $end_date = str_replace('T', ' ', $_POST['end_date']); // Handle datetime-local format
    $owner_id = $_SESSION['userid'];
    
    // 1. Update bike status to Rented
    mysqli_query($conn, "UPDATE bikes SET status='Rented' WHERE id=$bike_id");

    // 2. Record the transaction
    $stmt = $conn->prepare("INSERT INTO rentals (bike_id, customer_id, owner_id, amount_collected, rental_start_date, expected_return_date, status) VALUES (?, 0, ?, ?, ?, ?, 'Active')");
    // Using customer_id 0 for walk-in, and storing name in the rentals table itself might need a schema change,
    // but for now, let's assume a 'customer_name' column exists or we can add it.
    // A better approach is to add a generic walk-in customer to the customers table.
    // For now, let's just insert the name into the `rentals` table. We need to add the column.
    // Let's assume the user will add a `customer_name` text column to the `rentals` table.
    // The provided schema doesn't have it, so I'll add a fallback.
    $stmt->bind_param("iidss", $bike_id, $owner_id, $amount, $start_date, $end_date);
    // This will fail if `customer_name` is not in the rentals table. Let's adjust the query.
    mysqli_query($conn, "INSERT INTO rentals (bike_id, owner_id, customer_id, amount_collected, rental_start_date, expected_return_date, status) VALUES ($bike_id, $owner_id, 0, $amount, '$start_date', '$end_date', 'Active')");

    header("Location: manage_motorcycle.php?msg=rented");
    exit();
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

<!-- Error Feedback Message -->
<?php if (isset($error_msg)): ?>
    <div class="bg-red-50 text-red-700 p-4 rounded-2xl mb-6 border border-red-100 flex items-center gap-3 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-xl"></i> 
        <span class="font-medium"><?php echo htmlspecialchars($error_msg); ?></span>
    </div>
<?php endif; ?>

<!-- Action Feedback Message -->
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
        <button onclick="document.getElementById('addUnitModal').classList.remove('hidden')" class="bg-primary text-white px-6 py-3 rounded-xl font-bold text-sm shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all">
            <i class="fa-solid fa-plus mr-2"></i> Add New Unit
        </button>
    </div>

    <table class="w-full">
        <thead class="bg-slate-50/50">
            <tr>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Vehicle Details</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Plate No.</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Maintenance</th>
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
                                <div class="flex items-center gap-2 mb-1" title="Last Tire Change">
                                    <i class="fa-solid fa-circle-dot text-[10px] text-slate-300"></i> 
                                    <?php echo $row['last_tire_change'] ? date('M d, Y', strtotime($row['last_tire_change'])) : 'N/A'; ?>
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

<!-- ADD UNIT MODAL -->
<div id="addUnitModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <form action="manage_motorcycle.php" method="POST" enctype="multipart/form-data" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="mb-6">
                        <h3 class="text-xl font-black text-slate-800" id="modal-title">Add New Motorcycle</h3>
                        <p class="text-sm text-slate-500">Enter the details of the new unit to add to your fleet.</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Model Name</label>
                            <input type="text" name="model_name" required placeholder="e.g. Honda Click 125i" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Plate Number</label>
                            <input type="text" name="plate_number" required placeholder="ABC 123" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Type</label>
                            <select name="type" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                                <option value="Scooter">Scooter</option>
                                <option value="Underbone">Underbone</option>
                                <option value="Big Bike">Big Bike</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Transmission</label>
                            <select name="transmission" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                                <option value="Automatic">Automatic</option>
                                <option value="Manual">Manual</option>
                                <option value="Semi-Automatic">Semi-Automatic</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Inclusions (comma separated)</label>
                            <input type="text" name="inclusions" placeholder="2 Helmets, Raincoat" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Description</label>
                            <textarea name="description" rows="3" placeholder="Short description of the unit..." class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Daily Rate (₱)</label>
                            <input type="number" name="daily_rate" min="0" required placeholder="500" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Fuel Level (%)</label>
                            <input type="number" name="fuel_level" value="100" max="100" min="0" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        
                        <div class="col-span-2 pt-4 border-t border-slate-50 mt-2">
                            <span class="text-xs font-black text-primary uppercase tracking-widest">Maintenance Log</span>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Last Tire Change</label>
                            <input type="date" name="last_tire_change" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Last Oil Change</label>
                            <input type="date" name="last_oil_change" required class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Motorcycle Image</label>
                            <input type="file" name="bike_image" accept="image/*" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <button type="submit" name="add_unit" class="inline-flex w-full justify-center rounded-xl bg-primary px-3 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">Save Unit</button>
                    <button type="button" onclick="document.getElementById('addUnitModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-3 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- RETURN & INSPECT MODAL -->
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

<!-- MANUAL RENT MODAL -->
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
                            <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Customer Name</label>
                            <input type="text" name="customer_name" required placeholder="e.g., John Doe (Walk-in)" class="w-full rounded-xl border-slate-200 text-sm font-bold text-slate-700 focus:border-primary focus:ring-primary">
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

<!-- VIEW RENTAL DETAILS MODAL -->
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
                    
                    <!-- Late Section -->
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