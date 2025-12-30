<?php
session_start();
// 1. Database Configuration
$host = 'localhost';
$dbname = 'moto_rental_db';
$username = 'root';
$password = '';
// Initialize PDO connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 1. Database Configuration
$host = 'localhost';
$dbname = 'moto_rental_db';
$username = 'root';
$password = '';

// 2. Two-Step Registration Logic

require_once 'smtp_mailer.php';
if (!isset($_SESSION['owner_reg_step'])) $_SESSION['owner_reg_step'] = 1;

// ==========================================
// Handle Cancel & Resend Actions (Phase 3)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset(
        $_SESSION['owner_reg_step'],
        $_SESSION['owner_reg_shopname'],
        $_SESSION['owner_reg_location'],
        $_SESSION['owner_reg_otp'],
        $_SESSION['owner_reg_fullname'],
        $_SESSION['owner_reg_email'],
        $_SESSION['owner_reg_password']
    );
    header("Location: register_owner.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    if (isset($_SESSION['owner_reg_email'])) {
        $email = $_SESSION['owner_reg_email'];
        $new_otp = rand(100000, 999999);
        $_SESSION['owner_reg_otp'] = $new_otp;
        $subject = "Resend Verification Code - MatiMotoRental";
        $body = "<h1>Verify your Email</h1><p>Your new verification code is: <strong style='font-size:24px;'>$new_otp</strong></p>";
        if (send_gmail($email, $subject, $body)) {
            $_SESSION['success'] = "New code sent to $email!";
        } else {
            $_SESSION['global_error'] = "Failed to resend code. Please check your connection.";
        }
    } else {
        $_SESSION['global_error'] = "Session expired. Please register again.";
    }
    header("Location: register_owner.php");
    exit();
}

// Step 1: Business Info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step1'])) {
    $shopname = trim($_POST['shopname'] ?? '');
    $province = trim($_POST['province'] ?? 'Davao Oriental');
    $city = trim($_POST['city'] ?? 'Mati City');
    $barangay = trim($_POST['barangay'] ?? '');
    $street_landmark = trim($_POST['street_landmark'] ?? '');
    // File upload validation
    $permitPath = '';
    $validIdPath = '';
    $barangayClearancePath = '';
    $uploadDir = __DIR__ . '/assets/owner_uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    // Business Permit
    if (!isset($_FILES['business_permit']) || $_FILES['business_permit']['error'] !== UPLOAD_ERR_OK) {
        $errors['business_permit'] = "Business permit is required.";
    } else {
        $permitInfo = pathinfo($_FILES['business_permit']['name']);
        $permitExt = strtolower($permitInfo['extension']);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($permitExt, $allowed)) {
            $errors['business_permit'] = "Invalid file type for business permit.";
        } else {
            $permitPath = $uploadDir . uniqid('permit_') . '.' . $permitExt;
            if (!move_uploaded_file($_FILES['business_permit']['tmp_name'], $permitPath)) {
                $errors['business_permit'] = "Failed to upload business permit.";
            }
        }
    }
    // Valid ID
    if (!isset($_FILES['valid_id']) || $_FILES['valid_id']['error'] !== UPLOAD_ERR_OK) {
        $errors['valid_id'] = "Valid ID is required.";
    } else {
        $idInfo = pathinfo($_FILES['valid_id']['name']);
        $idExt = strtolower($idInfo['extension']);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($idExt, $allowed)) {
            $errors['valid_id'] = "Invalid file type for valid ID.";
        } else {
            $validIdPath = $uploadDir . uniqid('validid_') . '.' . $idExt;
            if (!move_uploaded_file($_FILES['valid_id']['tmp_name'], $validIdPath)) {
                $errors['valid_id'] = "Failed to upload valid ID.";
            }
        }
    }
    // Barangay Clearance
    if (!isset($_FILES['barangay_clearance']) || $_FILES['barangay_clearance']['error'] !== UPLOAD_ERR_OK) {
        $errors['barangay_clearance'] = "Barangay clearance is required.";
    } else {
        $brgyInfo = pathinfo($_FILES['barangay_clearance']['name']);
        $brgyExt = strtolower($brgyInfo['extension']);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($brgyExt, $allowed)) {
            $errors['barangay_clearance'] = "Invalid file type for barangay clearance.";
        } else {
            $barangayClearancePath = $uploadDir . uniqid('brgy_') . '.' . $brgyExt;
            if (!move_uploaded_file($_FILES['barangay_clearance']['tmp_name'], $barangayClearancePath)) {
                $errors['barangay_clearance'] = "Failed to upload barangay clearance.";
            }
        }
    }
    if (empty($shopname)) {
        $errors['shopname'] = "Shop/Business name is required.";
    }
    if (empty($barangay)) {
        $errors['barangay'] = "Barangay is required.";
    }
    if (!isset($_POST['terms'])) {
        $errors['terms'] = "You must agree to the Terms and Conditions.";
    }
    if (empty($errors)) {
        $_SESSION['owner_reg_shopname'] = $shopname;
        $_SESSION['owner_reg_province'] = $province;
        $_SESSION['owner_reg_city'] = $city;
        $_SESSION['owner_reg_barangay'] = $barangay;
        $_SESSION['owner_reg_street_landmark'] = $street_landmark;
        $_SESSION['owner_reg_permit'] = $permitPath;
        $_SESSION['owner_reg_validid'] = $validIdPath;
        $_SESSION['owner_reg_barangay_clearance'] = $barangayClearancePath;
        $_SESSION['owner_reg_step'] = 2;
    }
}

