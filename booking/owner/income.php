<?php
session_start();
/**
 * income.php - Financial Reporting Page
 * Summarizes cash earnings and provides revenue breakdowns.
 */
require_once 'db.php';

// Set page identification for the header
$page_title = "Income Report";
$active_nav = "income";

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_id = $_SESSION['userid'];

// --- FETCH FINANCIAL DATA ---

// 1. Total Lifetime Earnings
$total_res = mysqli_query($conn, "SELECT SUM(amount_collected) as total FROM rentals WHERE status != 'Pending' AND owner_id = $owner_id");
$total_income = mysqli_fetch_assoc($total_res)['total'] ?? 0;

// 2. Earnings this Month
$current_month = date('Y-m');
$month_res = mysqli_query($conn, "SELECT SUM(amount_collected) as total FROM rentals 
                                  WHERE status != 'Pending' AND rental_start_date LIKE '$current_month%' AND owner_id = $owner_id");
$monthly_income = mysqli_fetch_assoc($month_res)['total'] ?? 0;

include 'header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
    <!-- Total Revenue Card -->
    <div class="bg-primary rounded-[2.5rem] p-10 text-white relative overflow-hidden shadow-xl shadow-primary/20">
        <div class="relative z-10">
            <div class="text-sm font-bold text-white/60 uppercase tracking-widest mb-2">Total Lifetime Revenue</div>
            <div class="text-5xl font-black tracking-tighter">₱<?php echo number_format($total_income, 0); ?></div>
            <div class="mt-6 flex items-center gap-2 text-sm font-medium text-white/80 bg-white/10 w-max px-3 py-1 rounded-lg">
                <i class="fa-solid fa-wallet"></i> Cash Collected
            </div>
        </div>
        <div class="absolute -right-10 -bottom-10 text-white/10 text-9xl"><i class="fa-solid fa-sack-dollar"></i></div>
    </div>

    <!-- Monthly Revenue Card -->
    <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden">
        <div class="relative z-10">
            <div class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">This Month (<?php echo date('M'); ?>)</div>
            <div class="text-5xl font-black tracking-tighter text-slate-800">₱<?php echo number_format($monthly_income, 0); ?></div>
            <div class="mt-6 flex items-center gap-2 text-sm font-medium text-emerald-600 bg-emerald-50 w-max px-3 py-1 rounded-lg">
                <i class="fa-solid fa-arrow-trend-up"></i> Performance
            </div>
        </div>
        <div class="absolute -right-10 -bottom-10 text-slate-50 text-9xl"><i class="fa-solid fa-calendar-days"></i></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Left Column: Revenue by Vehicle -->
    <div class="lg:col-span-2 bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-xl font-black text-slate-800">Revenue Breakdown</h2>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">By Motorcycle Model</p>
            </div>
        </div>
        
        <div class="space-y-6">
            <?php
            $vehicle_query = "SELECT b.model_name, b.plate_number, SUM(r.amount_collected) as total_earned 
                              FROM rentals r 
                              JOIN bikes b ON r.bike_id = b.id 
                              WHERE r.status != 'Pending' AND b.owner_id = $owner_id
                              GROUP BY b.id 
                              ORDER BY total_earned DESC";
            $vehicle_res = mysqli_query($conn, $vehicle_query);

            if (mysqli_num_rows($vehicle_res) > 0) {
                while($row = mysqli_fetch_assoc($vehicle_res)) {
                    $percentage = ($total_income > 0) ? ($row['total_earned'] / $total_income) * 100 : 0;
                    ?>
                    <div class="group">
                        <div class="flex justify-between items-end mb-2">
                            <div>
                                <div class="font-bold text-slate-700"><?php echo htmlspecialchars($row['model_name']); ?></div>
                                <div class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-black text-primary">₱<?php echo number_format($row['total_earned'], 0); ?></div>
                                <div class="text-xs font-bold text-slate-400"><?php echo round($percentage, 1); ?>%</div>
                            </div>
                        </div>
                        <div class="w-full h-3 bg-slate-50 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-primary to-accent rounded-full transition-all duration-1000" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<div class='text-center py-10 text-slate-400'>No earnings data available yet.</div>";
            }
            ?>
        </div>
    </div>

    <!-- Right Column: Recent High-Value Rentals -->
    <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm">
        <h2 class="text-lg font-black text-slate-800 mb-6">Recent Top Rentals</h2>
        <div class="space-y-6">
            <?php
            $top_query = "SELECT r.*, b.model_name, c.fullname as customer_name 
                          FROM rentals r 
                          JOIN bikes b ON r.bike_id = b.id 
                          LEFT JOIN customers c ON r.customer_id = c.userid
                          WHERE r.status != 'Pending' AND r.owner_id = $owner_id
                          ORDER BY r.amount_collected DESC 
                          LIMIT 5";
            $top_res = mysqli_query($conn, $top_query);

            while($top = mysqli_fetch_assoc($top_res)):
            ?>
                <div class="flex justify-between items-center pb-4 border-b border-slate-50 last:border-0">
                    <div>
                        <div class="font-bold text-slate-700 text-sm"><?php echo $top['customer_name'] ?? 'Unknown'; ?></div>
                        <div class="text-xs text-slate-400 font-medium"><?php echo $top['model_name']; ?></div>
                    </div>
                    <div class="text-right">
                        <div class="font-black text-emerald-500 text-sm">+₱<?php echo number_format($top['amount_collected'], 0); ?></div>
                        <div class="text-[10px] font-bold text-slate-300 uppercase"><?php echo date('M d', strtotime($top['rental_start_date'])); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <div class="mt-8 p-4 bg-slate-50 rounded-2xl border border-slate-100 text-center">
            <p class="text-xs text-slate-400 font-medium">
                <i class="fa-solid fa-circle-info"></i> All figures represent gross <strong>cash</strong> revenue before expenses.
            </p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>