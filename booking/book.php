<?php
session_start();

// Database Connection
$conn = mysqli_connect('localhost', 'root', '', 'moto_rental_db');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$bike_id = (int)$_GET['id'];
$query = "SELECT * FROM bikes WHERE id = $bike_id";
$result = mysqli_query($conn, $query);
$bike = mysqli_fetch_assoc($result);

if (!$bike) {
    header("Location: index.php");
    exit();
}

// Fetch reviews
$reviews_query = "SELECT r.rating, r.feedback, r.created_at, c.fullname, c.profile_image 
                  FROM rentals r 
                  JOIN customers c ON r.customer_id = c.userid 
                  WHERE r.bike_id = $bike_id AND r.feedback IS NOT NULL 
                  ORDER BY r.created_at DESC";
$reviews_res = mysqli_query($conn, $reviews_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mati City Moto Rentals</title>
    <!-- Tailwind CSS for layout utility -->
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
            scroll-behavior: smooth;
        }
        .bg-primary { background-color: var(--primary); }
        .bg-secondary { background-color: var(--secondary); }
        .bg-accent { background-color: var(--accent); }
        .text-primary { color: var(--primary); }
        .text-secondary { color: var(--secondary); }
        .border-primary { border-color: var(--primary); }
        
        .hero-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        
        .bike-card {
            transition: all 0.3s ease;
        }
        .bike-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 84, 97, 0.2);
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--accent) !important;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 183, 181, 0.1);
        }
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
                    <?php if(isset($_SESSION['userid'])): ?>
                        <a href="mybooks.php" class="hover:text-accent transition">My Bookings</a>
                    <?php endif; ?>
                    <a href="#" class="hover:text-accent transition">Contact</a>
                </div>
                <div>
                    <?php if(isset($_SESSION['userid'])): ?>
                        <a href="index.php?logout=true" class="bg-red-50 text-red-600 border border-red-100 px-5 py-2 rounded-full text-sm font-bold hover:bg-red-100 transition">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="bg-primary text-white px-5 py-2 rounded-full text-sm font-bold shadow-md hover:bg-secondary transition">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT: BIKE DETAILS -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        
        <!-- Breadcrumb / Back -->
        <div class="mb-8">
            <a href="index.php" class="inline-flex items-center gap-2 text-sm font-bold text-gray-400 hover:text-primary transition">
                <i class="fa-solid fa-arrow-left"></i> Back to Fleet
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
            
            <!-- LEFT COLUMN: IMAGES & SPECS -->
            <div>
                <div class="relative rounded-3xl overflow-hidden shadow-lg border border-gray-100 bg-white mb-8 group">
                    <img src="<?php echo !empty($bike['image_url']) ? htmlspecialchars($bike['image_url']) : 'https://images.unsplash.com/photo-1558981403-c5f91cbba527?auto=format&fit=crop&q=80&w=800'; ?>" 
                         class="w-full h-[400px] object-cover transition-transform duration-700 group-hover:scale-105" alt="Motorcycle">
                    <div class="absolute top-6 left-6">
                        <span class="bg-white/90 backdrop-blur px-4 py-2 rounded-full text-xs font-black uppercase tracking-widest text-primary shadow-sm">
                            <?php echo htmlspecialchars($bike['type'] ?? 'Scooter'); ?>
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-lg font-bold text-primary mb-6">Vehicle Specifications</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-gas-pump"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase text-gray-400">Fuel Level</div>
                                <div class="font-bold text-gray-700"><?php echo $bike['fuel_level']; ?>%</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-gears"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase text-gray-400">Transmission</div>
                                <div class="font-bold text-gray-700"><?php echo htmlspecialchars($bike['transmission'] ?? 'Automatic'); ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-id-card"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase text-gray-400">Plate Number</div>
                                <div class="font-bold text-gray-700">Ending in <?php echo substr($bike['plate_number'], -3); ?></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400"><i class="fa-solid fa-helmet-safety"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase text-gray-400">Inclusions</div>
                                <div class="font-bold text-gray-700"><?php echo htmlspecialchars($bike['inclusions'] ?? 'Helmet'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: DETAILS & BOOKING ACTION -->
            <div class="lg:sticky lg:top-24">
                
                <!-- Title & Price Header -->
                <div class="mb-8">
                    <h1 class="text-4xl font-black text-slate-800 mb-2"><?php echo htmlspecialchars($bike['model_name']); ?></h1>
                    <div class="flex items-center gap-2 text-sm font-medium text-gray-500 mb-6">
                        <i class="fa-solid fa-location-dot text-accent"></i> Available in Mati City
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-5xl font-black text-primary tracking-tight">₱<?php echo number_format($bike['daily_rate'], 0); ?></span>
                        <span class="text-lg font-bold text-gray-400 mb-2">/ day</span>
                    </div>
                </div>

                <!-- Description -->
                <div class="prose prose-slate text-gray-500 leading-relaxed mb-8">
                    <p>
                        <?php echo !empty($bike['description']) ? htmlspecialchars($bike['description']) : "Experience the freedom of Mati City with this well-maintained <strong>" . htmlspecialchars($bike['model_name']) . "</strong>. Perfect for both city cruising and trips to Dahican Beach."; ?>
                    </p>
                </div>

                <!-- Action Area -->
                <div id="initialAction" class="bg-slate-50 rounded-3xl p-8 border border-slate-100 text-center">
                    <?php if(isset($_SESSION['userid'])): ?>
                        <button onclick="showBookingForm()" class="w-full py-4 rounded-2xl bg-primary text-white font-bold text-lg shadow-xl shadow-primary/20 hover:bg-secondary transition-all active:scale-95 flex items-center justify-center gap-2">
                            <span>Book This Ride</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <p class="text-xs text-gray-400 mt-4 font-medium">No credit card required. Pay cash on pickup.</p>
                    <?php else: ?>
                        <a href="login.php" class="block w-full py-4 rounded-2xl bg-slate-800 text-white font-bold text-lg shadow-xl hover:bg-slate-700 transition-all">
                            Login to Book
                        </a>
                        <p class="text-xs text-gray-400 mt-4 font-medium">You need an account to make a reservation.</p>
                    <?php endif; ?>
                </div>

                <!-- HIDDEN BOOKING FORM -->
                <div id="bookingFormContainer" class="hidden bg-white rounded-3xl p-8 shadow-xl border border-gray-100 ring-4 ring-slate-50">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-primary">Complete Reservation</h3>
                        <button onclick="hideBookingForm()" class="text-gray-400 hover:text-red-500 transition"><i class="fa-solid fa-xmark text-xl"></i></button>
                    </div>

                    <form id="rentalForm" onsubmit="handleFinalBooking(event)" class="space-y-5">
                        <!-- Hidden Bike ID for Backend -->
                        <input type="hidden" name="bike_id" value="<?php echo $bike['id']; ?>">
                        
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400">Full Name</label>
                            <input type="text" name="fullname" required placeholder="John Doe" value="<?php echo isset($_SESSION['fullname']) ? htmlspecialchars($_SESSION['fullname']) : ''; ?>" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-bold text-gray-700">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase text-gray-400">Phone Number</label>
                            <input type="tel" name="phone" required placeholder="09XX XXX XXXX" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-bold text-gray-700">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase text-gray-400">Pickup Date</label>
                                <input type="datetime-local" id="pickupDate" name="pickupDate" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-xs font-bold text-gray-700">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase text-gray-400">Return Date</label>
                                <input type="datetime-local" id="returnDate" name="returnDate" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-xs font-bold text-gray-700">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-50">
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-sm font-bold text-gray-500">Total Estimate</span>
                                <span class="text-2xl font-black text-primary" id="sumTotal">₱<?php echo number_format($bike['daily_rate'], 0); ?></span>
                            </div>
                            <button type="submit" class="w-full py-4 rounded-2xl bg-secondary hover:bg-primary text-white font-bold text-lg shadow-xl transition-all active:scale-95">
                                Confirm Reservation
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- REVIEWS SECTION -->
        <div class="mt-16 border-t border-gray-100 pt-12">
            <h3 class="text-2xl font-black text-slate-800 mb-8">Customer Reviews</h3>
            
            <?php if (mysqli_num_rows($reviews_res) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php while($review = mysqli_fetch_assoc($reviews_res)): ?>
                        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 overflow-hidden flex items-center justify-center">
                                        <?php if(!empty($review['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($review['profile_image']); ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="font-bold text-slate-400 text-xs"><?php echo strtoupper(substr($review['fullname'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($review['fullname']); ?></div>
                                        <div class="text-xs text-slate-400"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                    </div>
                                </div>
                                <div class="flex text-yellow-400 text-xs">
                                    <?php for($i=0; $i<$review['rating']; $i++) echo '<i class="fa-solid fa-star"></i>'; ?>
                                    <?php for($i=$review['rating']; $i<5; $i++) echo '<i class="fa-regular fa-star text-slate-200"></i>'; ?>
                                </div>
                            </div>
                            <p class="text-slate-600 text-sm leading-relaxed">"<?php echo htmlspecialchars($review['feedback']); ?>"</p>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-12 bg-slate-50 rounded-3xl border border-dashed border-slate-200">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm text-slate-300 text-2xl">
                        <i class="fa-regular fa-comment-dots"></i>
                    </div>
                    <p class="text-slate-400 font-medium">No reviews yet. Be the first to rent this bike!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Success Feedback Overlay (Hidden by default) -->
    <div id="successView" class="hidden fixed inset-0 z-[200] bg-white flex items-center justify-center p-4">
        <div class="text-center max-w-sm">
            <div class="w-20 h-20 bg-accent rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl shadow-accent/20">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="text-3xl font-extrabold text-primary mb-2">Reservation Sent!</h2>
            <p class="text-gray-500 mb-8 leading-relaxed">Thank you for choosing Mati Rentals. Our dispatcher will contact you within 15 minutes to confirm pickup.</p>
            <button onclick="window.location.href='index.php'" class="w-full py-4 rounded-2xl bg-primary text-white font-bold shadow-lg">Return to Fleet</button>
        </div>
    </div>

    <script>
        const bikePrice = <?php echo $bike['daily_rate']; ?>;
        
        function showBookingForm() {
            document.getElementById('initialAction').classList.add('hidden');
            document.getElementById('bookingFormContainer').classList.remove('hidden');
        }

        function hideBookingForm() {
            document.getElementById('bookingFormContainer').classList.add('hidden');
            document.getElementById('initialAction').classList.remove('hidden');
        }

        function handleFinalBooking(e) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.textContent;
            
            btn.textContent = 'Processing Reservation...';
            btn.disabled = true;

            const formData = new FormData(e.target);
            
            fetch('process_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('successView').classList.remove('hidden');
                    e.target.reset();
                } else {
                    alert('Booking Failed: ' + data.message);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.textContent = originalText;
                btn.disabled = false;
            });
        }

        // Simple calculation logic
        const pickupInput = document.getElementById('pickupDate');
        const returnInput = document.getElementById('returnDate');
        const sumTotal = document.getElementById('sumTotal');

        function updatePrice() {
            if (!pickupInput.value || !returnInput.value) return;
            
            const start = new Date(pickupInput.value);
            const end = new Date(returnInput.value);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            const total = (diffDays > 0 ? diffDays : 1) * bikePrice;
            sumTotal.textContent = '₱' + total;
        }

        pickupInput.addEventListener('change', updatePrice);
        returnInput.addEventListener('change', updatePrice);
    </script>

</body>
</html>