// Step 2: Personal Info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step2'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullname)) {
        $errors['fullname'] = "Full name is required.";
    }
    if (empty($phone_number)) {
        $errors['phone_number'] = "Phone number is required.";
    } elseif (!preg_match('/^[0-9]{11}$/', $phone_number)) {
        $errors['phone_number'] = "Phone number must be exactly 11 digits.";
    }
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$/", $email)) {
        $errors['email'] = "Invalid format. Use formal format like Name@example.com";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)[a-zA-Z\\d\\W]{8,}$/", $password)) {
        $errors['password'] = "Must be 8+ chars with 1 uppercase, 1 lowercase, & 1 number.";
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // Check for duplicates
    if (empty($errors)) {
        $stmtName = $pdo->prepare("SELECT ownerid FROM owners WHERE fullname = ?");
        $stmtName->execute([$fullname]);
        if ($stmtName->rowCount() > 0) {
            $errors['fullname'] = "This name is already registered.";
        }
        $stmtEmail = $pdo->prepare("SELECT ownerid FROM owners WHERE email = ?");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->rowCount() > 0) {
            $errors['email'] = "This email is already registered as an owner.";
        }
    }

    if (empty($errors)) {
        $_SESSION['owner_reg_fullname'] = $fullname;
        $_SESSION['owner_reg_phone_number'] = $phone_number;
        $_SESSION['owner_reg_email'] = $email;
        $_SESSION['owner_reg_password'] = $password;
        // Generate and send OTP when moving to step 3
        $otp = rand(100000, 999999);
        $_SESSION['owner_reg_otp'] = $otp;
        $subject = "Email Verification - MatiMotoRental";
        $body = "<h1>Verify your Email</h1><p>Your verification code is: <strong style='font-size:24px;'>$otp</strong></p><p>Please enter this code to complete your registration.</p>";
        send_gmail($email, $subject, $body);
        $_SESSION['owner_reg_step'] = 3;
    }
}

// Step 3: Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step3'])) {
    $otp_code = trim($_POST['otp_code'] ?? '');
    // Check OTP
    if (!isset($_SESSION['owner_reg_otp']) || $otp_code != $_SESSION['owner_reg_otp']) {
        $errors['otp_code'] = "Invalid or missing verification code.";
    }
    // Register if all good
    if (empty($errors)) {
        $fullname = $_SESSION['owner_reg_fullname'];
        $phone_number = $_SESSION['owner_reg_phone_number'] ?? '';
        $email = $_SESSION['owner_reg_email'];
        $password = $_SESSION['owner_reg_password'];
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $permitPath = $_SESSION['owner_reg_permit'] ?? '';
        $validIdPath = $_SESSION['owner_reg_validid'] ?? '';
        $barangayClearancePath = $_SESSION['owner_reg_barangay_clearance'] ?? '';
        $province = $_SESSION['owner_reg_province'] ?? 'Davao Oriental';
        $city = $_SESSION['owner_reg_city'] ?? 'Mati City';
        $barangay = $_SESSION['owner_reg_barangay'] ?? '';
        $street_landmark = $_SESSION['owner_reg_street_landmark'] ?? '';
        $location = $province . ', ' . $city . ', ' . $barangay;
        if (!empty($street_landmark)) {
            $location .= ', ' . $street_landmark;
        }
        try {
            $stmt = $pdo->prepare("
                INSERT INTO owners (fullname, shopname, location, email, phone_number, hashedpassword, business_permit, valid_id, barangay_clearance, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'owner', 'pending', NOW())
            ");
            $stmt->execute([
                $fullname,
                $_SESSION['owner_reg_shopname'],
                $location,
                $email,
                $phone_number,
                $hashedPassword,
                $permitPath,
                $validIdPath,
                $barangayClearancePath
            ]);
            $owner_id = $pdo->lastInsertId();
            $admin_message = "New owner '$fullname' (" . $_SESSION['owner_reg_shopname'] . ") registered and needs verification.";
            $admin_link = 'admin/dashboard.php?page=verify_owners';
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_type, message, link) VALUES ('admin', ?, ?)");
            $notif_stmt->execute([$admin_message, $admin_link]);
            // Clear OTP and registration session vars
            unset($_SESSION['owner_reg_otp']);
            unset(
                $_SESSION['owner_reg_step'],
                $_SESSION['owner_reg_shopname'],
                $_SESSION['owner_reg_province'],
                $_SESSION['owner_reg_city'],
                $_SESSION['owner_reg_barangay'],
                $_SESSION['owner_reg_street_landmark'],
                $_SESSION['owner_reg_fullname'],
                $_SESSION['owner_reg_email'],
                $_SESSION['owner_reg_password'],
                $_SESSION['owner_reg_permit'],
                $_SESSION['owner_reg_validid'],
                $_SESSION['owner_reg_barangay_clearance']
            );
            $_SESSION['success'] = "Registration successful! Your account is pending admin approval. You will receive an email once approved.";
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['global_error'] = "Database error: " . $e->getMessage();
        }
    }
}

