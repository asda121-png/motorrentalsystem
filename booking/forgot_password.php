<?php
session_start();
require_once 'smtp_mailer.php';

// Database Config
$host = 'localhost'; $dbname = 'moto_rental_db'; $username = 'root'; $password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) { die("Database connection failed."); }

$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Check Customers
    $stmt = $pdo->prepare("SELECT userid FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $table = 'customers';
    $id_col = 'userid';
    $id = $user['userid'] ?? null;

    // Check Owners if not found
    if (!$user) {
        $stmt = $pdo->prepare("SELECT ownerid FROM owners WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $table = 'owners';
        $id_col = 'ownerid';
        $id = $user['ownerid'] ?? null;
    }

    if ($user) {
        $token = bin2hex(random_bytes(16));
        $token_hash = hash("sha256", $token);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 30); // 30 mins

        $update = $pdo->prepare("UPDATE $table SET reset_token_hash = ?, reset_token_expires_at = ? WHERE $id_col = ?");
        $update->execute([$token_hash, $expiry, $id]);

        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token&type=$table";
        
        $subject = "Password Reset Request";
        $body = "<h1>Reset Your Password</h1>
                 <p>Click the link below to reset your password. This link expires in 30 minutes.</p>
                 <a href='$resetLink'>$resetLink</a>";
        
        if (send_gmail($email, $subject, $body)) {
            $msg = "Reset link sent to your email.";
            $msg_type = "green";
        } else {
            $msg = "Failed to send email. Try again later.";
            $msg_type = "red";
        }
    } else {
        $msg = "Email not found in our records.";
        $msg_type = "red";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Mati City Moto Rentals</title>
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
            <h1 class="text-2xl font-black text-primary">Forgot Password?</h1>
            <p class="text-gray-400 text-sm mt-2">Enter your email to receive a reset link.</p>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 bg-<?php echo $msg_type; ?>-50 text-<?php echo $msg_type; ?>-600 rounded-xl text-sm font-bold border border-<?php echo $msg_type; ?>-100">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="space-y-2">
                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                <input type="email" name="email" required class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-[#005461]">
            </div>
            
            <button type="submit" class="w-full py-4 rounded-2xl bg-primary text-white font-bold shadow-lg hover:bg-[#018790] transition-all">
                Send Reset Link
            </button>
        </form>

        <div class="mt-8 text-center">
            <a href="login.php" class="text-sm font-bold text-gray-400 hover:text-primary transition">Back to Login</a>
        </div>
    </div>
</body>
</html>