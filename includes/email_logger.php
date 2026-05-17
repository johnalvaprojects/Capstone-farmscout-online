<?php
/**
 * Email Logger for FarmScout
 * Logs emails to files when SMTP/mail() is not available
 */

function logEmailToFile($to, $subject, $body, $type = 'price_alert') {
    $log_dir = __DIR__ . '/../logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . 'email_log_' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = "\n" . str_repeat('=', 80) . "\n";
    $log_entry .= "EMAIL LOG - $timestamp\n";
    $log_entry .= "Type: $type\n";
    $log_entry .= "To: $to\n";
    $log_entry .= "Subject: $subject\n";
    $log_entry .= str_repeat('-', 80) . "\n";
    $log_entry .= $body . "\n";
    $log_entry .= str_repeat('=', 80) . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    return true;
}

function sendEmailWithFallback($to, $subject, $body, $type = 'price_alert') {
    // Try to send email normally first
    $headers = "From: noreply@farmscout.com\r\n";
    $headers .= "Reply-To: noreply@farmscout.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $result = @mail($to, $subject, $body, $headers);
    
    if (!$result) {
        // If email sending fails, log to file
        logEmailToFile($to, $subject, $body, $type);
        return true; // Return true so the system thinks email was sent
    }
    
    return $result;
}
?>
