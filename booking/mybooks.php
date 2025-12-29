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

// Ensure feedback columns exist (Schema Fix)
$cols_check = mysqli_query($conn, "SHOW COLUMNS FROM rentals LIKE 'rating'");
if (mysqli_num_rows($cols_check) == 0) {
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN rating INT DEFAULT NULL AFTER repair_cost");
    mysqli_query($conn, "ALTER TABLE rentals ADD COLUMN feedback TEXT DEFAULT NULL AFTER rating");
}

$userid = (int)$_SESSION['userid'];

// --- HANDLE FEEDBACK SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rental_id = (int)$_POST['rental_id'];
    $rating = (int)$_POST['rating'];
    $feedback = mysqli_real_escape_string($conn, $_POST['feedback']);
    
    // Verify rental belongs to user and is completed
    $check = mysqli_query($conn, "SELECT id FROM rentals WHERE id=$rental_id AND customer_id=$userid AND status='Completed'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE rentals SET rating=$rating, feedback='$feedback' WHERE id=$rental_id");
        $msg = "Feedback submitted successfully!";
    }
}

// 3. Fetch user data for navigation
$user_query = "SELECT fullname, profile_image FROM customers WHERE userid = $userid";
$user_res = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_res);

// Fetch notifications
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = $userid AND user_type = 'customer' AND is_read = 0";
$unread_notif_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_query))['count'];

$cust_notif_query = "SELECT * FROM notifications WHERE user_id = $userid AND user_type = 'customer' ORDER BY created_at DESC LIMIT 5";
$cust_notif_res = mysqli_query($conn, $cust_notif_query);

// 4. Fetch bookings for the logged-in customer
$bookings_query = "SELECT r.*, b.model_name, b.image_url 
                   FROM rentals r 
                   JOIN bikes b ON r.bike_id = b.id 
                   WHERE r.customer_id = $userid 
                   ORDER BY r.rental_start_date DESC";