// Send OTP (AJAX or POST)
if (isset($_POST['send_otp']) && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $otp = rand(100000, 999999);
    $_SESSION['owner_reg_otp'] = $otp;
    $subject = "Email Verification - MatiMotoRental";
    $body = "<h1>Verify your Email</h1><p>Your verification code is: <strong style='font-size:24px;'>$otp</strong></p><p>Please enter this code to complete your registration.</p>";
    if (send_gmail($email, $subject, $body)) {
        echo 'sent';
    } else {
        echo 'fail';
    }
    exit();
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
            html, body {
                height: 100%;
                min-height: 100%;
            }
            body {
                font-family: 'Inter', sans-serif;
                background-color: var(--bg);
                overflow-x: hidden;
                overflow-y: auto;
            }
            .bg-primary { background-color: var(--primary); }
            .bg-secondary { background-color: var(--secondary); }
            .text-primary { color: var(--primary); }
            .text-accent { color: var(--accent); }

            .auth-split-bg {
                min-height: 100vh;
                height: auto;
            }
            @media (max-width: 1024px) {
                .auth-split-bg {
                    min-height: unset;
                    height: auto;
                }
            }

            /* HIDE DEFAULT BROWSER EYE to prevent conflict */
            input::-ms-reveal,
            input::-ms-clear {
                display: none;
            }
            input:focus {
                outline: none;
                border-color: var(--accent);
            }
            input.input-error {
                border-color: #e53e3e;
                background-color: #fff5f5;
            }

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
<body class="text-gray-900">

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

                <?php if ($_SESSION['owner_reg_step'] == 1): ?>
                <form action="register_owner.php" method="POST" class="space-y-5" enctype="multipart/form-data">
                    <h3 class="text-lg font-bold text-primary mb-2">Business Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Shop / Business Name</label>
                            <input type="text" name="shopname" value="<?php echo htmlspecialchars($_POST['shopname'] ?? ''); ?>" placeholder="" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['shopname']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                            <?php if (isset($errors['shopname'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['shopname']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Province</label>
                            <input type="text" name="province" value="Davao Oriental" readonly class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-100 text-sm cursor-not-allowed">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">City / Municipality</label>
                            <input type="text" name="city" value="Mati City" readonly class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-100 text-sm cursor-not-allowed">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Barangay</label>
                            <select name="barangay" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['barangay']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                                <option value="">Select Barangay</option>
                                <option value="Dahican" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Dahican')) echo 'selected'; ?>>Dahican</option>
                                <option value="Central" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Central')) echo 'selected'; ?>>Central</option>
                                <option value="Tagbinonga" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Tagbinonga')) echo 'selected'; ?>>Tagbinonga</option>
                                <option value="Don Salvador" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Don Salvador')) echo 'selected'; ?>>Don Salvador</option>
                                <option value="Badas" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Badas')) echo 'selected'; ?>>Badas</option>
                                <option value="Bobon" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Bobon')) echo 'selected'; ?>>Bobon</option>
                                <option value="Busok" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Busok')) echo 'selected'; ?>>Busok</option>
                                <option value="Culian" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Culian')) echo 'selected'; ?>>Culian</option>
                                <option value="Don Enrique Lopez" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Don Enrique Lopez')) echo 'selected'; ?>>Don Enrique Lopez</option>
                                <option value="Don Martin Marundan" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Don Martin Marundan')) echo 'selected'; ?>>Don Martin Marundan</option>
                                <option value="Dawan" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Dawan')) echo 'selected'; ?>>Dawan</option>
                                <option value="Libudon" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Libudon')) echo 'selected'; ?>>Libudon</option>
                                <option value="Mamali" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Mamali')) echo 'selected'; ?>>Mamali</option>
                                <option value="Matiao" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Matiao')) echo 'selected'; ?>>Matiao</option>
                                <option value="Sainz" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Sainz')) echo 'selected'; ?>>Sainz</option>
                                <option value="Sanghay" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Sanghay')) echo 'selected'; ?>>Sanghay</option>
                                <option value="Tagabakid" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Tagabakid')) echo 'selected'; ?>>Tagabakid</option>
                                <option value="Tamisan" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Tamisan')) echo 'selected'; ?>>Tamisan</option>
                                <option value="Dila" <?php if((isset($_POST['barangay']) && $_POST['barangay'] == 'Dila')) echo 'selected'; ?>>Dila</option>
                            </select>
                            <?php if (isset($errors['barangay'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['barangay']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1.5 md:col-span-2">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Street / Landmark (Optional)</label>
                            <input type="text" name="street_landmark" value="<?php echo htmlspecialchars($_POST['street_landmark'] ?? ''); ?>" placeholder="" class="w-full px-6 py-4 rounded-2xl border border-gray-100 bg-gray-50 focus:bg-white transition-all text-sm">
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Business Permit </label>
                            <div class="relative w-full">
                                <label for="business_permit" class="w-full flex items-center justify-center px-6 py-3 rounded-2xl border <?php echo isset($errors['business_permit']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm cursor-pointer hover:bg-gray-100">
                                    <span class="text-gray-700">Choose File</span>
                                    <input type="file" id="business_permit" name="business_permit" accept=".pdf,.jpg,.jpeg,.png" class="absolute left-0 top-0 w-full h-full opacity-0 cursor-pointer" onchange="document.getElementById('business_permit_filename').textContent = this.files.length ? this.files[0].name : 'No file chosen'">
                                </label>
                                <span id="business_permit_filename" class="block text-xs text-gray-500 mt-1">No file chosen</span>
                            </div>
                            <?php if (isset($errors['business_permit'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['business_permit']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Barangay Clearance </label>
                            <div class="relative w-full">
                                <label for="barangay_clearance" class="w-full flex items-center justify-center px-6 py-3 rounded-2xl border <?php echo isset($errors['barangay_clearance']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm cursor-pointer hover:bg-gray-100">
                                    <span class="text-gray-700">Choose File</span>
                                    <input type="file" id="barangay_clearance" name="barangay_clearance" accept=".pdf,.jpg,.jpeg,.png" class="absolute left-0 top-0 w-full h-full opacity-0 cursor-pointer" onchange="document.getElementById('barangay_clearance_filename').textContent = this.files.length ? this.files[0].name : 'No file chosen'">
                                </label>
                                <span id="barangay_clearance_filename" class="block text-xs text-gray-500 mt-1">No file chosen</span>
                            </div>
                            <?php if (isset($errors['barangay_clearance'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['barangay_clearance']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Valid ID </label>
                            <div class="relative w-full">
                                <label for="valid_id" class="w-full flex items-center justify-center px-6 py-3 rounded-2xl border <?php echo isset($errors['valid_id']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm cursor-pointer hover:bg-gray-100">
                                    <span class="text-gray-700">Choose File</span>
                                    <input type="file" id="valid_id" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" class="absolute left-0 top-0 w-full h-full opacity-0 cursor-pointer" onchange="document.getElementById('valid_id_filename').textContent = this.files.length ? this.files[0].name : 'No file chosen'">
                                </label>
                                <span id="valid_id_filename" class="block text-xs text-gray-500 mt-1">No file chosen</span>
                            </div>
                            <?php if (isset($errors['valid_id'])): ?>
                                <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['valid_id']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-start gap-2 mt-2">
                        <input type="checkbox" id="terms" name="terms" value="1" class="mt-1" required <?php if(isset($_POST['terms'])) echo 'checked'; ?>>
                        <label for="terms" class="text-xs text-gray-700 select-none">I agree to the <a href="#" onclick="showTermsModal();return false;" class="text-primary underline font-bold">Owner Terms and Conditions</a> of Mati City Moto Rentals.</label>
                    </div>
                    <?php if (isset($errors['terms'])): ?>
                        <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['terms']; ?></p>
                    <?php endif; ?>
                    <!-- Owner Terms Modal -->
                    <div id="termsModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.45);">
                        <div style="max-width:800px; width:95vw; height:80vh; background:#fff; border-radius:18px; margin:40px auto; position:relative; box-shadow:0 8px 32px rgba(0,0,0,0.18); overflow:hidden;">
                            <button onclick="closeTermsModal()" style="position:absolute; top:10px; right:18px; background:none; border:none; font-size:2rem; color:#005461; cursor:pointer; z-index:10;">&times;</button>
                            <iframe src="terms_owner.html" style="width:100%; height:100%; border:none; border-radius:18px;"></iframe>
                        </div>
                    </div>
                    <button type="submit" name="step1" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">Next: Personal Info</button>
                <script>
                function showTermsModal() {
                    document.getElementById('termsModal').style.display = 'block';
                }
                function closeTermsModal() {
                    document.getElementById('termsModal').style.display = 'none';
                }
                </script>
                </form>
                <?php elseif ($_SESSION['owner_reg_step'] == 2): ?>
                <!-- Step 2: Personal Info -->
                <form action="register_owner.php" method="POST" class="space-y-5">
                    <h3 class="text-lg font-bold text-primary mb-2">Personal Information & Account</h3>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                        <input type="text" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" placeholder="Juan Dela Cruz" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['fullname']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                        <?php if (isset($errors['fullname'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['fullname']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Phone Number</label>
                        <input type="text" name="phone_number" maxlength="11" pattern="[0-9]{11}" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>" placeholder="09XXXXXXXXX" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['phone_number']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                        <?php if (isset($errors['phone_number'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['phone_number']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Gmail Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="name@gmail.com" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['email']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
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
                            <input type="password" name="password" id="password" oninput="checkInput(this); checkStrength(this.value)" placeholder="••••••••" class="w-full pl-6 pr-12 py-4 rounded-2xl border <?php echo isset($errors['password']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                            <button type="button" onclick="togglePassword('password', this)" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary focus:outline-none">
                                <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['password']; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Confirm Password</label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" oninput="checkInput(this)" placeholder="••••••••" class="w-full pl-6 pr-12 py-4 rounded-2xl border <?php echo isset($errors['confirm_password']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-sm">
                            <button type="button" onclick="togglePassword('confirm_password', this)" class="hidden absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary focus:outline-none">
                                <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['confirm_password']; ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="step2" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">Next: Verification</button>
                </form>
                <?php elseif ($_SESSION['owner_reg_step'] == 3): ?>
                <!-- Step 3: Verification (Match Customer) -->
                <form action="register_owner.php" method="POST" class="space-y-5">
                    <h3 class="text-lg font-bold text-primary mb-2">Email Verification</h3>
                    <div class="text-center mb-4">
                        <p class="text-sm text-gray-500">We sent a 6-digit code to <br><strong><?php echo htmlspecialchars($_SESSION['owner_reg_email']); ?></strong></p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-[10px] font-bold uppercase text-gray-400 tracking-widest ml-1">Verification Code</label>
                        <input type="text" name="otp_code" required placeholder="123456" maxlength="6" class="w-full px-6 py-4 rounded-2xl border <?php echo isset($errors['otp_code']) ? 'input-error' : 'border-gray-100'; ?> bg-gray-50 focus:bg-white transition-all text-center text-2xl font-bold tracking-widest text-primary">
                        <?php if (isset($errors['otp_code'])): ?>
                            <p class="text-red-500 text-[11px] font-medium ml-2 mt-1"><?php echo $errors['otp_code']; ?></p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="step3" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-base shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98] mt-4">Verify Email</button>
                    <div class="text-center mt-6 flex items-center justify-center gap-4">
                        <a href="register_owner.php?action=cancel" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors">Cancel</a>
                        <span class="text-gray-300 text-xs">|</span>
                        <a href="register_owner.php?action=resend" onclick="showLoader()" class="text-xs font-bold text-gray-400 hover:text-primary transition-colors">Resend Code</a>
                    </div>
                </form>
                <script>
                function showLoader() {
                    // Optionally implement a loader overlay if desired
                }
                </script>
                <?php endif; ?>

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