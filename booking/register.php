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
    die("Database connection failed. Please contact support.");
}

// Initialize errors array
$errors = [];

// ==========================================
// 2. Handle Cancel & Resend Actions
// ==========================================

// Handle "Cancel"
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['temp_user']);
    header("Location: register.php");
    exit();
}

// Handle "Resend"
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    if (isset($_SESSION['temp_user'])) {
        $email = $_SESSION['temp_user']['email'];
        $new_otp = rand(100000, 999999);
        
        // Update OTP in session
        $_SESSION['temp_user']['otp'] = $new_otp;
        
        // Send Email
        $subject = "Resend Verification Code - Mati City Moto Rentals";
        $body = "<h1>Verify your Email</h1><p>Your new verification code is: <strong style='font-size:24px;'>$new_otp</strong></p>";
        
        if (send_gmail($email, $subject, $body)) {
            $_SESSION['success'] = "New code sent to $email!";
        } else {
            $_SESSION['global_error'] = "Failed to resend code. Please check your connection.";
        }
    } else {
        $_SESSION['global_error'] = "Session expired. Please register again.";
    }
    header("Location: register.php");
    exit();
}

// ==========================================
// 3. Handle OTP Verification (Step 2)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp_code']);
    
    if (isset($_SESSION['temp_user']) && $entered_otp == $_SESSION['temp_user']['otp']) {
        // OTP Match!
        $fullname = $_SESSION['temp_user']['fullname'];
        $email = $_SESSION['temp_user']['email'];
        $hashedPassword = $_SESSION['temp_user']['password'];

        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers (fullname, email, hashedpassword, status, is_verified)
                VALUES (?, ?, ?, 'active', 0)
            ");
            $stmt->execute([$fullname, $email, $hashedPassword]);

            // Notification
            $admin_message = "New customer '$fullname' registered and verified email.";
            $admin_link = 'admin/dashboard.php?page=verify_customers';
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_type, message, link) VALUES ('admin', ?, ?)");
            $notif_stmt->execute([$admin_message, $admin_link]);

            unset($_SESSION['temp_user']);
            $_SESSION['success'] = "Registration successful! Email verified.";
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['global_error'] = "Registration failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['global_error'] = "Invalid verification code. Please try again.";
    }
}

// ==========================================
// 4. Handle Initial Registration (Step 1)
// ==========================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullname)) {
        $errors['fullname'] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $errors['email'] = "Invalid format. Use formal format like Name@example.com";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\W]{8,}$/", $password)) {
        $errors['password'] = "Must be 8+ chars with 1 uppercase, 1 lowercase, & 1 number.";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // Database Checks if no format errors
    if (empty($errors)) {
        // Check if Name exists
        $stmtName = $pdo->prepare("SELECT userid FROM customers WHERE fullname = ?");
        $stmtName->execute([$fullname]);
        if ($stmtName->rowCount() > 0) {
            $errors['fullname'] = "This name is already registered.";
        }

        // Check if Email exists
        $stmtEmail = $pdo->prepare("SELECT userid FROM customers WHERE email = ?");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->rowCount() > 0) {
            $errors['email'] = "This email is already registered.";
        }

        // Proceed if still no errors
        if (empty($errors)) {
            $otp = rand(100000, 999999);
            $subject = "Email Verification - Mati City Moto Rentals";
            $body = "<h1>Verify your Email</h1><p>Your verification code is: <strong style='font-size:24px;'>$otp</strong></p><p>Please enter this code to complete your registration.</p>";
            
            if (send_gmail($email, $subject, $body)) {
                $_SESSION['temp_user'] = [
                    'fullname' => $fullname, 'email' => $email, 
                    'password' => password_hash($password, PASSWORD_DEFAULT), 'otp' => $otp
                ];
                header("Location: register.php");
                exit();
            } else {
                $_SESSION['global_error'] = "Failed to send verification email. Please check your internet.";
            }
        }
    }
}

