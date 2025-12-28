<?php
session_start();
require_once 'db.php';

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_id = $_SESSION['userid'];
$msg = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $shopname = mysqli_real_escape_string($conn, $_POST['shopname']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $password_sql = "";
    if (!empty($new_password)) {
        if ($new_password === $confirm_password && strlen($new_password) >= 8) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $password_sql = ", hashedpassword='$hashed'";
        }
    }
    
    $sql = "UPDATE owners SET fullname='$fullname', shopname='$shopname' $password_sql WHERE ownerid=$owner_id";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: profile.php?msg=success");
        exit();
    } else {
        $msg = "Error updating profile: " . mysqli_error($conn);
    }
}

// Fetch Current Data
$query = "SELECT * FROM owners WHERE ownerid = $owner_id";
$result = mysqli_query($conn, $query);
$owner = mysqli_fetch_assoc($result);

// Set page identification for the header
$page_title = "Edit Profile";
$active_nav = "profile";

include 'header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-primary tracking-tight">Edit Owner Profile</h1>
            <a href="profile.php" class="text-sm font-bold text-gray-400 hover:text-primary transition">Cancel</a>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-xl text-sm font-bold"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form action="edit_profile.php" method="POST" class="space-y-6">
            
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                <input type="text" name="fullname" required value="<?php echo htmlspecialchars($owner['fullname']); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
            </div>

            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Shop Name</label>
                <input type="text" name="shopname" required value="<?php echo htmlspecialchars($owner['shopname']); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary">
            </div>
            
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                <input type="email" disabled value="<?php echo htmlspecialchars($owner['email']); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-100 border border-gray-200 text-gray-400 font-bold cursor-not-allowed">
                <p class="text-xs text-gray-400 ml-2">Email cannot be changed.</p>
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

<?php include 'footer.php'; ?>