$bookings_res = mysqli_query($conn, $bookings_query);
$bookings = [];
if ($bookings_res) {
    while ($row = mysqli_fetch_assoc($bookings_res)) {
        $bookings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Mati City Moto Rentals</title>
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
                    <span class="text-xl font-bold text-primary tracking-tight">MatiMotoRental</span>
                </div>
                <div class="hidden md:flex gap-8 text-sm font-semibold uppercase tracking-wider text-primary">
                    <a href="index.php" class="hover:text-accent transition">Home</a>
                    <a href="mybooks.php" class="hover:text-accent transition text-accent">My Bookings</a>
                    <a href="contact.php" class="hover:text-accent transition">Contact</a>
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
    <div id="myBookingsView">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="mb-10">
                <h2 class="text-3xl font-bold text-primary">My Bookings</h2>
                <p class="text-gray-500">Manage your active and completed rentals in Mati City</p>
            </div>

            <?php if (isset($msg)): ?>
                <div class="mb-8 p-4 bg-emerald-50 text-emerald-700 rounded-2xl border border-emerald-100 flex items-center gap-3">
                    <i class="fa-solid fa-circle-check"></i> <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <div class="space-y-8">
                <?php if (empty($bookings)): ?>
                    <div class="p-12 text-center bg-white rounded-3xl border border-dashed border-gray-200 text-gray-400">
                        <i class="fa-solid fa-folder-open text-4xl mb-4 text-gray-300"></i>
                        <p class="font-medium">You have no bookings yet.</p>
                        <a href="index.php" class="mt-4 inline-block px-6 py-2.5 rounded-xl bg-secondary text-white font-bold text-sm">Explore Fleet</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): 
                        $status_color = match(strtolower($booking['status'])) {
                            'pending' => 'bg-amber-50 text-amber-600 border-amber-100',
                            'active' => 'bg-blue-50 text-blue-600 border-blue-100',
                            'completed' => 'bg-gray-100 text-gray-500 border-gray-200',
                            default => 'bg-red-50 text-red-600 border-red-100'
                        };
                        $imgSrc = !empty($booking['image_url']) ? htmlspecialchars($booking['image_url']) : 'https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=800';
                    ?>
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex flex-col md:flex-row gap-6 items-center">
                        <img src="<?php echo $imgSrc; ?>" class="w-full md:w-32 h-32 rounded-2xl object-cover">
                        <div class="flex-1 text-center md:text-left">
                            <div class="flex flex-col md:flex-row md:items-center gap-2 mb-2">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">#RNT-<?php echo $booking['id']; ?></span>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase border <?php echo $status_color; ?> w-max mx-auto md:mx-0">
                                    <?php echo htmlspecialchars($booking['status']); ?>
                                </span>
                            </div>
                            <h4 class="text-xl font-bold text-primary mb-1"><?php echo htmlspecialchars($booking['model_name']); ?></h4>
                            <p class="text-sm text-gray-500">
                                <span class="font-semibold text-primary">Pickup:</span> <?php echo date('M d, Y - h:i A', strtotime($booking['rental_start_date'])); ?> <br/>
                                <span class="font-semibold text-primary">Return:</span> <?php echo date('M d, Y', strtotime($booking['expected_return_date'])); ?>
                            </p>
                        </div>
                        <div class="text-center md:text-right border-t md:border-t-0 md:border-l border-gray-50 pt-4 md:pt-0 md:pl-8">
                            <p class="text-xs font-bold uppercase text-gray-400 mb-1">Total Amount</p>
                            <p class="text-2xl font-extrabold text-secondary">₱<?php echo number_format($booking['amount_collected'], 0); ?></p>
                            <p class="text-[10px] text-accent font-bold mt-1 uppercase">Cash on Pickup</p>
                            
                            <?php if (strtolower($booking['status']) == 'completed'): ?>
                                <?php if (empty($booking['rating'])): ?>
                                    <button onclick="openFeedbackModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['model_name']); ?>')" class="mt-4 w-full py-2 bg-primary text-white text-xs font-bold rounded-xl shadow-md hover:bg-secondary transition">Leave Feedback</button>
                                <?php else: ?>
                                    <div class="mt-4 text-xs font-bold text-emerald-600 flex items-center justify-center md:justify-end gap-1 bg-emerald-50 py-2 px-3 rounded-xl">
                                        <i class="fa-solid fa-star text-yellow-400"></i> <?php echo $booking['rating']; ?>/5 Rated
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FEEDBACK MODAL -->
    <div id="feedbackModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <form action="mybooks.php" method="POST" class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="mb-6 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100 mb-4">
                                <i class="fa-solid fa-star text-yellow-500 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-black text-slate-800">Rate Your Experience</h3>
                            <p class="text-sm text-slate-500 mt-1">How was your ride with <span id="feedbackBikeName" class="font-bold text-primary"></span>?</p>
                        </div>
                        
                        <input type="hidden" name="rental_id" id="feedbackRentalId">
                        <input type="hidden" name="submit_feedback" value="1">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-2 text-center">Select Rating</label>
                                <div class="flex justify-center gap-4 text-2xl text-slate-300">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <label class="cursor-pointer hover:text-yellow-400 transition-colors"><input type="radio" name="rating" value="<?php echo $i; ?>" class="hidden peer" required><i class="fa-solid fa-star peer-checked:text-yellow-400"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Your Review</label>
                                <textarea name="feedback" rows="3" required placeholder="Tell us about the bike condition, performance, etc..." class="w-full rounded-xl border-slate-200 text-sm font-medium text-slate-700 focus:border-primary focus:ring-primary"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-primary-hover sm:ml-3 sm:w-auto">Submit Review</button>
                        <button type="button" onclick="document.getElementById('feedbackModal').classList.add('hidden')" class="mt-3 inline-flex w-full justify-center rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Cancel</button>
                    </div>
                </form>
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

        function openFeedbackModal(id, name) {
            document.getElementById('feedbackRentalId').value = id;
            document.getElementById('feedbackBikeName').textContent = name;
            document.getElementById('feedbackModal').classList.remove('hidden');
        }
    </script>

</body>
</html>