$global_error = $_SESSION['global_error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['global_error'], $_SESSION['success']);
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
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); }
        .bg-primary { background-color: var(--primary); }
        .text-primary { color: var(--primary); }
        .text-accent { color: var(--accent); }
        
        input::-ms-reveal, input::-ms-clear { display: none; }

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
        
        input.input-error {
            border-color: #EF4444 !important;
            background-color: #FEF2F2 !important;
        }

        .form-container { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* LOADING SPINNER */
        .loader {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid var(--primary);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="text-gray-900 lg:overflow-hidden">

    <div id="loading-overlay" class="hidden fixed inset-0 bg-white/80 backdrop-blur-sm z-50 items-center justify-center flex-col">
        <div class="loader mb-4"></div>
        <p class="text-primary font-bold animate-pulse">Processing...</p>
    </div>

    <div class="flex min-h-screen">
        <div class="hidden lg:flex lg:w-3/5 auth-split-bg relative items-center justify-center p-12">
            <div class="absolute top-10 left-10 flex items-center gap-2">
                <div class="w-10 h-10 rounded bg-white flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <span class="text-2xl font-bold text-white tracking-tight">MatiMotoRental</span>
            </div>
            <div class="z-10 text-white max-w-lg">
                <h1 class="text-5xl font-extrabold mb-6 leading-tight">Start Your Journey <br/> Today.</h1>
                <p class="text-xl opacity-90 mb-8 leading-relaxed">Book faster, track your rentals, and enjoy exclusive offers for your Mati City adventure.</p>
            </div>
        </div>

        <div class="w-full lg:w-2/5 flex items-center justify-center p-8 lg:p-16 bg-white overflow-y-auto">
            <div class="w-full max-w-sm form-container">
                
                <div class="lg:hidden flex justify-center mb-10">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <span class="text-xl font-bold text-primary tracking-tight">MatiMotoRental</span>
                    </div>
                </div>

                <div>
                    <h2 class="text-3xl font-extrabold text-primary mb-2 tracking-tight">Create Account</h2>
                    <p class="text-gray-400 mb-8">Join us and start exploring Mati City.</p>
                </div>

                <?php if ($global_error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg animate-pulse">
                        <?php echo htmlspecialchars($global_error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm rounded-r-lg">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['temp_user'])): ?>
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
                        
                        <div class="text-center mt-6 flex items-center justify-center gap-4">
                            <a href="register.php?action=cancel" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors">Cancel</a>
                            <span class="text-gray-300 text-xs">|</span>
                            <a href="register.php?action=resend" onclick="showLoader()" class="text-xs font-bold text-gray-400 hover:text-primary transition-colors">Resend Code</a>
                        </div>
                    </form>

                <?php else: ?>
                    <form action="register.php" method="POST" class="space-y-5">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                            <input type="text" name="fullname" 
                                   value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" 
                                   placeholder="John Doe" 
                                   class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['fullname']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                            <?php if (isset($errors['fullname'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['fullname']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                            <input type="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   placeholder="name@example.com" 
                                   class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['email']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                            <?php if (isset($errors['email'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center ml-1">
                                <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest">Password</label>
                                <span id="password-strength" class="text-[10px] font-bold uppercase tracking-widest hidden"></span>
                            </div>
                            
                            <div class="relative">
                                <input type="password" name="password" id="password" oninput="checkInput(this); checkStrength(this.value)" placeholder="••••••••" 
                                       class="w-full pl-6 pr-12 py-4 rounded-2xl border <?php echo isset($errors['password']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                                
                                <button type="button" onclick="togglePassword('password', this)" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary focus:outline-none">
                                    <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['password']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" oninput="checkInput(this)" placeholder="••••••••" 
                                       class="w-full pl-6 pr-12 py-4 rounded-2xl border <?php echo isset($errors['confirm_password']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                                
                                <button type="button" onclick="togglePassword('confirm_password', this)" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary focus:outline-none">
                                    <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['confirm_password']; ?></p>
                            <?php endif; ?>
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

    <script>
        function showLoader() {
            document.getElementById('loading-overlay').classList.remove('hidden');
            document.getElementById('loading-overlay').classList.add('flex');
        }

        function checkInput(input) {
            const btn = input.parentElement.querySelector('button');
            if (input.value.length > 0) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        }

        // NEW: Password Strength Checker
        function checkStrength(password) {
            const strengthText = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthText.classList.add('hidden');
                return;
            }

            strengthText.classList.remove('hidden');

            // Logic matching the regex: 8+ chars, 1 Upper, 1 Lower, 1 Number
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNum = /\d/.test(password);
            const isLongEnough = password.length >= 8;

            if (isLongEnough && hasUpper && hasLower && hasNum) {
                strengthText.innerText = "Strong";
                strengthText.className = "text-[10px] font-bold uppercase tracking-widest text-green-500";
            } else if (password.length >= 6) {
                strengthText.innerText = "Medium";
                strengthText.className = "text-[10px] font-bold uppercase tracking-widest text-yellow-500";
            } else {
                strengthText.innerText = "Weak";
                strengthText.className = "text-[10px] font-bold uppercase tracking-widest text-red-500";
            }
        }

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const svgs = btn.querySelectorAll('svg');
            
            if (input.type === "password") {
                input.type = "text";
                svgs[0].classList.add('hidden'); 
                svgs[0].classList.remove('block'); 
                svgs[1].classList.remove('hidden');
                svgs[1].classList.add('block');
            } else {
                input.type = "password";
                svgs[0].classList.remove('hidden');
                svgs[0].classList.add('block');
                svgs[1].classList.add('hidden');
                svgs[1].classList.remove('block');
            }
        }
    </script>
</body>
</html>