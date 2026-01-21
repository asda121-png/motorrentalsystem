<?php
session_start();
/**
 * rental_requests.php - Pending Requests View
 * Displays incoming rental requests for approval or rejection.
 */
require_once 'db.php';

// Set page identification for the header
$page_title = "Rental Requests";
$active_nav = "requests";

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}
$owner_id = $_SESSION['userid'];

// --- HANDLE REQUEST ACTIONS ---

// Action: Approve Request (Collect Cash & Rent)
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    
    // Get request details (Verify ownership)
    $req_res = mysqli_query($conn, "SELECT bike_id, customer_id FROM rentals WHERE id=$request_id AND status='Pending' AND owner_id=$owner_id");
    if ($req_data = mysqli_fetch_assoc($req_res)) {
        $bike_id = $req_data['bike_id'];
        $customer_id = $req_data['customer_id'];
        
        // Check if bike is available before approving
        $bike_check = mysqli_query($conn, "SELECT status FROM bikes WHERE id=$bike_id");
        $bike_status = mysqli_fetch_assoc($bike_check)['status'] ?? 'Unknown';
        if ($bike_status !== 'Available') {
            header("Location: rental_requests.php?msg=unavailable");
            exit();
        }
        
        // 1. Mark Rental as Approved (Reserved)
        mysqli_query($conn, "UPDATE rentals SET status='Approved' WHERE id=$request_id");
        
        // 2. Mark Bike as Reserved
        mysqli_query($conn, "UPDATE bikes SET status='Reserved' WHERE id=$bike_id");

        // 3. Notify Customer
        $bike_model_res = mysqli_query($conn, "SELECT model_name FROM bikes WHERE id=$bike_id");
        $bike_model = mysqli_fetch_assoc($bike_model_res)['model_name'];
        $customer_message = "Your booking for '$bike_model' has been approved! Please proceed to pickup.";
        create_notification($conn, $customer_id, 'customer', $customer_message, 'mybooks.php');
        
        header("Location: rental_requests.php?msg=approved");
        exit();
    }
}

// Action: Reject/Delete Request
if (isset($_GET['action']) && $_GET['action'] == 'reject' && isset($_GET['id'])) {
    $request_id = (int)$_GET['id'];
    mysqli_query($conn, "DELETE FROM rentals WHERE id=$request_id AND status='Pending' AND owner_id=$owner_id");
    header("Location: rental_requests.php?msg=rejected");
    exit();
}

include 'header.php';
?>

<!-- Status Notifications -->
<?php if (isset($_GET['msg'])): ?>
    <div class="bg-white border-l-4 border-accent p-6 rounded-2xl mb-8 shadow-sm flex items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-accent/10 flex items-center justify-center text-accent"><i class="fa-solid fa-check"></i></div>
        <div class="text-slate-600 font-medium">
        <?php 
            if ($_GET['msg'] == 'approved') echo "Request approved! Motorcycle is now 'Reserved' for pickup.";
            if ($_GET['msg'] == 'rejected') echo "Rental request has been removed.";
            if ($_GET['msg'] == 'unavailable') echo "Action Failed: This motorcycle is currently not available (Rented or Maintenance).";
        ?>
        </div>
    </div>
<?php endif; ?>

