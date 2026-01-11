<?php
session_start();
// Enable error reporting for debugging
error_reporting(E_ALL); ini_set('display_errors', 1);
require_once 'smtp_mailer.php';

// Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch User Profile Image if logged in
$profile_image = null;
$user_fullname = '';
$user_email = '';

if (isset($_SESSION['userid'])) {
    $uid = (int)$_SESSION['userid'];
    $u_res = mysqli_query($conn, "SELECT profile_image, fullname, email FROM customers WHERE userid=$uid");
    if ($u_res && $u_row = mysqli_fetch_assoc($u_res)) {
        $profile_image = $u_row['profile_image'];
        $user_fullname = $u_row['fullname'];
        $user_email = $u_row['email'];
    }

    // Fetch notifications
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $uid AND user_type = 'customer' AND is_read = 0";
    $unread_notif_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'];

    $cust_notif_query = "SELECT * FROM notifications WHERE user_id = $uid AND user_type = 'customer' ORDER BY created_at DESC LIMIT 5";
    $cust_notif_res = mysqli_query($conn, $cust_notif_query);
}

// Handle Contact Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = $_POST['name'] ?? 'Guest';
    $email = $_POST['email'] ?? 'No email provided';
    $subject = $_POST['subject'] ?? 'No Subject';
    $message = $_POST['message'] ?? '';

    $email_body = "<h3>New Message from Contact Us</h3><p><strong>Name:</strong> " . htmlspecialchars($name) . "</p><p><strong>Email:</strong> " . htmlspecialchars($email) . "</p><p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p><p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

    // Send to admin email
    // Use +contact alias to force Gmail to show it in Inbox instead of just Sent folder
    if (send_gmail('christian.labrador+contact@dorsu.edu.ph', "Contact Us: $subject", $email_body, $email)) {
        echo "<script>alert('Message sent successfully! We will get back to you shortly.'); window.location.href='contact.php';</script>";
        exit();
    } else {
        echo "<script>alert('Failed to send: " . addslashes($smtp_debug_error) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - MatiMotoRental</title>
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
                    <span class="text-xl font-bold text-primary tracking-tight">MatiMoto<span class="text-accent">Rental</span></span>
                </div>
                
                <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-wider text-primary items-center">
                    <a href="index.php" class="hover:text-accent transition">Home</a>
                    <?php if(isset($_SESSION['userid'])): ?>
                        <a href="mybooks.php" class="hover:text-accent transition">My Bookings</a>
                    <?php endif; ?>
                    <a href="contact.php" class="hover:text-accent transition text-accent">Contact</a>
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
                                <a href="index.php?logout=true" class="block px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 font-medium transition flex items-center gap-2"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
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

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="text-center mb-16">
            <h1 class="text-4xl font-black text-primary mb-4">Get in Touch</h1>
            <p class="text-gray-500 max-w-2xl mx-auto">Have questions about our fleet or need assistance with your booking? We're here to help you explore Mati City.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Info -->
            <div class="space-y-8">
                <div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-gray-100">
                    <h3 class="text-xl font-bold text-primary mb-6">Contact Information</h3>
                    
                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <i class="fa-solid fa-location-dot text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Visit Us</h4>
                                <p class="text-gray-500 text-sm mt-1">123 Coastal Road, Dahican<br>Mati City, Davao Oriental 8200</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <i class="fa-solid fa-phone text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Call Us</h4>
                                <p class="text-gray-500 text-sm mt-1">+63 912 345 6789<br>Mon-Sun, 8am - 6pm</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                <i class="fa-solid fa-envelope text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Email Us</h4>
                                <p class="text-gray-500 text-sm mt-1">support@matimotorental.com<br>bookings@matimotorental.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Maps Embed -->
                <div class="rounded-[2.5rem] h-64 w-full overflow-hidden relative shadow-md border border-gray-100">
                    <iframe src="https://maps.google.com/maps?q=6.9424876,126.2468022&z=14&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="bg-white rounded-[2.5rem] p-10 shadow-lg border border-gray-100">
                <h3 class="text-xl font-bold text-primary mb-6">Send us a Message</h3>
                <form action="contact.php" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Your Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user_fullname); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary focus:bg-white transition-all" placeholder="John Doe">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary focus:bg-white transition-all" placeholder="john@example.com">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Subject</label>
                        <input type="text" name="subject" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary focus:bg-white transition-all" placeholder="Rental Inquiry">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Message</label>
                        <textarea name="message" rows="5" class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold focus:outline-none focus:border-primary focus:bg-white transition-all" placeholder="How can we help you?"></textarea>
                    </div>

                    <button type="submit" name="send_message" class="w-full py-4 rounded-2xl bg-primary text-white font-bold shadow-lg shadow-primary/20 hover:bg-secondary transition-all active:scale-[0.98]">
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>

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
        }
        window.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-dropdown') && !e.target.closest('button[onclick^="toggleNotif"]')) {
                document.querySelectorAll('.notification-dropdown').forEach(d => d.style.display = 'none');
            }
        });
    </script>
</body>
</html>