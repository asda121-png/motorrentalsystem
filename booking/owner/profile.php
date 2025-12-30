<?php
session_start();
require_once 'db.php';

// Ensure user is logged in as owner
if (!isset($_SESSION['userid']) || ($_SESSION['role'] ?? '') !== 'owner') {
    header("Location: ../login.php");
    exit();
}

$owner_id = $_SESSION['userid'];

// Fetch owner data
$query = "SELECT * FROM owners WHERE ownerid = $owner_id";
$result = mysqli_query($conn, $query);
$owner = mysqli_fetch_assoc($result);

if (!$owner) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Set page identification for the header
$page_title = "My Profile";
$active_nav = "profile"; // A new nav state

include 'header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-[2.5rem] p-10 shadow-sm border border-slate-100">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-primary tracking-tight">Owner Profile</h1>
            <span class="px-4 py-1.5 rounded-full bg-emerald-50 text-emerald-600 text-xs font-bold uppercase tracking-widest border border-emerald-100">
                <?php echo htmlspecialchars($owner['status']); ?>
            </span>
        </div>

        <div class="flex flex-col md:flex-row gap-10">
            <!-- Avatar Section -->
            <div class="flex flex-col items-center gap-4">
                <div class="w-32 h-32 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-5xl font-black text-white shadow-xl shadow-primary/20">
                    <?php echo strtoupper(substr($owner['shopname'], 0, 1)); ?>
                </div>
                <div class="text-center">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">Owner Since</div>
                    <div class="font-bold text-gray-700"><?php echo date('M Y', strtotime($owner['created_at'])); ?></div>
                </div>
            </div>

            <!-- Details Section -->
            <div class="flex-1 space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Full Name</label>
                        <div class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold">
                            <?php echo htmlspecialchars($owner['fullname']); ?>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Shop Name</label>
                        <div class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold">
                            <?php echo htmlspecialchars($owner['shopname']); ?>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Email Address</label>
                        <div class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold flex items-center justify-between">
                            <span><?php echo htmlspecialchars($owner['email']); ?></span>
                            <i class="fa-solid fa-lock text-gray-300" title="Email cannot be changed"></i>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase text-gray-400 tracking-widest ml-1">Phone Number</label>
                        <div class="w-full px-6 py-4 rounded-2xl bg-gray-50 border border-gray-100 text-gray-700 font-bold">
                            <?php echo htmlspecialchars($owner['phone_number'] ?? 'N/A'); ?>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-50">
                    <h3 class="text-xs font-bold uppercase text-gray-400 tracking-widest mb-4 ml-1">Legal Documents</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <?php 
                        $docs = [
                            ['label' => 'Business Permit', 'file' => $owner['business_permit'], 'icon' => 'fa-file-signature'],
                            ['label' => 'Valid ID', 'file' => $owner['valid_id'], 'icon' => 'fa-id-card'],
                            ['label' => 'Brgy. Clearance', 'file' => $owner['barangay_clearance'], 'icon' => 'fa-building-shield']
                        ];
                        foreach($docs as $doc):
                            $hasFile = !empty($doc['file']);
                            $fileName = $hasFile ? basename($doc['file']) : '';
                            $fileUrl = $hasFile ? "../assets/owner_uploads/" . $fileName : '#';
                        ?>
                        <div class="p-4 rounded-2xl border <?php echo $hasFile ? 'border-gray-100 bg-white' : 'border-dashed border-gray-200 bg-gray-50'; ?> flex flex-col items-center text-center gap-3 transition-all hover:shadow-md">
                            <div class="w-10 h-10 rounded-full <?php echo $hasFile ? 'bg-primary/10 text-primary' : 'bg-gray-200 text-gray-400'; ?> flex items-center justify-center text-lg">
                                <i class="fa-solid <?php echo $doc['icon']; ?>"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-gray-700 uppercase tracking-wide"><?php echo $doc['label']; ?></div>
                                <?php if($hasFile): ?>
                                    <a href="<?php echo $fileUrl; ?>" target="_blank" class="text-[10px] font-bold text-secondary hover:underline mt-1 block">View File</a>
                                <?php else: ?>
                                    <span class="text-[10px] font-bold text-gray-400 mt-1 block">Missing</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
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

<?php include 'footer.php'; ?>