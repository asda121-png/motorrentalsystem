<?php
/**
 * header.php - Shared Layout (Head, CSS, Sidebar, Header Bar)
 * This file handles the visual structure, navigation, and icons.
 */
if (!isset($page_title)) $page_title = "Dashboard";
if (!isset($active_nav)) $active_nav = "dashboard";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch notifications for owner
$owner_id = $_SESSION['userid'] ?? 0;
$notif_query = "SELECT * FROM notifications WHERE user_id = $owner_id AND user_type = 'owner' ORDER BY created_at DESC LIMIT 5";
$notif_res = isset($conn) ? mysqli_query($conn, $notif_query) : false;
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $owner_id AND user_type = 'owner' AND is_read = 0";
$unread_count = isset($conn) ? mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'] : 0;

// Fetch owner details for header
$owner_details_res = isset($conn) ? mysqli_query($conn, "SELECT fullname, shopname FROM owners WHERE ownerid = $owner_id") : false;
$owner_details = $owner_details_res ? mysqli_fetch_assoc($owner_details_res) : ['fullname' => 'Owner', 'shopname' => 'Shop'];

$owner_fullname = $owner_details['fullname'];
$owner_shopname = $owner_details['shopname'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoRent | <?php echo $page_title; ?></title>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0f766e', // Teal 700
                        secondary: '#0ea5e9', // Sky 500
                        accent: '#14b8a6', // Teal 500
                        surface: '#ffffff',
                        dark: '#111827',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .notification-dot { position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; border: 2px solid var(--surface); }
        .notification-dropdown { display: none; position: absolute; right: 0; top: 120%; width: 320px; background: var(--surface); border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e5e7eb; z-index: 100; }
        .notification-item { display:block; padding: 12px 15px; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem; color: #1f2937; text-decoration:none; }
        .notification-item:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex h-screen overflow-hidden">
    
    <!-- Sidebar Navigation -->
    <aside class="w-20 lg:w-72 bg-dark text-white flex flex-col transition-all duration-300 z-50 flex-shrink-0">
        <div class="h-20 flex items-center justify-center lg:justify-start lg:px-8 border-b border-white/5">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-accent flex items-center justify-center shadow-lg shadow-primary/20">
                <i class="fa-solid fa-motorcycle text-white text-lg"></i>
            </div>
            <span class="hidden lg:block ml-3 font-black text-xl tracking-tight">Moto<span class="text-accent">Rent</span></span>
        </div>

        <ul class="flex-1 py-8 space-y-2 px-3 overflow-y-auto">
            <li>
                <a href="dashboard.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'dashboard' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-gauge w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="manage_motorcycle.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'manage' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-motorcycle w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">Fleet Management</span>
                </a>
            </li>
            <li>
                <a href="rental_requests.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'requests' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-ticket w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">Requests</span>
                </a>
            </li>
            <li>
                <a href="history.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'history' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-clock-rotate-left w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">History Log</span>
                </a>
            </li>
            <li>
                <a href="income.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'income' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-chart-pie w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">Financials</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="flex items-center gap-4 px-4 py-3.5 rounded-2xl transition-all duration-200 group <?php echo $active_nav == 'profile' ? 'bg-white/10 text-white shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-user-gear w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="hidden lg:block font-semibold text-sm tracking-wide">My Profile</span>
                </a>
            </li>
        </ul>
        
        <div class="p-4 border-t border-white/5">
            <a href="../index.php?logout=true" class="flex items-center gap-4 px-4 py-3 rounded-2xl text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket w-6 text-center"></i>
                <span class="hidden lg:block font-semibold text-sm">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col h-screen overflow-hidden relative">
        <!-- Top Header Bar -->
        <header class="h-20 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-8 sticky top-0 z-40">
            <div class="text-xl font-black text-slate-800 tracking-tight"><?php echo $page_title; ?></div>
            <div class="flex items-center gap-6">
                <div class="relative">
                    <button onclick="toggleNotif('ownerNotif')" class="relative text-slate-400 hover:text-primary transition-colors">
                        <i class="fa-regular fa-bell text-xl"></i>
                        <?php if($unread_count > 0): ?><span class="notification-dot"></span><?php endif; ?>
                    </button>
                    <div id="ownerNotif" class="notification-dropdown">
                        <div class="p-3 font-bold border-b">Notifications</div>
                        <?php if($notif_res && mysqli_num_rows($notif_res) > 0): ?>
                            <?php while($notif = mysqli_fetch_assoc($notif_res)): ?>
                                <a href="<?= $notif['link'] ?>" class="notification-item <?= !$notif['is_read'] ? 'bg-slate-50' : '' ?>">
                                    <p class="m-0"><?= $notif['message'] ?></p>
                                    <small class="text-slate-400"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center p-5 text-slate-400 text-sm">No notifications yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Profile -->
                <a href="profile.php" class="flex items-center gap-3 pl-6 border-l border-slate-200 hover:bg-slate-50 p-2 rounded-xl transition-colors no-underline">
                    <div class="text-right hidden md:block">
                        <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($owner_fullname); ?></div>
                        <div class="text-xs font-medium text-slate-400"><?php echo htmlspecialchars($owner_shopname); ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </a>
            </div>
        </header>
        
        <!-- Opening of the dynamic content container -->
        <div class="flex-1 overflow-y-auto p-6 lg:p-10 scroll-smooth">

<script>
    function toggleNotif(id) {
        const dropdown = document.getElementById(id);
        const isVisible = dropdown.style.display === 'block';
        dropdown.style.display = isVisible ? 'none' : 'block';

        if (!isVisible) {
            // Mark as read via AJAX
            fetch('../mark_as_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'user_type=owner'
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