<?php
session_start();
// Database Config
$host = 'localhost'; $dbname = 'moto_rental_db'; $username = 'root'; $password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Database connection failed."); }

$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'customers'; // 'customers' or 'owners'
$msg = "";
$msg_type = "";

if (empty($token)) {
    die("Invalid request.");
}

$token_hash = hash("sha256", $token);
$table = ($type === 'owners') ? 'owners' : 'customers';
$id_col = ($type === 'owners') ? 'ownerid' : 'userid';

// Verify Token
$stmt = $pdo->prepare("SELECT * FROM $table WHERE reset_token_hash = ? AND reset_token_expires_at > NOW()");
$stmt->execute([$token_hash]);
$user = $stmt->fetch();

if (!$user) {
    die("Link is invalid or has expired.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if ($pass !== $confirm) {
        $msg = "Passwords do not match.";
        $msg_type = "red";
    } elseif (strlen($pass) < 8) {
        $msg = "Password must be at least 8 characters.";
        $msg_type = "red";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE $table SET hashedpassword = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE $id_col = ?");
        $update->execute([$hashed, $user[$id_col]]);
        
        $_SESSION['success'] = "Password reset successful. You can now login.";
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F4F4F4; }
        .text-primary { color: #005461; }
        .bg-primary { background-color: #005461; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-[2rem] shadow-xl w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-black text-primary">Set New Password</h1>
            <p class="text-gray-400 text-sm mt-2">Enter your new password below.</p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 bg-<?php echo $msg_type; ?>-50 text-<?php echo $msg_type; ?>-600 rounded-xl text-sm font-bold border border-<?php echo $msg_type; ?>-100">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">New Password</label>
                <input type="password" name="password" required class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-[#005461]">
            </div>
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Confirm Password</label>
                <input type="password" name="confirm_password" required class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-[#005461]">
            </div>
            
            <button type="submit" class="w-full py-4 rounded-2xl bg-primary text-white font-bold shadow-lg hover:bg-[#018790] transition-all">
                Update Password
            </button>
        </form>
    </div>
</body>
</html>