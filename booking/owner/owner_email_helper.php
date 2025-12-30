<?php
// Helper to send approval/rejection email to owner
require_once __DIR__ . '/../smtp_mailer.php';
// Assumes $conn is available from the including script (admin/dashboard.php)

function send_owner_status_email($owner_id, $status, $reason = null) {
    global $conn;
    $owner = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email, fullname FROM owners WHERE ownerid = $owner_id"));
    if (!$owner) return false;
    $to = $owner['email'];
    $name = $owner['fullname'];
    if ($status === 'active') {
        $subject = "Your Owner Account is Approved";
        $body = "<h2>Congratulations, $name!</h2><p>Your owner account has been <b>approved</b> by the admin. You can now access all features on MatiMotoRental.</p>";
    } else if ($status === 'disabled' || $status === 'rejected') {
        $subject = "Your Owner Account was Suspended";
        $body = "<h2>Hello, $name</h2><p>Your owner account has been <b>suspended</b> by the admin.";
        if ($reason) {
            $body .= "<br><b>Reason:</b> " . nl2br(htmlspecialchars($reason)) . "</p>";
        } else {
            $body .= "</p>";
        }
        $body .= "<p>If you believe this is a mistake or need more information, please contact support.</p>";
    } else {
        return false;
    }
    return send_gmail($to, $subject, $body);
}
