<?php
session_start();
/**
 * history.php - Comprehensive Customer Rental History
 * Displays a log of all completed and active rental transactions.
 */
require_once 'db.php';

// Set page identification for the header
$page_title = "Customer History";
$active_nav = "history";

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_id = $_SESSION['userid'];

include 'header.php';
?>

<div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-8 border-b border-slate-50">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Transaction Log</h2>
                <p class="text-slate-400 text-sm font-medium mt-1">Complete history of all processed rentals.</p>
            </div>
            <div class="bg-slate-50 px-4 py-2 rounded-xl border border-slate-100 flex items-center gap-2 text-slate-500 text-xs font-bold uppercase tracking-widest cursor-pointer hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-filter"></i> Filter
            </div>
        </div>
    </div>

    <table class="w-full">
        <thead class="bg-slate-50/50">
            <tr>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Date Logged</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Customer</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Vehicle</th>
                <th class="px-8 py-5 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Amount</th>
                <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">State</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php
            // Fetch all rentals that are NOT pending (History usually implies actions already taken)
            // We sort by date descending to show the newest first
            $query = "SELECT r.*, b.model_name, b.plate_number, c.fullname as customer_name, c.profile_image 
                      FROM rentals r 
                      JOIN bikes b ON r.bike_id = b.id 
                      LEFT JOIN customers c ON r.customer_id = c.userid
                      WHERE r.status != 'Pending' AND r.owner_id = $owner_id
                      ORDER BY r.rental_start_date DESC";
            
            $res = mysqli_query($conn, $query);

            if (mysqli_num_rows($res) > 0) {
                while($row = mysqli_fetch_assoc($res)) {
                    $status_color = match($row['status']) {
                        'Completed' => 'bg-slate-100 text-slate-500',
                        'Active' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                        'Overdue' => 'bg-red-50 text-red-600 border-red-100',
                        default => 'bg-slate-50 text-slate-500'
                    };
                    $repair_cost = $row['repair_cost'] ?? 0;
                    ?>
                    <tr class="group hover:bg-slate-50/50 transition-colors">
                        <td class="px-8 py-6">
                            <div class="font-bold text-slate-700 text-sm">
                                <?php echo date('M d, Y', strtotime($row['rental_start_date'])); ?>
                            </div>
                            <div class="text-xs font-medium text-slate-400 mt-0.5">
                                <?php echo date('h:i A', strtotime($row['rental_start_date'])); ?>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white text-xs font-bold shadow-sm overflow-hidden">
                                    <?php if (!empty($row['profile_image'])): ?>
                                        <img src="../<?= htmlspecialchars($row['profile_image']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($row['customer_name'] ?? 'U', 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-700 text-sm">
                                        <?php echo htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Verified</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6">
                            <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($row['model_name']); ?></div>
                            <div class="font-mono text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded w-max mt-1"><?php echo htmlspecialchars($row['plate_number']); ?></div>
                        </td>
                        <td class="px-8 py-6">
                            <div class="font-black text-emerald-600 text-sm">
                                ₱<?php echo number_format($row['amount_collected'], 2); ?>
                            </div>
                            <?php if($repair_cost > 0): ?>
                                <div class="text-[10px] font-bold text-red-500 uppercase tracking-wider">+ ₱<?php echo number_format($repair_cost, 0); ?> Dmg</div>
                            <?php else: ?>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cash</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-6 text-right">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-transparent text-[10px] font-black uppercase tracking-widest <?php echo $status_color; ?>">
                                <?php if($row['status'] == 'Completed'): ?>
                                    <i class="fa-solid fa-check-circle"></i>
                                <?php elseif($row['status'] == 'Active'): ?>
                                    <i class="fa-solid fa-clock"></i>
                                <?php endif; ?>
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="5" class="text-center py-24">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300 text-3xl">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-700">No History Found</h3>
                        <p class="text-slate-400 text-sm mt-1">Transactions will appear here once rentals are processed.</p>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>