<div class="mb-8">
    <h2 class="text-3xl font-black text-slate-800 tracking-tight">Booking Requests</h2>
    <p class="text-slate-400 font-medium mt-2">Review and approve incoming rental inquiries.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php
    // Fetch only Pending rentals
    $query = "SELECT r.*, b.model_name, b.plate_number, c.fullname as customer_name, c.profile_image, c.is_verified, c.email, c.phone_number, c.drivers_license_image, c.valid_id_image 
              FROM rentals r 
              JOIN bikes b ON r.bike_id = b.id 
              LEFT JOIN customers c ON r.customer_id = c.userid
              WHERE r.status = 'Pending' AND r.owner_id = $owner_id
              ORDER BY r.created_at DESC";
    $res = mysqli_query($conn, $query);

    if (mysqli_num_rows($res) > 0) {
        while($row = mysqli_fetch_assoc($res)) {
            $cust_data = [
                'name' => $row['customer_name'],
                'email' => $row['email'],
                'phone' => $row['phone_number'],
                'img' => $row['profile_image'],
                'license' => $row['drivers_license_image'],
                'valid_id' => $row['valid_id_image'],
                'verified' => $row['is_verified']
            ];
            ?>
            <div class="bg-white rounded-[2rem] p-8 border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-slate-200/50 transition-all duration-300 flex flex-col">
                <div class="flex justify-between items-start mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-xl font-black text-primary overflow-hidden">
                            <?php if (!empty($row['profile_image'])): ?>
                                <img src="../<?= htmlspecialchars($row['profile_image']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($row['customer_name'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-800"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></h3>
                            <?php if(!empty($row['is_verified']) && $row['is_verified'] == 1): ?>
                                <div class="flex items-center gap-2 text-xs font-bold text-emerald-500 bg-emerald-50 px-2 py-1 rounded-md w-max mt-1">
                                    <i class="fa-solid fa-shield-halved"></i> Verified Customer
                                </div>
                            <?php else: ?>
                                <div class="flex items-center gap-2 text-xs font-bold text-amber-500 bg-amber-50 px-2 py-1 rounded-md w-max mt-1">
                                    <i class="fa-solid fa-circle-exclamation"></i> Unverified
                                </div>
                            <?php endif; ?>
                            <button onclick='viewCustomer(<?php echo json_encode($cust_data, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="mt-2 text-xs font-bold text-slate-500 hover:text-primary flex items-center gap-1 transition-colors">
                                <i class="fa-solid fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Requested</div>
                        <div class="text-sm font-bold text-slate-600"><?= date('M d y, h:i A', strtotime($row['rental_start_date'])); ?></div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-2xl p-6 mb-6 border border-slate-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Vehicle</span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Plate</span>
                    </div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-base font-black text-slate-700"><?= htmlspecialchars($row['model_name']); ?></span>
                        <span class="font-mono text-xs bg-white px-2 py-1 rounded border border-slate-200 text-slate-500"><?= htmlspecialchars($row['plate_number']); ?></span>
                    </div>
                    <div class="h-px bg-slate-200 w-full mb-4"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Collect Cash</span>
                        <span class="text-2xl font-black text-primary">₱<?= number_format($row['amount_collected'], 2); ?></span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-auto">
                    <a href="rental_requests.php?action=reject&id=<?php echo $row['id']; ?>" 
                       class="py-4 rounded-xl border border-slate-200 text-slate-500 font-bold text-center hover:bg-red-50 hover:text-red-500 hover:border-red-100 transition-all"
                       onclick="return confirm('Reject this request?')">
                        Decline
                    </a>
                    <a href="rental_requests.php?action=approve&id=<?php echo $row['id']; ?>" 
                       class="py-4 rounded-xl bg-primary text-white font-bold text-center shadow-lg shadow-primary/20 hover:bg-primary-hover transition-all"
                       onclick="return confirm('Approve the booking?')">
                        Approve
                    </a>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="col-span-full py-24 text-center bg-white rounded-[2.5rem] border border-dashed border-slate-200">
            <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300 text-4xl">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-700">All Caught Up!</h3>
            <p class="text-slate-400 mt-2">There are no pending rental requests at the moment.</p>
        </div>
        <?php
    }
    ?>
</div>

<div class="mt-12 bg-slate-100 rounded-3xl p-8 border border-slate-200">
    <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest mb-4">Processing Guide</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="flex gap-4">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center font-bold text-slate-400 shadow-sm">1</div>
            <p class="text-sm text-slate-500 leading-relaxed">Review the customer details and the requested vehicle model.</p>
        </div>
        <div class="flex gap-4">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center font-bold text-slate-400 shadow-sm">2</div>
            <p class="text-sm text-slate-500 leading-relaxed">Ensure the cash amount shown is collected from the customer.</p>
        </div>
        <div class="flex gap-4">
            <div class="w-8 h-8 rounded-full bg-white flex items-center justify-center font-bold text-slate-400 shadow-sm">3</div>
            <p class="text-sm text-slate-500 leading-relaxed">Click Approve to automatically update the bike status to "Reserved" and the rental status to "Approved.</p>
        </div>
    </div>
</div>

<!-- Customer Detail Modal -->
<div id="customerModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-xl transition-all w-full max-w-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-black text-slate-800">Customer Details</h3>
                    <button onclick="document.getElementById('customerModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                
                <div class="text-center mb-6">
                    <div class="w-24 h-24 rounded-full bg-slate-100 mx-auto mb-3 overflow-hidden border-4 border-slate-50 shadow-sm flex items-center justify-center">
                        <img id="modalImg" src="" class="w-full h-full object-cover hidden">
                        <div id="modalInitial" class="text-3xl font-black text-slate-300 hidden"></div>
                    </div>
                    <h4 id="modalName" class="text-lg font-bold text-slate-800"></h4>
                    <div id="modalVerified" class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mt-1"></div>
                </div>

                <div class="space-y-4 text-sm">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-slate-400 shadow-sm"><i class="fa-solid fa-envelope"></i></div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Email</div>
                            <div id="modalEmail" class="font-bold text-slate-700"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-slate-400 shadow-sm"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase">Phone</div>
                            <div id="modalPhone" class="font-bold text-slate-700"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a id="modalLicense" href="#" target="_blank" class="flex flex-col items-center justify-center p-4 rounded-xl border border-dashed border-slate-300 hover:border-primary hover:bg-primary/5 transition-all group">
                        <i class="fa-solid fa-id-card text-2xl text-slate-300 group-hover:text-primary mb-2"></i>
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary">View License</span>
                    </a>
                    <a id="modalValidId" href="#" target="_blank" class="flex flex-col items-center justify-center p-4 rounded-xl border border-dashed border-slate-300 hover:border-primary hover:bg-primary/5 transition-all group">
                        <i class="fa-solid fa-address-card text-2xl text-slate-300 group-hover:text-primary mb-2"></i>
                        <span class="text-[10px] font-bold text-slate-500 group-hover:text-primary">View Valid ID</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewCustomer(data) {
    document.getElementById('modalName').textContent = data.name;
    document.getElementById('modalEmail').textContent = data.email;
    document.getElementById('modalPhone').textContent = data.phone || 'N/A';
    
    const img = document.getElementById('modalImg');
    const initial = document.getElementById('modalInitial');
    
    if (data.img) {
        img.src = '../' + data.img;
        img.classList.remove('hidden');
        initial.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        initial.classList.remove('hidden');
        initial.textContent = data.name.charAt(0).toUpperCase();
    }

    const badge = document.getElementById('modalVerified');
    if (data.verified == 1) {
        badge.className = 'inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mt-1 bg-emerald-100 text-emerald-600';
        badge.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> Verified';
    } else {
        badge.className = 'inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest mt-1 bg-amber-100 text-amber-600';
        badge.innerHTML = '<i class="fa-solid fa-circle-exclamation mr-1"></i> Unverified';
    }

    const licBtn = document.getElementById('modalLicense');
    if (data.license) {
        licBtn.href = '../' + data.license;
        licBtn.classList.remove('opacity-50', 'pointer-events-none');
    } else {
        licBtn.href = '#';
        licBtn.classList.add('opacity-50', 'pointer-events-none');
    }

    const idBtn = document.getElementById('modalValidId');
    if (data.valid_id) {
        idBtn.href = '../' + data.valid_id;
        idBtn.classList.remove('opacity-50', 'pointer-events-none');
    } else {
        idBtn.href = '#';
        idBtn.classList.add('opacity-50', 'pointer-events-none');
    }

    document.getElementById('customerModal').classList.remove('hidden');
}
</script>

<?php include 'footer.php'; ?>