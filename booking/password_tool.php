<?php
session_start();

// Database Connection
$host = 'localhost';
$dbname = 'moto_rental_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$msg = "";

// 1. Handle Single User Password Reset
if (isset($_POST['update_single'])) {
    $email = trim($_POST['email']);
    $new_pass = $_POST['new_password'];
    $table = $_POST['table']; // 'customers' or 'owners'
    
    if (!empty($email) && !empty($new_pass)) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $sql = "UPDATE $table SET hashedpassword = ? WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed, $email]);
        
        if ($stmt->rowCount() > 0) {
            $msg = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 border border-green-200'>✅ Password updated successfully for <strong>$email</strong>. You can now login.</div>";
        } else {
            $msg = "<div class='bg-yellow-100 text-yellow-700 p-4 rounded-xl mb-6 border border-yellow-200'>⚠️ No record found for that email in <strong>$table</strong> (or the password was already the same).</div>";
        }
    }
}

// 2. Handle Bulk Fix (Convert Plain Text to Hash)
if (isset($_POST['bulk_fix'])) {
    $count = 0;
    $tables = ['customers' => 'userid', 'owners' => 'ownerid'];
    
    foreach ($tables as $table => $id_col) {
        // Fetch all users
        $stmt = $pdo->query("SELECT $id_col, hashedpassword FROM $table");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $current_pass = $row['hashedpassword'];
            
            // Bcrypt hashes are always 60 characters long. 
            // If it's shorter, it's likely plain text that needs fixing.
            if (strlen($current_pass) < 60 && !empty($current_pass)) {
                $new_hash = password_hash($current_pass, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE $table SET hashedpassword = ? WHERE $id_col = ?");
                $update->execute([$new_hash, $row[$id_col]]);
                $count++;
            }
        }
    }
    $msg = "<div class='bg-blue-100 text-blue-700 p-4 rounded-xl mb-6 border border-blue-200'>ℹ️ Scanned database and converted <strong>$count</strong> plain text passwords to secure hashes.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Tool - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #F4F4F4; }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <div class="bg-white w-full max-w-lg p-8 rounded-3xl shadow-xl">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Database Password Tool</h1>
        <p class="text-slate-500 text-sm mb-6">Use this tool to fix login issues caused by incorrect password formats.</p>

        <?php echo $msg; ?>

        <!-- Option 1: Reset Specific User -->
        <div class="mb-8 border-b border-gray-100 pb-8">
            <h2 class="text-lg font-bold text-[#005461] mb-4">Option 1: Reset a User's Password</h2>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <select name="table" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-bold">
                        <option value="customers">Customer</option>
                        <option value="owners">Owner</option>
                    </select>
                    <input type="email" name="email" placeholder="Email Address" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm">
                </div>
                <input type="text" name="new_password" placeholder="Enter New Password" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm">
                <button type="submit" name="update_single" class="w-full py-3 rounded-xl bg-[#005461] text-white font-bold hover:bg-[#018790] transition shadow-lg shadow-[#005461]/20">
                    Update Password
                </button>
            </form>
        </div>

        <!-- Option 2: Bulk Fix -->
        <div>
            <h2 class="text-lg font-bold text-[#005461] mb-2">Option 2: Fix Plain Text Passwords</h2>
            <p class="text-xs text-gray-400 mb-4">
                If you manually inserted passwords like "password123" into the database via phpMyAdmin, 
                the login will fail because the system expects a hash. Click below to automatically hash them.
            </p>
            <form method="POST">
                <button type="submit" name="bulk_fix" class="w-full py-3 rounded-xl bg-white border-2 border-[#005461] text-[#005461] font-bold hover:bg-gray-50 transition">
                    Scan & Fix All Plain Text Passwords
                </button>
            </form>
        </div>

        <div class="mt-8 text-center">
            <a href="login.php" class="text-sm font-bold text-gray-400 hover:text-[#005461]">Back to Login Page</a>
        </div>
    </div>

</body>
</html>