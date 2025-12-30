<?php
session_start();
/**
 * dashboard.php - Premium Creative Overview
 * Designed with high-contrast elements, visual hierarchy, and clean modern aesthetics.
 */
require_once 'db.php';

// Set page identification for the header
$page_title = "Owner Dashboard";
$active_nav = "dashboard";

// Ensure user is logged in as owner
$account_status = $_SESSION['account_status'] ?? 'active';
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_id = $_SESSION['userid'];


// --- FETCH SUMMARY STATISTICS ---

// 1. Total Fleet Count
$total_bikes_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM bikes WHERE owner_id = $owner_id");
$total_bikes = mysqli_fetch_assoc($total_bikes_res)['count'] ?? 0;

// 2. Currently Rented Count
$rented_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM bikes WHERE status='Rented' AND owner_id = $owner_id");
$rented_count = mysqli_fetch_assoc($rented_res)['count'] ?? 0;

// 3. Available for Rent Count
$available_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM bikes WHERE status='Available' AND owner_id = $owner_id");
$available_count = mysqli_fetch_assoc($available_res)['count'] ?? 0;

// 4. Total Cash Collected
$earnings_res = mysqli_query($conn, "SELECT SUM(amount_collected) as total FROM rentals WHERE owner_id = $owner_id");
$total_earnings = mysqli_fetch_assoc($earnings_res)['total'] ?? 0;

// Utilization Calculation
$utilization = ($total_bikes > 0) ? round(($rented_count / $total_bikes) * 100) : 0;

include 'header.php';
?>

