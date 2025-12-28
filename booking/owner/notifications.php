<?php
session_start();

// Logout Logic
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

// Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle Filtering
$selectedType = isset($_GET['type']) ? $_GET['type'] : 'All';
$whereClause = "WHERE status = 'Available'";

if ($selectedType !== 'All') {
    $safeType = mysqli_real_escape_string($conn, $selectedType);
    $whereClause .= " AND type = '$safeType'";
}

$query = "SELECT * FROM bikes $whereClause ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$filteredBikes = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $filteredBikes[] = $row;
    }
}

// Fetch User Profile Image if logged in
$profile_image = null;
$unread_notif_count = 0;
if (isset($_SESSION['userid'])) {
    $uid = (int)$_SESSION['userid'];
    $u_res = mysqli_query($conn, "SELECT profile_image FROM customers WHERE userid=$uid");
    if ($u_res && $u_row = mysqli_fetch_assoc($u_res)) {
        $profile_image = $u_row['profile_image'];
    }

    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $uid AND user_type = 'customer' AND is_read = 0";
    $unread_notif_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #005461;
            --secondary: #018790;
            --accent: #00B7B5;
            --bg: #F4F4F4;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .hero-gradient { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .bike-card { transition: all 0.3s ease; }
        .bike-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0, 84, 97, 0.2); }
    </style>
