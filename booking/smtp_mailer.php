<?php
/**
 * Simple SMTP Mailer for Gmail
 */
function send_gmail($to, $subject, $message_body) {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $username = 'labradoriiichristian@gmail.com';
    $password = 'sggb rkdr davk buul'; // App Password

    $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP Connection Failed: $errno - $errstr");
        return false;
    }

    // Helper to read server response
    function server_parse($socket, $expected_response) {
        $server_response = '';
        while (substr($server_response, 3, 1) != ' ') {
            if (!($server_response = fgets($socket, 256))) return false;
        }
        if (!(substr($server_response, 0, 3) == $expected_response)) return false;
        return true;
    }

    if (!server_parse($socket, '220')) return false;

    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    if (!server_parse($socket, '250')) return false;

    fputs($socket, "STARTTLS\r\n");
    if (!server_parse($socket, '220')) return false;

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return false;

    fputs($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    if (!server_parse($socket, '250')) return false;

    fputs($socket, "AUTH LOGIN\r\n");
    if (!server_parse($socket, '334')) return false;

    fputs($socket, base64_encode($username) . "\r\n");
    if (!server_parse($socket, '334')) return false;

    fputs($socket, base64_encode($password) . "\r\n");
    if (!server_parse($socket, '235')) return false;

    fputs($socket, "MAIL FROM: <$username>\r\n");
    if (!server_parse($socket, '250')) return false;

    fputs($socket, "RCPT TO: <$to>\r\n");
    if (!server_parse($socket, '250')) return false;

    fputs($socket, "DATA\r\n");
    if (!server_parse($socket, '354')) return false;

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Mati City Moto Rentals <$username>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";

    fputs($socket, "$headers\r\n$message_body\r\n.\r\n");
    if (!server_parse($socket, '250')) return false;

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}
?>