<!-- Content Wrapper -->
<div class="max-w-[1600px] mx-auto">
    <!-- Creative Hero Section --><?php if ($account_status !== 'active'): ?>
                    <div style="background:#fff3cd;color:#856404;padding:16px;border-radius:8px;margin:16px 0;text-align:center;font-weight:bold;">
                        Notice: Your owner account is <b><?php echo htmlspecialchars($account_status); ?></b>. Some features may be limited until approved by admin.
                    </div>
                <?php endif; ?>
    <div class="relative bg-primary rounded-[2.5rem] p-8 md:p-12 mb-10 text-white overflow-hidden shadow-2xl shadow-primary/20">
        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
            <div class="max-w-xl">
                <div class="flex items-center gap-3 mb-6">
                    
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-md px-3 py-1.5 rounded-full border border-white/10">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                        </span>
                        
                        <span class="text-[10px] font-black uppercase tracking-widest text-white">System Active</span>
                    </div>
                    <span class="text-[10px] font-bold text-white/50 uppercase tracking-widest"><?php echo date('l, jS F'); ?></span>
                </div>
                
                <h1 class="text-4xl md:text-5xl font-black tracking-tight leading-tight">Mati City <br/><span class="text-accent italic">Fleet Overview</span></h1>
                <p class="text-white/60 font-medium mt-4 text-lg">Manage your rentals, track performance, and grow your motorcycle business with precision.</p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 w-full md:w-auto">
                <div class="bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-[2rem] flex-1 md:min-w-[160px] text-center">
                    <div class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] mb-2">Efficiency</div>
                    <div class="text-3xl font-black text-accent"><?php echo $utilization; ?>%</div>
                </div>
                <div class="bg-white/5 backdrop-blur-xl border border-white/10 p-6 rounded-[2rem] flex-1 md:min-w-[160px] text-center">
                    <div class="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] mb-2">On Trips</div>
                    <div class="text-3xl font-black text-white"><?php echo $rented_count; ?></div>
                </div>
            </div>
        </div>
        <!-- Abstract Glass Orbs -->
        <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-accent/20 rounded-full blur-[120px]"></div>
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-secondary/30 rounded-full blur-[120px]"></div>
    </div>

    <!-- KPI Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <?php
        $stats_cards = [
            ['Total Fleet', $total_bikes, 'fa-motorcycle', 'bg-slate-50', 'text-slate-400'],
            ['Active Rentals', $rented_count, 'fa-route', 'bg-blue-50', 'text-blue-500'],
            ['Ready to Book', $available_count, 'fa-check-double', 'bg-emerald-50', 'text-emerald-500'],
            ['Gross Revenue', '₱'.number_format($total_earnings, 0), 'fa-chart-pie', 'bg-accent/10', 'text-secondary']
        ];
        foreach($stats_cards as $card):
        ?>
        <div class="bg-white p-7 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col items-start hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-300">
            <div class="w-12 h-12 rounded-2xl <?= $card[3] ?> flex items-center justify-center <?= $card[4] ?> mb-6">
                <i class="fa-solid <?= $card[2] ?> text-lg"></i>
            </div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-1"><?= $card[0] ?></p>
            <div class="text-3xl font-black text-slate-800 tracking-tighter"><?= $card[1] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Activity Section -->
        <div class="lg:col-span-8">
            <div class="flex justify-between items-center mb-6 px-2">
                <div>
                    <h2 class="text-xl font-black text-slate-800 tracking-tight uppercase italic">Live Transactions</h2>
                </div>
                <a href="history.php" class="bg-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-500 px-4 py-2 rounded-full hover:bg-slate-200 transition-colors">History</a>
            </div>
            
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Time</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</th>
                            <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400 text-center">Vehicle</th>
                            <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php
                        $recent_query = "SELECT r.*, b.model_name, c.fullname as customer_name, c.profile_image 
                                         FROM rentals r 
                                         JOIN bikes b ON r.bike_id = b.id 
                                         LEFT JOIN customers c ON r.customer_id = c.userid
                                         WHERE r.owner_id = $owner_id
                                         ORDER BY r.rental_start_date DESC 
                                         LIMIT 6";
                        $recent_res = mysqli_query($conn, $recent_query);

                        while($row = mysqli_fetch_assoc($recent_res)):
                        ?>
                        <tr class="group hover:bg-slate-50/80 transition-colors">
                            <td class="px-8 py-6 text-xs font-bold text-slate-400"><?= date('M d • h:i A', strtotime($row['rental_start_date'])); ?></td>
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center text-[10px] font-black text-primary overflow-hidden">
                                        <?php if (!empty($row['profile_image'])): ?>
                                            <img src="../<?= htmlspecialchars($row['profile_image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?= strtoupper(substr($row['customer_name'] ?? 'W', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-sm font-black text-slate-700"><?= htmlspecialchars($row['customer_name'] ?? 'Walk-in Guest'); ?></div>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="text-[10px] font-black text-slate-500 bg-slate-100 px-3 py-1.5 rounded-lg uppercase tracking-widest"><?= htmlspecialchars($row['model_name']); ?></span>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <span class="text-sm font-black text-primary">₱<?= number_format($row['amount_collected'], 0); ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sidebar Components -->
        <div class="lg:col-span-4 space-y-8">
            <!-- Performance Widget -->
            <div class="bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm">
                <div class="text-center mb-8">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-[0.2em] mb-1">Top Performers</h3>
                    <p class="text-[10px] font-bold text-slate-400 italic">Rental volume by model</p>
                </div>
                
                <div class="space-y-6">
                    <?php
                    $perf_q = "SELECT b.model_name, COUNT(r.id) as trips 
                               FROM rentals r JOIN bikes b ON r.bike_id = b.id 
                               WHERE b.owner_id = $owner_id
                               GROUP BY b.id ORDER BY trips DESC LIMIT 4";
                    $perf_res = mysqli_query($conn, $perf_q);
                    $max_trips = 0;
                    $perf_data = [];
                    while($p = mysqli_fetch_assoc($perf_res)) {
                        if($max_trips === 0) $max_trips = $p['trips'];
                        $perf_data[] = $p;
                    }

                    foreach($perf_data as $data):
                        $width = ($max_trips > 0) ? ($data['trips'] / $max_trips) * 100 : 0;
                    ?>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-black text-slate-700 uppercase tracking-widest"><?= $data['model_name'] ?></span>
                            <span class="text-xs font-black text-secondary"><?= $data['trips'] ?> Trips</span>
                        </div>
                        <div class="w-full h-2 bg-slate-50 rounded-full overflow-hidden flex">
                            <div class="h-full bg-secondary rounded-full" style="width: <?= $width ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-10 pt-8 border-t border-slate-50 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Fleet Occupancy</div>
                        <div class="text-2xl font-black text-primary tracking-tighter italic"><?= $utilization ?>%</div>
                    </div>
                    <div class="w-14 h-14 rounded-full border-[6px] border-slate-50 border-t-accent flex items-center justify-center text-[10px] font-black text-slate-400">
                        ACTV
                    </div>
                </div>
            </div>

            <!-- Quick Action Grid -->
            <div class="grid grid-cols-2 gap-4">
                <a href="manage_motorcycle.php" class="bg-white border border-slate-100 p-6 rounded-[2rem] text-primary flex flex-col items-center gap-3 hover:bg-slate-50 transition-all shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center">
                        <i class="fa-solid fa-plus-circle text-lg"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest">Add Bike</span>
                </a>
                <a href="rental_requests.php" class="bg-white border border-slate-100 p-6 rounded-[2rem] text-primary flex flex-col items-center gap-3 hover:bg-slate-50 transition-all shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center">
                        <i class="fa-solid fa-bell text-lg"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest">Inquiries</span>
                </a>
                <a href="income.php" class="bg-white border border-slate-100 p-6 rounded-[2rem] text-primary flex flex-col items-center gap-3 hover:bg-slate-50 transition-all shadow-sm col-span-2">
                    <div class="w-10 h-10 rounded-xl bg-primary/5 flex items-center justify-center">
                        <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest">Financial Reports</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>