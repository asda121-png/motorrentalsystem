<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

// Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$userid = $_SESSION['userid'];
$query = "SELECT * FROM customers WHERE userid = $userid";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    // Handle case where user might be deleted but session exists
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch notifications
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $userid AND user_type = 'customer' AND is_read = 0";
$unread_notif_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'];

$cust_notif_query = "SELECT * FROM notifications WHERE user_id = $userid AND user_type = 'customer' ORDER BY created_at DESC LIMIT 5";
$cust_notif_res = mysqli_query($conn, $cust_notif_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mati City Moto Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
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

        .notification-dot { position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid white; }
        .notification-dropdown { display: none; position: absolute; right: 0; top: 120%; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; z-index: 100; }
        .notification-item { display:block; padding: 12px 15px; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem; color: #1f2937; text-decoration:none; }
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
                
                <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-wider text-primary items-center">
                    <a href="index.php" class="hover:text-accent transition">Home</a>
                    <a href="mybooks.php" class="hover:text-accent transition">My Bookings</a>
                    <a href="contact.php" class="hover:text-accent transition ">Contact</a>
                </div>

                <div class="flex items-center gap-4">
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
    <div class="max-w-3xl mx-auto px-4 py-12">
        <div class="bg-gradient-to-br from-[#f0f4f8] via-[#e6f7fa] to-[#f8fafc] rounded-[2.5rem] p-10 shadow-xl border-2 border-primary/10">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-black text-primary tracking-tight">My Profile</h1>
            </div>

            <div class="flex flex-col md:flex-row gap-10">
                <!-- Avatar Section -->
                <div class="flex flex-col items-center gap-4">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" class="w-32 h-32 rounded-full object-cover shadow-xl shadow-primary/20 border-4 border-white">
                    <?php else: ?>
                        <div class="w-32 h-32 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-5xl font-black text-white shadow-xl shadow-primary/20">
                            <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-center">
                        <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Member Since</div>
                        <div class="font-bold text-gray-700"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>

                <!-- Details Section -->
                <div class="flex-1 space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                            <div class="w-full px-6 py-4 rounded-2xl bg-white/90 border-l-4 border-primary/30 shadow text-gray-700 font-bold">
                                <?php echo htmlspecialchars($user['fullname']); ?>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                            <div class="w-full px-6 py-4 rounded-2xl bg-white/90 border-l-4 border-primary/30 shadow text-gray-700 font-bold flex items-center justify-between">
                                <span><?php echo htmlspecialchars($user['email']); ?></span>
                                <i class="fa-solid fa-lock text-gray-300"></i>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Phone Number</label>
                            <div class="w-full px-6 py-4 rounded-2xl bg-white/90 border-l-4 border-primary/30 shadow text-gray-700 font-bold">
                                <?php echo htmlspecialchars($user['phone_number'] ?? 'Not set'); ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Driver's License</label>
                                <div class="h-40 rounded-2xl bg-gradient-to-br from-[#f0f4f8] to-[#e6f7fa] border-2 border-primary/10 overflow-hidden flex items-center justify-center relative group">
                                    <?php if(!empty($user['drivers_license_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['drivers_license_image']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="text-center p-4"><i class="fa-solid fa-id-card text-3xl text-gray-300 mb-2 block"></i><span class="text-gray-400 text-xs italic">No license uploaded</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Valid ID</label>
                                <div class="h-40 rounded-2xl bg-gradient-to-br from-[#f0f4f8] to-[#e6f7fa] border-2 border-primary/10 overflow-hidden flex items-center justify-center relative group">
                                    <?php if(!empty($user['valid_id_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['valid_id_image']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="text-center p-4"><i class="fa-solid fa-address-card text-3xl text-gray-300 mb-2 block"></i><span class="text-gray-400 text-xs italic">No ID uploaded</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-50">
                        <div class="flex justify-end">
                            <a href="edit_profile.php" class="px-6 py-3 rounded-xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:bg-secondary transition-all text-sm">Edit Profile</a>
                        </div>
                    </div>
                </div>
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
        }
        window.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown') && !e.target.closest('button[onclick^="toggleNotif"]')) {
                document.querySelectorAll('.notification-dropdown').forEach(d => d.style.display = 'none');
            }
        });
    </script>
</body>
</html>