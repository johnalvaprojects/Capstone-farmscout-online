<?php
require_once __DIR__ . '/env.php';

/**
 * Email Configuration for FarmScout Online
 * Configure your email settings here
 */

$email_config = [
    // Basic email settings
    'from_email' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@farmscout.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'FarmScout Online - Baloan Public Market',
    
    // SMTP Settings
    'use_smtp' => fs_env_bool(getenv('MAIL_USE_SMTP'), false),
    'smtp_host' => getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int)(getenv('MAIL_SMTP_PORT') ?: 587),
    'smtp_username' => getenv('MAIL_SMTP_USER') ?: '',
    'smtp_password' => getenv('MAIL_SMTP_PASSWORD') ?: '',
    'smtp_secure' => getenv('MAIL_SMTP_ENCRYPTION') ?: 'tls',
    
    // Email content settings
    'site_url' => getenv('APP_URL') ?: 'http://localhost/farmscout_online',
    'support_email' => getenv('MAIL_SUPPORT_ADDRESS') ?: 'support@farmscout.com',
    
    // Testing mode - when true, emails are logged instead of sent
    'test_mode' => fs_env_bool(getenv('MAIL_TEST_MODE'), false),
    'test_email' => getenv('MAIL_TEST_ADDRESS') ?: 'test@example.com',
    
    // Email templates directory
    'templates_dir' => __DIR__ . '/../includes/email_templates/',
    
    // Logging
    'log_emails' => true,
    'email_log_file' => __DIR__ . '/../logs/email.log',
];

/**
 * Get email configuration
 */
if (!function_exists('getEmailConfig')) {
function getEmailConfig() {
    global $email_config;
    return $email_config;
}
}

/**
 * Log email activity
 */
if (!function_exists('logEmailActivity')) {
function logEmailActivity($to, $subject, $status, $error = null) {
    $config = getEmailConfig();
    
    if (!$config['log_emails']) {
        return;
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'to' => $to,
        'subject' => $subject,
        'status' => $status,
        'error' => $error,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
    ];
    
    $log_dir = dirname($config['email_log_file']);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents(
        $config['email_log_file'], 
        json_encode($log_entry) . "\n", 
        FILE_APPEND | LOCK_EX
    );
}
}


/**
 * Send welcome email to new users
 */
if (!function_exists('sendWelcomeEmail')) {
function sendWelcomeEmail($email, $user_name) {
    try {
        $config = getEmailConfig();
        $mailer = getMailer();
        
        // Create email content
        $email_content = createWelcomeEmail($user_name);
        $full_html = $mailer->wrapTemplate($email_content, 'Welcome to FarmScout Online');
        
        $subject = "Welcome to FarmScout Online - Your Market Price Companion!";
        
        // Test mode handling
        if ($config['test_mode']) {
            logEmailActivity($email, $subject, 'TEST_MODE', 'Welcome email sent to test mode');
            error_log("TEST EMAIL: Welcome email to $email");
            return true;
        }
        
        // Send email
        $success = $mailer->send($email, $subject, $full_html, true);
        
        // Log activity
        if ($success) {
            logEmailActivity($email, $subject, 'SENT');
        } else {
            logEmailActivity($email, $subject, 'FAILED', 'Mail function returned false');
        }
        
        return $success;
        
    } catch (Exception $e) {
        logEmailActivity($email, $subject ?? 'Welcome Email', 'ERROR', $e->getMessage());
        error_log("Welcome email error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Test email functionality
 */
if (!function_exists('sendTestEmail')) {
function sendTestEmail($to_email = null) {
    $config = getEmailConfig();
    $test_email = $to_email ?? $config['test_email'];
    
    $mailer = getMailer();
    
    $content = '
    <h2>Test Email - FarmScout Online</h2>
    <p>This is a test email to verify that the email system is working correctly.</p>
    <div class="alert-box">
        <p><strong>Email System Status:</strong> ✅ Working</p>
        <p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</p>
        <p><strong>Configuration:</strong> ' . ($config['use_smtp'] ? 'SMTP' : 'PHP Mail') . '</p>
    </div>
    <p>If you received this email, your FarmScout Online email system is configured correctly!</p>
    ';
    
    $full_html = $mailer->wrapTemplate($content, 'Test Email - FarmScout Online');
    $subject = "Test Email - FarmScout Online Email System";
    
    try {
        $success = $mailer->send($test_email, $subject, $full_html, true);
        
        if ($success) {
            logEmailActivity($test_email, $subject, 'TEST_SENT');
            return ['success' => true, 'message' => 'Test email sent successfully to ' . $test_email];
        } else {
            logEmailActivity($test_email, $subject, 'TEST_FAILED', 'Mail function returned false');
            return ['success' => false, 'message' => 'Failed to send test email'];
        }
    } catch (Exception $e) {
        logEmailActivity($test_email, $subject, 'TEST_ERROR', $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    }
}

// Return the configuration array
return $email_config;
?>