<?php
session_start();
require_once 'smtp_mailer.php';

// 1. Database Configuration
$host = 'localhost';
$dbname = 'moto_rental_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // In production, log this error instead of die()
    die("Database connection failed. Please contact support.");
}

// 2. Handle OTP Verification (Step 2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp_code']);
    
    if (isset($_SESSION['temp_user']) && $entered_otp == $_SESSION['temp_user']['otp']) {
        // OTP Match! Insert into Database
        $fullname = $_SESSION['temp_user']['fullname'];
        $email = $_SESSION['temp_user']['email'];
        $hashedPassword = $_SESSION['temp_user']['password'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers (fullname, email, hashedpassword, status, is_verified)
                VALUES (?, ?, ?, 'active', 0)
            ");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Create notification for admin
            $admin_message = "New customer '$fullname' registered and verified email.";
            $admin_link = 'admin/dashboard.php?page=verify_customers';
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_type, message, link) VALUES ('admin', ?, ?)");
            $notif_stmt->execute([$admin_message, $admin_link]);

            unset($_SESSION['temp_user']); // Clear temp session
            $_SESSION['success'] = "Registration successful! Email verified.";
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Registration failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid verification code. Please try again.";
    }
}
// 3. Handle Initial Registration (Step 1)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation Logic
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT userid FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "This email is already registered.";
        } else {
            // Generate 6-Digit OTP
            $otp = rand(100000, 999999);
            
            // Send Email
            $subject = "Email Verification - Mati City Moto Rentals";
            $body = "<h1>Verify your Email</h1><p>Your verification code is: <strong style='font-size:24px;'>$otp</strong></p><p>Please enter this code to complete your registration.</p>";
            
            if (send_gmail($email, $subject, $body)) {
                // Store in session
                $_SESSION['temp_user'] = [
                    'fullname' => $fullname, 'email' => $email, 
                    'password' => password_hash($password, PASSWORD_DEFAULT), 'otp' => $otp
                ];
            } else {
                $_SESSION['error'] = "Failed to send verification email. Please check your internet or try again.";
            }
        }
    }
    
    // Redirect back to show errors if we reached this point
    header("Location: register.php");
    exit();
}

// 4. Prepare display variables and clear sessions
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #005461;
            --secondary: #018790;
            --accent: #00B7B5;
            --bg: #F4F4F4;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
        }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-accent { color: var(--accent); }
        
        .auth-split-bg {
            background-image: linear-gradient(rgba(0, 84, 97, 0.85), rgba(1, 135, 144, 0.75)), 
                             url('https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=1200');
            background-size: cover;
            background-position: center;
        }

        input:focus {
            border-color: var(--accent) !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 183, 181, 0.1);
        }

        .form-container {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="text-gray-900 lg:overflow-hidden">

    <div class="flex min-h-screen">
        <!-- Left Side: Marketing -->
        <div class="hidden lg:flex lg:w-3/5 auth-split-bg relative items-center justify-center p-12">
            <div class="absolute top-10 left-10 flex items-center gap-2">
                <div class="w-10 h-10 rounded bg-white flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <span class="text-2xl font-bold text-white tracking-tight uppercase">Mati<span class="text-accent">Rentals</span></span>
            </div>

            <div class="z-10 text-white max-w-lg">
                <h1 class="text-5xl font-extrabold mb-6 leading-tight">Start Your Journey <br/> Today.</h1>
                <p class="text-xl opacity-90 mb-8 leading-relaxed">Book faster, track your rentals, and enjoy exclusive offers for your Mati City adventure.</p>
                
                <div class="flex gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-white/10 backdrop-blur flex items-center justify-center border border-white/20">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                        </div>
                        <span class="text-sm font-semibold tracking-wide uppercase">Easy Sign Up</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="w-full lg:w-2/5 flex items-center justify-center p-8 lg:p-16 bg-white overflow-y-auto">
            <div class="w-full max-w-sm form-container">
                
                <div class="lg:hidden flex justify-center mb-10">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <span class="text-xl font-bold text-primary tracking-tight uppercase">Mati<span class="text-accent">Rentals</span></span>
                    </div>
                </div>

                <div>
                    <h2 class="text-3xl font-extrabold text-primary mb-2 tracking-tight">Create Account</h2>
                    <p class="text-gray-400 mb-8">Join us and start exploring Mati City.</p>
                </div>

                <!-- ALERT MESSAGES -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg animate-pulse">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm rounded-r-lg">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['temp_user'])): ?>
                    <!-- STEP 2: OTP FORM -->
                    <form action="register.php" method="POST" class="space-y-5">
                        <div class="text-center mb-4">
                            <p class="text-sm text-gray-500">We sent a 6-digit code to <br><strong><?php echo htmlspecialchars($_SESSION['temp_user']['email']); ?></strong></p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Verification Code</label>
                            <input type="text" name="otp_code" required placeholder="123456" maxlength="6" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-center text-2xl font-bold tracking-widest text-primary">
                        </div>
                        <button type="submit" name="verify_otp" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">
                            Verify Email
                        </button>
                        <div class="text-center mt-4">
                            <a href="register.php" class="text-xs font-bold text-gray-400 hover:text-primary">Cancel / Resend</a>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- STEP 1: REGISTRATION FORM -->
                    <form action="register.php" method="POST" class="space-y-5">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                            <input type="text" name="fullname" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" placeholder="John Doe" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                            <input type="email" name="email" required placeholder="name@email.com" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Password</label>
                            <input type="password" name="password" required placeholder="••••••••" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Confirm Password</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                        </div>
                        
                        <button type="submit" name="register" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">
                            Send Verification Code
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-500">
                        Already have an account? 
                        <a href="index.php" class="text-accent font-bold hover:underline transition">Sign In</a>
                    </p>
                </div>
                
                <p class="mt-12 text-center text-[10px] text-gray-400 uppercase tracking-widest font-medium">
                    © 2024 Mati City Rentals. All Rights Reserved.
                </p>
            </div>
        </div>
    </div>
</body>
</html>