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
$user_status = null;
$is_verified = 0;
if (isset($_SESSION['userid'])) {
    $uid = (int)$_SESSION['userid'];
    $u_res = mysqli_query($conn, "SELECT profile_image, status, is_verified FROM customers WHERE userid=$uid");
    if ($u_res && $u_row = mysqli_fetch_assoc($u_res)) {
        $profile_image = $u_row['profile_image'];
        $user_status = $u_row['status'];
        $is_verified = (int)$u_row['is_verified'];
    }

    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $uid AND user_type = 'customer' AND is_read = 0";
    $unread_notif_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'];

    // Fetch actual notifications
    $cust_notif_query = "SELECT * FROM notifications WHERE user_id = $uid AND user_type = 'customer' ORDER BY created_at DESC LIMIT 5";
    $cust_notif_res = mysqli_query($conn, $cust_notif_query);
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

        .notification-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; border: 2px solid white; }
        .notification-dropdown { display: none; position: absolute; right: 0; top: 120%; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; z-index: 100; }
        .notification-item { display:block; padding: 12px 15px; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem; color: #1f2937; text-decoration:none; }
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
                    <span class="text-xl font-bold text-primary tracking-tight">MatiMotoRental</span>
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
                        <div class="relative">
                            <button onclick="toggleNotif('custNotif')" class="relative text-slate-400 hover:text-primary transition-colors">
                                <i class="fa-regular fa-bell text-xl"></i>
                                <?php if($unread_notif_count > 0): ?><span class="notification-dot"></span><?php endif; ?>
                            </button>
                            <div id="custNotif" class="notification-dropdown">
                                <div class="p-3 font-bold border-b border-gray-100">Notifications</div>
                                <?php if(isset($cust_notif_res) && mysqli_num_rows($cust_notif_res) > 0): ?>
                                    <?php while($notif = mysqli_fetch_assoc($cust_notif_res)): ?>
                                        <a href="<?= $notif['link'] ?>" class="notification-item <?= !$notif['is_read'] ? 'bg-slate-50' : '' ?>">
                                            <p class="m-0"><?= $notif['message'] ?></p>
                                            <small class="text-slate-400"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
                                        </a>
                                    <?php endwhile; ?>
                                    <a href="notifications.php" class="block text-center p-2 text-xs font-bold text-primary hover:bg-gray-50">View All</a>
                                <?php else: ?>
                                    <p class="text-center p-5 text-slate-400 text-sm">No notifications yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
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
        
        <?php if(isset($_SESSION['userid']) && $is_verified != 1): ?>
        <div class="bg-gradient-to-r from-amber-400 via-amber-200 to-yellow-100 border-l-8 border-amber-600 p-6 mb-10 rounded-2xl flex items-center gap-5 shadow-lg animate-pulse-slow">
            <div class="text-amber-600 flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
            </div>
            <div>
                <p class="font-extrabold text-amber-900 text-lg mb-1 tracking-wide uppercase">Account Verification Pending</p>
                <p class="text-amber-800 text-sm font-medium mb-2">Your account is not yet verified. Please upload the required documents and update your profile to continue using all features.</p>
                <div class="flex flex-wrap gap-2 mt-2">
                    <a href="profile.php" class="inline-block px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg shadow transition-all text-xs uppercase tracking-wider">Update Profile &amp; Settings</a>
                    <span class="inline-block px-3 py-2 bg-white/80 text-amber-700 font-semibold rounded-lg text-xs">Wait for admin approval after uploading</span>
                </div>
            </div>
        </div>
        <style>
        @keyframes pulse-slow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(251, 191, 36, 0.15); }
        }
        .animate-pulse-slow { animation: pulse-slow 2.5s infinite; }
        </style>
        <?php endif; ?>

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
                            <?php if (isset($_SESSION['userid']) && $is_verified != 1): ?>
                                <button type="button" onclick="alert('Your account is pending verification. Please wait for an admin to approve it.')" class="px-6 py-2.5 rounded-xl bg-gray-300 text-gray-600 font-bold cursor-not-allowed shadow-sm text-sm">
                                    Verification Pending
                                </button>
                            <?php else: ?>
                                <a href="book.php?id=<?php echo $bike['id']; ?>" class="px-6 py-2.5 rounded-xl bg-secondary hover:bg-primary text-white font-bold transition-all shadow-md active:scale-95 text-sm">
                                    <?php echo isset($_SESSION['userid']) ? 'Book Ride' : 'View Details'; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white py-12 px-4 border-t mt-12">
        <div class="max-w-7xl mx-auto flex flex-col items-center gap-2 text-center">
            <div>
                <div class="flex items-center gap-2 justify-center mb-4">
                    <div class="w-6 h-6 rounded bg-primary flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <span class="font-bold text-primary uppercase tracking-tight">Mati Rentals</span>
                </div>
                <p class="text-gray-400 text-sm">© 2024 Mati City Motorcycle Rental Services.</p>
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

        function toggleNotif(id) {
            const dropdown = document.getElementById(id);
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';

            if (!isVisible) {
                fetch('mark_as_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'user_type=customer'
                }).then(() => {
                    const dot = dropdown.previousElementSibling.querySelector('.notification-dot');
                    if(dot) dot.style.display = 'none';
                });
            }
        };
    </script>
</body>
</html>