</head>
<body class="text-gray-900">

    <!-- Navigation -->
    <nav class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="text-xl font-bold text-primary tracking-tight uppercase">Mati<span class="text-accent">Rentals</span></span>
                </div>
                
                <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-wider text-primary items-center">
                    <a href="index.php" class="hover:text-accent transition">Home</a>
                    <?php if(isset($_SESSION['userid'])): ?>
                        <a href="mybooks.php" class="hover:text-accent transition">My Bookings</a>
                    <?php endif; ?>
                    <a href="contact.php" class="hover:text-accent transition">Contact</a>
                </div>

                <div class="flex items-center gap-4">
                    <?php if(isset($_SESSION['userid'])): ?>
                        <a href="notifications.php" class="relative text-slate-400 hover:text-primary transition-colors">
                            <i class="fa-regular fa-bell text-xl"></i>
                            <?php if($unread_notif_count > 0): ?>
                                <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                            <?php endif; ?>
                        </a>
                        <div class="relative">
                            <button onclick="toggleProfileMenu()" class="flex items-center gap-3 focus:outline-none">
                                <span class="hidden md:block text-sm font-bold text-gray-600">Hi, <?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold shadow-md border-2 border-white transition hover:scale-105 overflow-hidden">
                                    <?php if (!empty($profile_image)): ?>
                                        <img src="<?php echo htmlspecialchars($profile_image); ?>?v=<?php echo time(); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['fullname'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 transform origin-top-right transition-all">
                                <div class="px-4 py-3 border-b border-gray-50 mb-1">
                                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Account</p>
                                    <p class="text-sm font-bold text-primary truncate"><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                                </div>
                                <a href="profile.php" class="block px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-primary font-medium transition flex items-center gap-2"><i class="fa-solid fa-user-gear text-gray-400"></i> Profile & Setting</a>
                                <a href="?logout=true" class="block px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 font-medium transition flex items-center gap-2"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="bg-primary text-white px-5 py-2 rounded-full text-sm font-bold shadow-md hover:bg-secondary transition">Login</a>
                        <a href="register.php" class="hidden md:block text-primary font-bold text-sm hover:text-secondary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient text-white py-20 px-4 relative overflow-hidden">
        <div class="max-w-7xl mx-auto text-center relative z-10">
            <span class="inline-block py-1 px-3 rounded-full bg-white/20 text-xs font-bold uppercase mb-4 tracking-widest">Available in Mati City</span>
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">Explore Mati <br/> on Two Wheels</h1>
            <p class="text-lg opacity-90 max-w-2xl mx-auto mb-10">From the curves of the Sleeping Dinosaur to the waves of Dahican Beach—experience Mati at your own pace.</p>
            
            <div class="flex flex-wrap justify-center gap-6">
                <div class="flex items-center gap-2"><span class="font-medium">Cash on Pickup</span></div>
                <div class="flex items-center gap-2"><span class="font-medium">Low Deposit</span></div>
                <div class="flex items-center gap-2"><span class="font-medium">Free Helmet</span></div>
            </div>
        </div>
    </section>

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <!-- Filter -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-6">
            <div>
                <h2 class="text-3xl font-bold text-primary">Bike Gallery</h2>
                <p class="text-gray-500 mt-1">View our high-quality fleet and pick your ride</p>
            </div>
            
            <div class="bg-white p-2 rounded-xl shadow-sm border flex items-center gap-2">
                <span class="text-xs font-bold uppercase text-gray-400 px-4">Filter:</span>
                <div class="flex gap-1">
                    <?php foreach(['All', 'Scooter', 'Underbone'] as $type): ?>
                        <a href="?type=<?php echo $type; ?>" class="px-5 py-2 rounded-lg text-sm font-bold transition-all <?php echo $selectedType === $type ? 'bg-primary text-white shadow-md' : 'text-gray-500 hover:bg-gray-100'; ?>">
                            <?php echo $type; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if(empty($filteredBikes)): ?>
                <div class="col-span-full py-20 text-center text-gray-400 italic">No bikes found.</div>
            <?php else: ?>
                <?php foreach($filteredBikes as $bike): ?>
                <div class="bike-card bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 flex flex-col">
                    <div class="relative h-64">
                        <a href="book.php?id=<?php echo $bike['id']; ?>" class="block w-full h-full">
                            <?php $imgSrc = !empty($bike['image_url']) ? htmlspecialchars($bike['image_url']) : 'https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=800'; ?>
                            <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($bike['model_name']); ?>" class="w-full h-full object-cover">
                            <div class="absolute top-4 left-4 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest text-primary shadow-sm">
                                <?php echo htmlspecialchars($bike['type'] ?? 'Scooter'); ?>
                            </div>
                            <div class="absolute bottom-4 right-4 bg-primary text-white px-3 py-1 rounded-lg text-sm font-bold shadow-lg">
                                ₱<?php echo number_format($bike['daily_rate'], 0); ?> / Day
                            </div>
                        </a>
                    </div>
                    
                    <div class="p-6 flex flex-col flex-1">
                        <div class="mb-2">
                            <h3 class="text-xl font-bold text-primary"><?php echo htmlspecialchars($bike['model_name']); ?></h3>
                            <div class="flex items-center gap-2 mt-1 text-xs font-medium text-gray-400 uppercase tracking-wide">
                                <span><?php echo $bike['fuel_level']; ?>% Fuel</span> • <span>Available Now</span>
                            </div>
                        </div>

                        <p class="text-gray-500 text-sm leading-relaxed mb-6 mt-2 line-clamp-3">
                            Well-maintained <?php echo strtolower($bike['type'] ?? 'vehicle'); ?> ready for your adventure in Mati City. Book now for a hassle-free ride.
                        </p>

                        <div class="mt-auto pt-4 border-t border-gray-50 flex items-center justify-between">
                            <div class="flex gap-2">
                                <span title="Free Helmet" class="p-2 rounded-lg bg-gray-50 text-secondary border border-gray-100">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path></svg>
                                </span>
                            </div>
                            <a href="book.php?id=<?php echo $bike['id']; ?>" class="px-6 py-2.5 rounded-xl bg-secondary hover:bg-primary text-white font-bold transition-all shadow-md active:scale-95 text-sm">
                                <?php echo isset($_SESSION['userid']) ? 'Book Ride' : 'View Details'; ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white py-12 px-4 border-t mt-12">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8 text-center md:text-left">
            <div>
                <div class="flex items-center gap-2 justify-center md:justify-start mb-4">
                    <div class="w-6 h-6 rounded bg-primary flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="font-bold text-primary uppercase tracking-tight">Mati Rentals</span>
                </div>
                <p class="text-gray-400 text-sm">© 2024 Mati City Motorcycle Rental Services.</p>
            </div>
            <div class="flex gap-4">
                <a href="#" class="w-10 h-10 rounded-full border flex items-center justify-center hover:bg-primary hover:text-white transition text-gray-400">FB</a>
                <a href="#" class="w-10 h-10 rounded-full border flex items-center justify-center hover:bg-primary hover:text-white transition text-gray-400">IG</a>
            </div>
        </div>
    </footer>

    <script>
        function toggleProfileMenu() {
            const menu = document.getElementById('profileDropdown');
            menu.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const menu = document.getElementById('profileDropdown');
            const button = document.querySelector('button[onclick="toggleProfileMenu()"]');
            if (!menu.contains(e.target) && !button.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
<?php
session_start();

// 1. Check login status
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// 2. Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$userid = (int)$_SESSION['userid'];
$user_role = $_SESSION['role'];

// 3. Mark all unread notifications for this user as read
mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $userid AND user_type = '$user_role' AND is_read = 0");

// 4. Fetch user data for navigation
$user_query = "SELECT fullname, profile_image FROM customers WHERE userid = $userid";
$user_res = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_res);

// 5. Fetch all bookings for the logged-in customer
$notifications_query = "SELECT * FROM notifications WHERE user_id = $userid AND user_type = '$user_role' ORDER BY created_at DESC";
$notifications_res = mysqli_query($conn, $notifications_query);
$notifications = [];
if ($notifications_res) {
    while ($row = mysqli_fetch_assoc($notifications_res)) {
        $notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    </style>
</head>
<body class="text-gray-900">

    <!-- Navigation -->
    <nav class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2 cursor-pointer" onclick="window.location.href='index.php'">
                    <div class="w-8 h-8 rounded bg-primary flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="text-xl font-bold text-primary tracking-tight uppercase">Mati<span class="text-accent">Rentals</span></span>
                </div>
                <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-wider text-primary">
                    <a href="index.php" class="hover:text-accent transition">Home</a>
                    <a href="mybooks.php" class="hover:text-accent transition">My Bookings</a>
                    <a href="contact.php" class="hover:text-accent transition">Contact</a>
                </div>
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <button onclick="toggleProfileMenu()" class="flex items-center gap-3 focus:outline-none">
                            <span class="hidden md:block text-sm font-bold text-gray-600">Hi, <?php echo htmlspecialchars($user['fullname']); ?></span>
                            <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold shadow-md border-2 border-white transition hover:scale-105 overflow-hidden">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                            <div class="px-4 py-3 border-b border-gray-50 mb-1">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Account</p>
                                <p class="text-sm font-bold text-primary truncate"><?php echo htmlspecialchars($user['fullname']); ?></p>
                            </div>
                            <a href="profile.php" class="block px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-primary font-medium transition flex items-center gap-2"><i class="fa-solid fa-user-gear text-gray-400"></i> Profile & Setting</a>
                            <a href="index.php?logout=true" class="block px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 font-medium transition flex items-center gap-2"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="notificationHistoryView">
        <div class="max-w-3xl mx-auto px-4 py-12">
            <div class="mb-10">
                <h2 class="text-3xl font-bold text-primary">Notification History</h2>
                <p class="text-gray-500">A complete log of all your account updates and alerts.</p>
            </div>

            <div class="space-y-4">
                <?php if (empty($notifications)): ?>
                    <div class="p-12 text-center bg-white rounded-3xl border border-dashed border-gray-200 text-gray-400">
                        <i class="fa-regular fa-bell-slash text-4xl mb-4 text-gray-300"></i>
                        <p class="font-medium">You have no notifications yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): 
                        $icon = 'fa-info-circle';
                        $icon_color = 'text-blue-500 bg-blue-50';
                        if (str_contains(strtolower($notif['message']), 'approved') || str_contains(strtolower($notif['message']), 'verified')) {
                            $icon = 'fa-check-circle';
                            $icon_color = 'text-green-500 bg-green-50';
                        } elseif (str_contains(strtolower($notif['message']), 'damage')) {
                            $icon = 'fa-triangle-exclamation';
                            $icon_color = 'text-red-500 bg-red-50';
                        }
                    ?>
                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="block bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-md hover:border-gray-200 transition-all">
                        <div class="flex gap-4 items-start">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 <?php echo $icon_color; ?>">
                                <i class="fa-solid <?php echo $icon; ?>"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($notif['message']); ?></p>
                                <p class="text-xs text-gray-400 font-bold mt-1"><?php echo date('F j, Y - h:i A', strtotime($notif['created_at'])); ?></p>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleProfileMenu() {
            const menu = document.getElementById('profileDropdown');
            menu.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const menu = document.getElementById('profileDropdown');
            const button = document.querySelector('button[onclick="toggleProfileMenu()"]');
            if (menu && button && !menu.contains(e.target) && !button.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>

</body>
</html>

```

### 2. Update Navigation Link

I've updated the bell icon in the main navigation to link to the new `notifications.php` page.

```diff
--- a/c/xampp/htdocs/booking/index.php
+++ b/c/xampp/htdocs/booking/index.php
</body>
</html>