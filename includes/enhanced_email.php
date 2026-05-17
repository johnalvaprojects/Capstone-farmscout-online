<?php
/**
 * Enhanced Email System for FarmScout Online
 * Uses PHPMailer for production-ready email sending
 */

// Global function to get enhanced mailer
function getEnhancedMailer($config = null) {
    return new FarmScoutMailer($config);
}

// Enhanced email functions for backwards compatibility
function sendEnhancedPriceAlert($alert_data, $template_style = null) {
    $mailer = getEnhancedMailer();
    return $mailer->sendPriceAlert($alert_data, $template_style);
}

function sendEnhancedWelcomeEmail($email, $user_name) {
    $mailer = getEnhancedMailer();
    return $mailer->sendWelcomeEmail($email, $user_name);
}

function sendEnhancedTestEmail($email = null) {
    $mailer = getEnhancedMailer();
    return $mailer->sendTestEmail($email);
}

function sendEnhancedEmail($to, $subject, $body, $is_html = true) {
    $mailer = getEnhancedMailer();
    return $mailer->send($to, $subject, $body, $is_html);
}

// Load PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
} else {
    error_log('PHPMailer not found in expected locations');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class FarmScoutMailer {
    private $config;
    private $mailer;
    private $templates_dir;
    
    public function __construct($config = null) {
        $this->config = $config ?? $this->getDefaultConfig();
        $this->templates_dir = $this->config['templates_dir'] ?? __DIR__ . '/email_templates/';
        $this->initializeMailer();
    }
    
    private function getDefaultConfig() {
        // Load email configuration from config file
        $config_file = __DIR__ . '/../config/email.php';
        if (file_exists($config_file)) {
            $config = include $config_file;
            if (is_array($config)) {
                return $config;
            }
        }
        
        // Fallback configuration
        return [
            'from_email' => 'noreply@farmscout.com',
            'from_name' => 'FarmScout Online',
            'use_smtp' => false,
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_secure' => 'tls',
            'test_mode' => false,
            'debug_level' => 0
        ];
    }
    
    private function initializeMailer() {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Fall back to simple mailer if PHPMailer is not available
            return;
        }
        
        $this->mailer = new PHPMailer(true);
        
        // Configure SMTP if enabled
        if ($this->config['use_smtp']) {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'];
            $this->mailer->Password = $this->config['smtp_password'];
            $this->mailer->Port = $this->config['smtp_port'];
            
            // Set encryption
            if ($this->config['smtp_secure'] === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Debug level
            if (isset($this->config['debug_level'])) {
                $this->mailer->SMTPDebug = $this->config['debug_level'];
            }
        }
        
        // Set default from address with fallbacks
        $from_email = $this->config['from_email'] ?? 'noreply@farmscout.com';
        $from_name = $this->config['from_name'] ?? 'FarmScout Online';
        $this->mailer->setFrom($from_email, $from_name);
    }
    
    /**
     * Check if mail server is configured
     */
    private function isMailServerConfigured() {
        // Check PHP mail configuration
        $smtp_host = ini_get('SMTP');
        $smtp_port = ini_get('smtp_port');
        $sendmail_path = ini_get('sendmail_path');
        
        // If on Windows, check SMTP settings
        if (PHP_OS_FAMILY === 'Windows') {
            return !empty($smtp_host) && $smtp_host !== 'localhost' && !empty($smtp_port);
        }
        
        // If on Unix/Linux, check sendmail path
        return !empty($sendmail_path) && file_exists($sendmail_path);
    }
    
    /**
     * Send email using PHPMailer
     */
    public function send($to, $subject, $body, $is_html = true, $attachments = []) {
        try {
            // Check if in test mode
            if ($this->config['test_mode']) {
                return $this->handleTestMode($to, $subject, $body);
            }
            
            // If PHPMailer is not available, fall back to simple mail
            if (!$this->mailer) {
                return $this->sendSimpleMail($to, $subject, $body, $is_html);
            }
            
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipient
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mailer->addAddress($name);
                    } else {
                        $this->mailer->addAddress($email, $name);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Set content
            $this->mailer->isHTML($is_html);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            if ($is_html) {
                $this->mailer->AltBody = strip_tags($body);
            }
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mailer->addAttachment($attachment);
                }
            }
            
            // Send email
            $result = $this->mailer->send();
            
            // Log success
            $this->logEmail($to, $subject, 'SENT', null);
            
            return $result;
            
        } catch (Exception $e) {
            // Log error
            $this->logEmail($to, $subject, 'ERROR', $e->getMessage());
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle test mode
     */
    private function handleTestMode($to, $subject, $body) {
        $log_message = "TEST MODE EMAIL:\n";
        $log_message .= "To: " . (is_array($to) ? json_encode($to) : $to) . "\n";
        $log_message .= "Subject: $subject\n";
        $log_message .= "Body: " . substr(strip_tags($body), 0, 200) . "...\n";
        $log_message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
        
        error_log($log_message);
        $this->logEmail($to, $subject, 'TEST_MODE', 'Email logged in test mode');
        
        return true;
    }
    
    /**
     * Simple mail fallback
     */
    private function sendSimpleMail($to, $subject, $body, $is_html) {
        
        $from_name = $this->config['from_name'] ?? 'FarmScout Online';
        $from_email = $this->config['from_email'] ?? 'noreply@farmscout.com';
        
        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'X-Mailer: FarmScout Online',
            'MIME-Version: 1.0'
        ];
        
        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        // Suppress warnings and handle errors gracefully
        $result = @mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) {
            error_log("Email sending failed: To: $to, Subject: $subject");
            $this->logEmail($to, $subject, 'FAILED', 'Mail function returned false');
        }
        
        return $result;
    }
    
    /**
     * Send price alert email with template selection
     */
    public function sendPriceAlert($alert_data, $template_style = null) {
        // Load template configuration
        $template_config = $this->getTemplateConfig();
        
        // Use provided style or fallback to configured default
        if (!$template_style) {
            $template_style = $template_config['default_price_alert_template'] ?? 'standard';
        }
        // Template selection based on style preference
        $template_options = [
            'special_event' => 'price_alert_special_event.html',
            'special_event_v2' => 'price_alert_special_event_v2.html', // Email client optimized
            'standard' => 'price_alert_template.html',
            'professional' => 'price_alert_professional.html',
            'dark' => 'price_alert_dark_theme.html',
            'premium_nitro' => 'price_alert_premium_nitro.html' // New Discord-inspired template
        ];
        
        // Use the requested template or fallback to standard
        $template_name = $template_options[$template_style] ?? $template_options['standard'];
        $template = $this->loadTemplate($template_name);
        
        // Fallback chain if template not found
        if (!$template) {
            foreach (['standard', 'special_event_v2', 'premium_nitro', 'professional'] as $fallback_style) {
                $fallback_template = $template_options[$fallback_style];
                $template = $this->loadTemplate($fallback_template);
                if ($template) break;
            }
            
            if (!$template) {
                error_log("No email template found for price alerts");
                return false;
            }
        }
        
        // Prepare template variables
        $variables = [
            'ALERT_MESSAGE' => $this->getAlertMessage($alert_data['alert_type'], $alert_data['target_price']),
            'FILIPINO_NAME' => $alert_data['product']['filipino_name'],
            'PRODUCT_NAME' => $alert_data['product']['name'],
            'PRODUCT_IMAGE' => $alert_data['product']['image_url'] ?? 'https://via.placeholder.com/80x80?text=Product',
            'OLD_PRICE' => '₱' . number_format($alert_data['product']['previous_price'], 2),
            'NEW_PRICE' => '₱' . number_format($alert_data['product']['current_price'], 2),
            'UNIT' => $alert_data['product']['unit'],
            'PRICE_CHANGE' => $this->calculatePriceChange($alert_data['product']['previous_price'], $alert_data['product']['current_price']),
            'PRICE_CHANGE_PERCENT' => $this->calculatePriceChangePercent($alert_data['product']['previous_price'], $alert_data['product']['current_price']),
            'PRICE_CHANGE_ICON' => $this->getPriceChangeIcon($alert_data['product']['previous_price'], $alert_data['product']['current_price']),
            'PRICE_CHANGE_COLOR' => $this->getPriceChangeColor($alert_data['product']['previous_price'], $alert_data['product']['current_price']),
            'EMAIL' => $alert_data['email'],
            'CURRENT_YEAR' => date('Y')
        ];
        
        // Replace template variables
        $html_content = $this->replaceTemplateVariables($template, $variables);
        
        $subject = "🔔 Price Alert: " . $alert_data['product']['filipino_name'] . " - FarmScout Online";
        
        return $this->send($alert_data['email'], $subject, $html_content, true);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($email, $user_name) {
        $template = $this->loadTemplate('welcome_template.html');
        
        if (!$template) {
            // Fallback to inline content
            $html_content = $this->createWelcomeEmail($user_name);
        } else {
            // Use modern template
            $variables = [
                'USER_NAME' => htmlspecialchars($user_name),
                'WEBSITE_URL' => $this->getCurrentDomain(),
                'CURRENT_YEAR' => date('Y')
            ];
            $html_content = $this->replaceTemplateVariables($template, $variables);
        }
        
        $subject = "🌾 Welcome to FarmScout Online - Your Market Price Companion!";
        
        return $this->send($email, $subject, $html_content, true);
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($to_email = null) {
        $test_email = $to_email ?? $this->config['test_email'] ?? 'test@example.com';
        
        $template = $this->loadTemplate('test_template.html');
        
        if (!$template) {
            // Fallback to inline content
            $html_content = $this->createTestEmail();
        } else {
            // Use modern template
            $variables = [
                'TIMESTAMP' => date('Y-m-d H:i:s'),
                'EMAIL_METHOD' => $this->config['use_smtp'] ? 'SMTP' : 'PHP Mail',
                'WEBSITE_URL' => $this->getCurrentDomain(),
                'CURRENT_YEAR' => date('Y')
            ];
            $html_content = $this->replaceTemplateVariables($template, $variables);
        }
        
        $subject = "✅ Email System Test - FarmScout Online";
        
        $result = $this->send($test_email, $subject, $html_content, true);
        
        if ($result) {
            return ['success' => true, 'message' => 'Test email sent successfully to ' . $test_email];
        } else {
            return ['success' => false, 'message' => 'Failed to send test email to ' . $test_email];
        }
    }
    
    /**
     * Load email template
     */
    private function loadTemplate($template_name) {
        $template_path = $this->templates_dir . $template_name;
        
        if (file_exists($template_path)) {
            return file_get_contents($template_path);
        }
        
        error_log("Email template not found: $template_path");
        return false;
    }
    
    /**
     * Replace template variables
     */
    private function replaceTemplateVariables($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Get alert message based on type
     */
    private function getAlertMessage($alert_type, $target_price) {
        switch ($alert_type) {
            case 'below':
                return "Great news! The price has dropped below your target price of ₱" . number_format($target_price, 2) . ".";
            case 'above':
                return "Price alert! The price has risen above your target price of ₱" . number_format($target_price, 2) . ".";
            case 'change':
                return "Price update! The price has changed for this product.";
            default:
                return "Price alert notification for your watched product.";
        }
    }
    
    /**
     * Calculate price change
     */
    private function calculatePriceChange($old_price, $new_price) {
        $change = $new_price - $old_price;
        return ($change >= 0 ? '+' : '') . '₱' . number_format(abs($change), 2);
    }
    
    /**
     * Calculate price change percentage
     */
    private function calculatePriceChangePercent($old_price, $new_price) {
        if ($old_price == 0) return '0.00';
        
        $change_percent = (($new_price - $old_price) / $old_price) * 100;
        return ($change_percent >= 0 ? '+' : '') . number_format($change_percent, 1);
    }
    
    /**
     * Get price change icon
     */
    private function getPriceChangeIcon($old_price, $new_price) {
        if ($new_price > $old_price) return '↗️';
        if ($new_price < $old_price) return '↘️';
        return '→';
    }
    
    /**
     * Get price change color
     */
    private function getPriceChangeColor($old_price, $new_price) {
        if ($new_price > $old_price) return '#dc2626'; // Red for increase
        if ($new_price < $old_price) return '#059669'; // Green for decrease
        return '#6b7280'; // Gray for no change
    }
    
    /**
     * Get current domain for links
     */
    private function getCurrentDomain() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $domain = $_SERVER['HTTP_HOST'] ?? 'farmscout-online.com';
        return $protocol . $domain;
    }
    
    /**
     * Create welcome email content
     */
    private function createWelcomeEmail($user_name) {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff;">
            <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 30px 40px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">🌾 Welcome to FarmScout!</h1>
                <p style="margin: 5px 0 0 0; font-size: 16px;">Your trusted market price companion</p>
            </div>
            
            <div style="padding: 40px;">
                <h2>Hello ' . htmlspecialchars($user_name) . '!</h2>
                
                <p>Thank you for joining FarmScout Online, your trusted partner for real-time market prices from Baloan Public Market!</p>
                
                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%); border-left: 5px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #92400e;">What you can do:</h3>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li>📨 Set up price alerts for your favorite products</li>
                        <li>🛒 Create and manage shopping lists</li>
                        <li>📊 Track price history and trends</li>
                        <li>🏪 Connect with market vendors</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . $this->getCurrentDomain() . '/price-alerts.php" 
                       style="display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #059669 0%, #047857 100%); 
                              color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">
                        Set Your First Price Alert
                    </a>
                </div>
                
                <p>If you have any questions, feel free to contact us. We\'re here to help you get the best prices at Baloan Public Market!</p>
                
                <p>Happy shopping!<br><strong>The FarmScout Team</strong></p>
            </div>
            
            <div style="background-color: #f8fafc; padding: 30px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                    &copy; ' . date('Y') . ' FarmScout Online. All rights reserved.
                </p>
            </div>
        </div>';
    }
    
    /**
     * Create test email content
     */
    private function createTestEmail() {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff;">
            <div style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 30px 40px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">✅ Test Email</h1>
                <p style="margin: 5px 0 0 0; font-size: 16px;">FarmScout Online Email System</p>
            </div>
            
            <div style="padding: 40px;">
                <h2>Email System Status: Working!</h2>
                
                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%); border-left: 5px solid #f59e0b; padding: 20px; margin: 20px 0; border-radius: 8px;">
                    <p style="margin: 0; font-weight: bold; color: #92400e;">This is a test email to verify that your email system is working correctly.</p>
                </div>
                
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">Timestamp:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;">' . date('Y-m-d H:i:s') . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">Configuration:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd;">' . ($this->config['use_smtp'] ? 'SMTP' : 'PHP Mail') . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">Status:</td>
                        <td style="padding: 10px; border-bottom: 1px solid #ddd; color: #059669; font-weight: bold;">✅ Working</td>
                    </tr>
                </table>
                
                <p>If you received this email, your FarmScout Online email system is configured correctly!</p>
            </div>
            
            <div style="background-color: #f8fafc; padding: 30px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                    &copy; ' . date('Y') . ' FarmScout Online. All rights reserved.
                </p>
            </div>
        </div>';
    }
    
    
    /**
     * Log email activity
     */
    private function logEmail($to, $subject, $status, $error = null) {
        if (function_exists('logEmailActivity')) {
            logEmailActivity($to, $subject, $status, $error);
        }
    }
    
    /**
     * Get template configuration
     */
    private function getTemplateConfig() {
        $config_file = __DIR__ . '/../config/email_templates.php';
        if (file_exists($config_file)) {
            return include $config_file;
        }
        
        // Fallback configuration
        return [
            'default_price_alert_template' => 'standard',
            'available_price_alert_templates' => []
        ];
    }
    
    /**
     * Test SMTP connection
     */
    public function testSMTPConnection() {
        if (!$this->mailer || !$this->config['use_smtp']) {
            return ['success' => false, 'message' => 'SMTP not configured'];
        }
        
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }
    
}
?>
