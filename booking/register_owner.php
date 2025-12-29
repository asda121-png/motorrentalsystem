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

// Initialize errors array
$errors = [];

// 2. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $shopname = trim($_POST['shopname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // --- Validation Logic ---
    
    // Full Name
    if (empty($fullname)) {
        $errors['fullname'] = "Full name is required.";
    }

    // Shop Name
    if (empty($shopname)) {
        $errors['shopname'] = "Shop/Business name is required.";
    }

    // Email Validation (Strict Regex)
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $errors['email'] = "Invalid format. Use formal format like Name@example.com";
    }

    // Password Validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\W]{8,}$/", $password)) {
        $errors['password'] = "Must be 8+ chars with 1 uppercase, 1 lowercase, & 1 number.";
    }

    // Confirm Password
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // If no format errors, check Database for Duplicates
    if (empty($errors)) {
        // 1. Check if Full Name already exists
        $stmtName = $pdo->prepare("SELECT ownerid FROM owners WHERE fullname = ?");
        $stmtName->execute([$fullname]);
        if ($stmtName->rowCount() > 0) {
            $errors['fullname'] = "This name is already registered.";
        }

        // 2. Check if Email already exists
        $stmtEmail = $pdo->prepare("SELECT ownerid FROM owners WHERE email = ?");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->rowCount() > 0) {
            $errors['email'] = "This email is already registered as an owner.";
        }

        // Proceed if still no errors
        if (empty($errors)) {
            // Hash and Store
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO owners (fullname, shopname, email, hashedpassword, role, status, created_at)
                    VALUES (?, ?, ?, ?, 'owner', 'pending', NOW())
                ");
                $stmt->execute([$fullname, $shopname, $email, $hashedPassword]);

                // Create notification for admin
                $admin_message = "New owner '$fullname' ($shopname) registered and needs verification.";
                $admin_link = 'admin/dashboard.php?page=verify_owners';
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_type, message, link) VALUES ('admin', ?, ?)");
                $notif_stmt->execute([$admin_message, $admin_link]);

                $_SESSION['success'] = "Registration successful! Your account is pending admin approval.";
                header("Location: login.php"); // Redirect to login
                exit();
            } catch (Exception $e) {
                $_SESSION['global_error'] = "An unexpected error occurred. Please try again.";
            }
        }
    }
    // Note: We do NOT redirect here if there are errors so we can show them.
}

// 3. Prepare display variables
$global_error = $_SESSION['global_error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['global_error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Registration - Mati City Moto Rentals</title>
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

        /* HIDE DEFAULT BROWSER EYE to prevent conflict */
        input::-ms-reveal,
        input::-ms-clear {
            display: none;
        }
        
        .auth-split-bg {
            background-image: linear-gradient(rgba(0, 50, 60, 0.9), rgba(1, 100, 110, 0.8)), 
                             url('https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=1200');
            background-size: cover;
            background-position: center;
        }

        input:focus {
            border-color: var(--accent) !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 183, 181, 0.1);
        }
        
        /* Error border style */
        input.input-error {
            border-color: #EF4444 !important;
            background-color: #FEF2F2 !important;
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
                <h1 class="text-5xl font-extrabold mb-6 leading-tight">Partner With Us. <br/> Grow Your Fleet.</h1>
                <p class="text-xl opacity-90 mb-8 leading-relaxed">Join the largest motorcycle rental network in Mati City. Manage bookings, track revenue, and reach more customers.</p>
            </div>
        </div>

        <div class="w-full lg:w-2/5 flex items-center justify-center p-8 lg:p-16 bg-white overflow-y-auto">
            <div class="w-full max-w-sm form-container">
                
                <div>
                    <h2 class="text-3xl font-extrabold text-primary mb-2 tracking-tight">Owner Registration</h2>
                    <p class="text-gray-400 mb-8">Create an account to list your motorcycles.</p>
                </div>

                <?php if ($global_error): ?>
                    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded-r-lg animate-pulse">
                        <?php echo htmlspecialchars($global_error); ?>
                    </div>
                <?php endif; ?>

                <form action="register_owner.php" method="POST" class="space-y-5">
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                        <input type="text" name="fullname" 
                               value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" 
                               placeholder="Juan Dela Cruz" 
                               class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['fullname']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                        <?php if (isset($errors['fullname'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['fullname']; ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Shop / Business Name</label>
                        <input type="text" name="shopname" 
                               value="<?php echo htmlspecialchars($_POST['shopname'] ?? ''); ?>" 
                               placeholder="Juan's Motor Rentals" 
                               class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['shopname']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                        <?php if (isset($errors['shopname'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['shopname']; ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                        <input type="text" name="email" 
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
                                <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
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
                                <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                    <line x1="1" y1="1" x2="23" y2="23"></line>
                                </svg>
                            </button>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['confirm_password']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">
                        Register Business
                    </button>
                </form>

                <div class="mt-8 text-center">
                    <p class="text-sm text-gray-500">
                        Already have an account? 
                        <a href="login.php" class="text-accent font-bold hover:underline transition">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function checkInput(input) {
            // Find the button (sibling in the same container)
            const btn = input.parentElement.querySelector('button');
            
            // If input has text, remove 'hidden' class from button
            if (input.value.length > 0) {
                btn.classList.remove('hidden');
            } else {
                // If empty, hide button again
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
                // Show Slashed Eye (index 1)
                svgs[0].classList.add('hidden'); 
                svgs[0].classList.remove('block'); 
                svgs[1].classList.remove('hidden');
                svgs[1].classList.add('block');
            } else {
                input.type = "password";
                // Show Open Eye (index 0)
                svgs[0].classList.remove('hidden');
                svgs[0].classList.add('block');
                svgs[1].classList.add('hidden');
                svgs[1].classList.remove('block');
            }
        }
    </script>
</body>
</html>