<?php
session_start();

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

// Redirect if already logged in
if (isset($_SESSION['userid'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'owner') {
        header("Location: owner/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

// 2. Process Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please enter both email and password.";
    } else {
        // Hardcoded Admin Login
        if ($email === 'Admin123@gmail.com' && $password === 'Admin123') {
            $_SESSION['userid'] = 0; // System Admin ID
            $_SESSION['fullname'] = 'System Administrator';
            $_SESSION['role'] = 'admin';
            header("Location: admin/dashboard.php");
            exit();
        }

        // 1. Check Customers Table
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userType = 'customer';

        // 2. If not found, Check Owners Table
        if (!$user) {
            $stmt = $pdo->prepare("SELECT * FROM owners WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userType = 'owner';
        }

        if ($user && password_verify($password, $user['hashedpassword'])) {
            // Check status
            $block_statuses = ['inactive', 'banned', 'disabled'];
            if (in_array($user['status'], $block_statuses)) {
                $_SESSION['error'] = "Your account is currently inactive or disabled.";
            } elseif ($userType !== 'owner' && $user['status'] === 'pending') {
                $_SESSION['error'] = "Your account is pending verification.";
            } else {
                // Set Session Variables
                $_SESSION['userid'] = ($userType === 'owner') ? $user['ownerid'] : $user['userid'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['account_status'] = $user['status'];
                $_SESSION['email'] = $user['email']; // Store email for autofill

                // 3. Role-Based Redirection
                if ($user['role'] === 'admin') {
                    header("Location: admin/dashboard.php");
                } elseif ($user['role'] === 'owner') {
                    header("Location: owner/dashboard.php");
                    exit();
                } else {
                    // Default to customer landing page
                    header("Location: index.php");
                }
                exit();
            }
        } else {
            $_SESSION['error'] = "Invalid email or password.";
        }
    }
    
    // Redirect back to show errors
    header("Location: login.php");
    exit();
}

// Prepare display variables
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mati City Moto Rentals</title>
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
                             url('https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&q=80&w=1200');
            background-size: cover;
            background-position: center;
        }

        input:focus {
            border-color: var(--accent) !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 183, 181, 0.1);
        }

        /* HIDE DEFAULT BROWSER EYE */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
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
        <!-- Left Side: Visuals -->
        <div class="hidden lg:flex lg:w-3/5 auth-split-bg relative items-center justify-center p-12">
            <a href="index.php" class="absolute top-10 left-10 flex items-center gap-2">
                <div class="w-10 h-10 rounded bg-white flex items-center justify-center shadow-lg">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <span  class="text-2xl font-bold text-white tracking-tight">MatiMotoRental</span>
            </a>

            <div class="z-10 text-white max-w-lg">
                <h1 class="text-5xl font-extrabold mb-6 leading-tight">Welcome Back <br/> Adventurer.</h1>
                <p class="text-xl opacity-90 mb-8 leading-relaxed">Log in to manage your bookings and explore the beautiful sights of Mati City.</p>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="w-full lg:w-2/5 flex items-center justify-center p-8 lg:p-16 bg-white overflow-y-auto">
            <div class="w-full max-w-sm form-container">
                
                <div class="lg:hidden flex justify-center mb-10">
                    <a href="index.php" class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <span class="text-xl font-bold text-primary tracking-tight uppercase">Mati<span class="text-accent">Rentals</span></span>
                    </a>
                </div>

                <div>
                    <h2 class="text-3xl font-extrabold text-primary mb-2 tracking-tight">Sign In</h2>
                    <p class="text-gray-400 mb-8">Access your account to continue.</p>
                </div>

                <!-- ALERT MESSAGES -->
                <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 text-sm rounded-r-lg">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                        <input type="email" name="email" required placeholder="name@email.com" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex justify-between items-center px-1">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest">Password</label>
                            <a href="forgot_password.php" class="text-[10px] font-bold text-accent uppercase hover:underline">Forgot?</a>
                        </div>
                        <div class="relative">
                            <input type="password" name="password" id="password" required placeholder="••••••••" oninput="checkInput(this)" class="w-full pl-6 pr-12 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                            <button type="button" onclick="togglePassword('password', this)" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary focus:outline-none">
                                <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">
                        Sign In
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-500">
                        Don't have an account? 
                        <a href="register.php" class="text-accent font-bold hover:underline transition">Register Now</a>
                    </p>
                    <p class="text-sm text-gray-500 mt-3">
                        Sign up as an owner? 
                        <a href="register_owner.php" class="text-primary font-bold hover:underline transition">Register here</a>
                    </p>
                </div>
                
                <p class="mt-12 text-center text-[10px] text-gray-400 uppercase tracking-widest font-medium">
                    © 2025 Mati City Rentals. All Rights Reserved.
                </p>
            </div>
        </div>
    </div>

    <script>
        function checkInput(input) {
            const btn = input.parentElement.querySelector('button');
            if (input.value.length > 0) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
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