<?php
/**
 * Simple SMTP Mailer for Gmail
 */
$smtp_debug_error = ""; // Global variable to store the last error

// Helper to read server response (Moved outside to prevent redeclaration errors)
if (!function_exists('server_parse')) {
    function server_parse($socket, $expected_response) {
        global $smtp_debug_error;
        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if (!($server_response = fgets($socket, 256))) {
                $smtp_debug_error = "No response from server";
                error_log("SMTP Error: $smtp_debug_error");
                return false;
            }
        }
        if (!(substr($server_response, 0, 3) == $expected_response)) {
            $smtp_debug_error = "Expected $expected_response, got " . substr($server_response, 0, 3) . " - Response: $server_response";
            error_log("SMTP Error: $smtp_debug_error");
            return false;
        }
        return true;
    }
}

function send_gmail($to, $subject, $message_body, $reply_to = null) {
    global $smtp_debug_error;
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'christian.labrador@dorsu.edu.ph';
    $password = str_replace(' ', '', 'vkny txyw axqg xjik'); // Remove spaces from App Password

    // 1. Create Stream Context to bypass SSL Certificate issues on XAMPP/Localhost
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    // 2. Use stream_socket_client instead of fsockopen
    $socket = stream_socket_client("tcp://$smtp_host:$smtp_port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        $smtp_debug_error = "Connection Failed: $errno - $errstr";
        error_log("SMTP: $smtp_debug_error");
        return false;
    }

    if (!server_parse($socket, '220')) { fclose($socket); return false; }

    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    if (!server_parse($socket, '250')) { fclose($socket); return false; }

    fputs($socket, "STARTTLS\r\n");
    if (!server_parse($socket, '220')) { fclose($socket); return false; }

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        $smtp_debug_error = "TLS negotiation failed. Ensure OpenSSL is enabled in php.ini";
        error_log("SMTP: $smtp_debug_error");
        fclose($socket);
        return false;
    }

    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    if (!server_parse($socket, '250')) { fclose($socket); return false; }

    fputs($socket, "AUTH LOGIN\r\n");
    if (!server_parse($socket, '334')) { fclose($socket); return false; }

    fputs($socket, base64_encode($username) . "\r\n");
    if (!server_parse($socket, '334')) { fclose($socket); return false; }

    fputs($socket, base64_encode($password) . "\r\n");
    if (!server_parse($socket, '235')) { fclose($socket); return false; }

    fputs($socket, "MAIL FROM: <$username>\r\n");
    if (!server_parse($socket, '250')) { fclose($socket); return false; }

    fputs($socket, "RCPT TO: <$to>\r\n");
    if (!server_parse($socket, '250')) { fclose($socket); return false; }

    fputs($socket, "DATA\r\n");
    if (!server_parse($socket, '354')) { fclose($socket); return false; }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Mati City Moto Rentals <$username>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date("r") . "\r\n"; // Helps with Spam filters
    
    if ($reply_to) {
        $headers .= "Reply-To: $reply_to\r\n";
    }

    fputs($socket, "$headers\r\n$message_body\r\n.\r\n");
    if (!server_parse($socket, '250')) { fclose($socket); return false; }

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}
?>