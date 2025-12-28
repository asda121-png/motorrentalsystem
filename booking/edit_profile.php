<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$userid = $_SESSION['userid'];
$msg = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Base updates
    $updates = ["fullname='$fullname'", "phone_number='$phone'"];
    
    // Password Update Logic
    if (!empty($new_password)) {
        if ($new_password === $confirm_password && strlen($new_password) >= 8) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $updates[] = "hashedpassword='$hashed'";
        }
    }
    
    // Handle File Uploads
    $target_dir = "assets/customer_uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_fields = ['profile_image', 'drivers_license_image', 'valid_id_image'];
    
    foreach ($file_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES[$field]["name"], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed)) {
                $new_filename = uniqid("cust_{$userid}_") . "." . $file_ext;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES[$field]["tmp_name"], $target_file)) {
                    $db_path = mysqli_real_escape_string($conn, $target_file);
                    $updates[] = "$field='$db_path'";
                } else {
                    $msg = "Failed to save file. Please check directory permissions.";
                }
            } else {
                $msg = "Invalid file type. Only JPG, PNG, and WEBP are allowed.";
            }
        } elseif (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            $msg = "File upload error: Code " . $_FILES[$field]['error'];
        }
    }
    
    // Execute Update
    $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE userid=$userid";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['fullname'] = $fullname; // Update session name
        header("Location: profile.php");
        exit();
    } else {
        $msg = "Error updating profile: " . mysqli_error($conn);
    }
}

// Fetch Current Data
$query = "SELECT * FROM customers WHERE userid = $userid";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #005461;
            --secondary: #018790;
            --bg: #F4F4F4;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
    </style>
</head>
<body class="text-gray-900">

    <div class="max-w-3xl mx-auto px-4 py-12">
        <div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-black text-primary tracking-tight">Edit Profile</h1>
                <a href="profile.php" class="text-sm font-bold text-gray-400 hover:text-primary transition">Cancel</a>
            </div>

            <?php if ($msg): ?>
                <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl text-sm font-bold"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form action="edit_profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                        <input type="text" name="fullname" required value="<?php echo htmlspecialchars($user['fullname']); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Contact Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" placeholder="09XX XXX XXXX" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Profile Picture</label>
                    <input type="file" name="profile_image" accept="image/*" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                    <?php if(!empty($user['profile_image'])): ?>
                        <p class="text-xs text-gray-400 ml-2 mt-1">Current: Uploaded</p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-50">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Driver's License</label>
                        <input type="file" name="drivers_license_image" accept="image/*" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                        <?php if(!empty($user['drivers_license_image'])): ?>
                            <p class="text-xs text-gray-400 ml-2 mt-1">Current: Uploaded</p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Valid ID</label>
                        <input type="file" name="valid_id_image" accept="image/*" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                        <?php if(!empty($user['valid_id_image'])): ?>
                            <p class="text-xs text-gray-400 ml-2 mt-1">Current: Uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-50 mt-4">
                    <h3 class="text-sm font-bold text-gray-800 mb-4">Change Password <span class="text-gray-400 font-normal text-xs">(Leave blank to keep current)</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">New Password</label>
                            <input type="password" name="new_password" placeholder="••••••••" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Confirm Password</label>
                            <input type="password" name="confirm_password" placeholder="••••••••" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
                        </div>
                    </div>
                </div>

                <div class="pt-6 flex justify-end gap-4">
                    <a href="profile.php" class="px-8 py-4 rounded-2xl bg-white border border-gray-200 text-gray-500 font-bold hover:bg-gray-50 transition-all">Cancel</a>
                    <button type="submit" class="px-8 py-4 rounded-2xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:bg-secondary transition-all">Save Changes</button>
                </div>

            </form>
        </div>
    </div>
</body>
</html>