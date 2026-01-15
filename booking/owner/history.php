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
                <th class="px-8 py-5 text-center text-[10px] font-black uppercase tracking-widest text-slate-400">Details</th>
                <th class="px-8 py-5 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">State</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php
            // Fetch all rentals that are NOT pending (History usually implies actions already taken)
            // We sort by date descending to show the newest first
            $query = "SELECT r.*, b.model_name, b.plate_number, c.fullname as customer_name, c.profile_image, c.is_verified 
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
                    $penalty_amount = isset($row['penalty_amount']) ? $row['penalty_amount'] : 0;
                    
                    // Prepare data for modal
                    $inspection_data = json_encode([
                        'ref' => $row['id'],
                        'customer' => $row['customer_name'] ?? 'Unknown',
                        'vehicle' => $row['model_name'],
                        'exp_start' => date('M d, Y h:i A', strtotime($row['rental_start_date'])),
                        'act_start' => $row['exact_pickup_date'] ? date('M d, Y h:i A', strtotime($row['exact_pickup_date'])) : 'N/A',
                        'exp_end' => $row['expected_return_date'] ? date('M d, Y h:i A', strtotime($row['expected_return_date'])) : 'N/A',
                        'act_end' => $row['rental_end_date'] ? date('M d, Y h:i A', strtotime($row['rental_end_date'])) : 'N/A',
                        'p_fuel' => $row['pickup_fuel_level'] ?? 'N/A',
                        'p_cond' => $row['pickup_condition'] ?? 'N/A',
                        'r_fuel' => $row['return_fuel_level'] ?? 'N/A',
                        'r_cond' => $row['return_condition'] ?? 'N/A',
                        'p_imgs' => $row['pickup_images'] ?? '[]',
                        'r_imgs' => $row['return_images'] ?? '[]',
                        'damage' => $row['damage_notes'] ?? 'None',
                        'cost' => $row['repair_cost'],
                        'penalty' => $penalty_amount
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
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
                                    <?php if(!empty($row['is_verified']) && $row['is_verified'] == 1): ?>
                                        <div class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Verified</div>
                                    <?php else: ?>
                                        <div class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Unverified</div>
                                    <?php endif; ?>
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
                            <?php endif; ?>
                            <?php if($penalty_amount > 0): ?>
                                <div class="text-[10px] font-bold text-red-500 uppercase tracking-wider">+ ₱<?php echo number_format($penalty_amount, 0); ?> Late</div>
                            <?php endif; ?>
                            <?php if($repair_cost == 0 && $penalty_amount == 0): ?>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Cash</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-8 py-6 text-center">
                            <button onclick='openInspectionModal(<?php echo $inspection_data; ?>)' class="text-xs font-bold text-primary hover:text-secondary bg-primary/5 hover:bg-primary/10 px-3 py-2 rounded-lg transition-colors">View Log</button>
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

<!-- Inspection History Modal -->
<div id="inspectionModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all w-full max-w-2xl p-8">
                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-2xl font-black text-slate-800">Rental Log #<span id="inspRef"></span></h3>
                        <p class="text-sm text-slate-500 font-medium mt-1">Full inspection history for <span id="inspVehicle" class="text-primary font-bold"></span></p>
                    </div>
                    <button onclick="document.getElementById('inspectionModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-slate-50 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-colors flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Timeline -->
                    <div class="space-y-6">
                        <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Timeline Comparison</h4>
                        
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <div class="text-[10px] font-bold text-primary uppercase mb-2">Pickup</div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-slate-500">Expected:</span>
                                <span class="font-bold text-slate-700" id="inspExpStart"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-500">Actual:</span>
                                <span class="font-bold text-slate-700" id="inspActStart"></span>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <div class="text-[10px] font-bold text-primary uppercase mb-2">Return</div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-slate-500">Expected:</span>
                                <span class="font-bold text-slate-700" id="inspExpEnd"></span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-500">Actual:</span>
                                <span class="font-bold text-slate-700" id="inspActEnd"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Inspection -->
                    <div class="space-y-6">
                        <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Condition Report</h4>
                        
                        <div class="flex justify-between items-center border-b border-slate-50 pb-3">
                            <span class="text-sm font-bold text-slate-500">Fuel Level</span>
                            <div class="text-sm font-bold text-slate-700">
                                <span id="inspPFuel"></span>% <i class="fa-solid fa-arrow-right text-slate-300 mx-2"></i> <span id="inspRFuel"></span>%
                            </div>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-50 pb-3">
                            <span class="text-sm font-bold text-slate-500">Condition</span>
                            <div class="text-sm font-bold text-slate-700">
                                <span id="inspPCond"></span> <i class="fa-solid fa-arrow-right text-slate-300 mx-2"></i> <span id="inspRCond"></span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase mb-2">Pickup Photos</div>
                                <div id="inspPImgs" class="flex flex-wrap gap-2"></div>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase mb-2">Return Photos</div>
                                <div id="inspRImgs" class="flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                        
                        <div class="bg-red-50 p-4 rounded-2xl border border-red-100">
                            <div class="text-[10px] font-bold text-red-500 uppercase mb-2">Additional Charges</div>
                            <p class="text-sm text-slate-700 font-medium mb-2" id="inspDamage"></p>
                            <div class="text-right text-red-600 font-black" id="inspCost"></div>
                            <div class="text-right text-red-600 font-black" id="inspPenalty"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openInspectionModal(data) {
    document.getElementById('inspRef').textContent = data.ref;
    document.getElementById('inspVehicle').textContent = data.vehicle;
    
    document.getElementById('inspExpStart').textContent = data.exp_start;
    document.getElementById('inspActStart').textContent = data.act_start;
    document.getElementById('inspExpEnd').textContent = data.exp_end;
    document.getElementById('inspActEnd').textContent = data.act_end;
    
    document.getElementById('inspPFuel').textContent = data.p_fuel;
    document.getElementById('inspRFuel').textContent = data.r_fuel;
    document.getElementById('inspPCond').textContent = data.p_cond;
    document.getElementById('inspRCond').textContent = data.r_cond;
    
    // Render Images
    const renderImgs = (jsonStr, containerId) => {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        try {
            const paths = JSON.parse(jsonStr);
            if(paths.length === 0) container.innerHTML = '<span class="text-xs text-slate-300 italic">No photos</span>';
            paths.forEach(path => {
                const a = document.createElement('a');
                a.href = '../' + path;
                a.target = '_blank';
                a.innerHTML = `<img src="../${path}" class="w-12 h-12 rounded-lg object-cover border border-slate-200 hover:scale-110 transition-transform">`;
                container.appendChild(a);
            });
        } catch(e) { container.innerHTML = '<span class="text-xs text-slate-300 italic">Error loading</span>'; }
    };
    renderImgs(data.p_imgs, 'inspPImgs');
    renderImgs(data.r_imgs, 'inspRImgs');
    
    document.getElementById('inspDamage').textContent = data.damage || 'No damage reported.';
    if(data.cost > 0) {
        document.getElementById('inspCost').textContent = 'Repair Cost: ₱' + new Intl.NumberFormat().format(data.cost);
        document.getElementById('inspCost').parentElement.classList.remove('hidden');
    } else {
        document.getElementById('inspCost').textContent = '';
    }

    if(data.penalty > 0) {
        document.getElementById('inspPenalty').textContent = 'Late Penalty: ₱' + new Intl.NumberFormat().format(data.penalty);
        document.getElementById('inspPenalty').parentElement.classList.remove('hidden');
    } else {
        document.getElementById('inspPenalty').textContent = '';
    }
    
    document.getElementById('inspectionModal').classList.remove('hidden');
}
</script>

<?php include 'footer.php'; ?>