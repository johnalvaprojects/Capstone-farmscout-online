<?php
/**
 * Simple Email Class for FarmScout Online
 * Handles email sending for price alerts and notifications
 */

class SimpleMailer {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    private $use_smtp;
    
    public function __construct($config = []) {
        $this->smtp_host = $config['smtp_host'] ?? 'localhost';
        $this->smtp_port = $config['smtp_port'] ?? 587;
        $this->smtp_username = $config['smtp_username'] ?? '';
        $this->smtp_password = $config['smtp_password'] ?? '';
        $this->from_email = $config['from_email'] ?? 'noreply@farmscout.com';
        $this->from_name = $config['from_name'] ?? 'FarmScout Online';
        $this->use_smtp = $config['use_smtp'] ?? false;
    }
    
    /**
     * Send email using PHP's built-in mail function or SMTP
     */
    public function send($to, $subject, $body, $is_html = true) {
        try {
            if ($this->use_smtp) {
                return $this->sendSMTP($to, $subject, $body, $is_html);
            } else {
                return $this->sendPHP($to, $subject, $body, $is_html);
            }
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHP's built-in mail function
     */
    private function sendPHP($to, $subject, $body, $is_html = true) {
        $headers = [
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: FarmScout Online',
            'MIME-Version: 1.0'
        ];
        
        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Send email using SMTP (basic implementation)
     */
    private function sendSMTP($to, $subject, $body, $is_html = true) {
        // For production, consider using a proper SMTP library like PHPMailer
        // This is a simplified implementation for development
        
        if (!$this->smtp_username || !$this->smtp_password) {
            throw new Exception("SMTP credentials not configured");
        }
        
        // Use PHP's mail() function with additional headers for now
        // In production, implement proper SMTP authentication
        $headers = [
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: FarmScout Online SMTP',
            'MIME-Version: 1.0',
            'X-Priority: 3',
            'X-MSMail-Priority: Normal'
        ];
        
        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Validate email address
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Create email template wrapper
     */
    public function wrapTemplate($content, $title = 'FarmScout Online') {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background-color: #2D5016; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px 20px; }
        .footer { background-color: #f8f9fa; color: #6c757d; padding: 20px; text-align: center; font-size: 14px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #2D5016; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .alert-box { background-color: #e7f5e1; border-left: 4px solid #2D5016; padding: 15px; margin: 20px 0; }
        .price { font-size: 24px; font-weight: bold; color: #2D5016; }
        .old-price { text-decoration: line-through; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌾 FarmScout Online</h1>
            <p>Tapat na Presyo • Trusted Pricing</p>
        </div>
        <div class="content">
            ' . $content . '
        </div>
        <div class="footer">
            <p>&copy; 2025 FarmScout Online. All rights reserved.</p>
            <p>Baloan Public Market, Real-time Price Monitoring</p>
            <p><a href="#" style="color: #2D5016;">Unsubscribe</a> | <a href="#" style="color: #2D5016;">Update Preferences</a></p>
        </div>
    </div>
</body>
</html>';
    }
}

/**
 * Email Template Functions
 */

function createPriceAlertEmail($product, $alert_type, $target_price, $current_price) {
    $price_direction = '';
    $alert_message = '';
    
    switch ($alert_type) {
        case 'below':
            $price_direction = 'dropped below';
            $alert_message = "Great news! The price has dropped below your target price.";
            break;
        case 'above':
            $price_direction = 'risen above';
            $alert_message = "Price alert! The price has risen above your target price.";
            break;
        case 'change':
            $price_direction = 'changed to';
            $alert_message = "Price update! The price has changed for this product.";
            break;
    }
    
    $content = '
    <h2>Price Alert: ' . htmlspecialchars($product['filipino_name']) . '</h2>
    <div class="alert-box">
        <p><strong>' . $alert_message . '</strong></p>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Product:</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($product['filipino_name']) . ' (' . htmlspecialchars($product['name']) . ')</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Category:</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">' . htmlspecialchars($product['category_filipino'] ?? 'N/A') . '</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Target Price:</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">₱' . number_format($target_price, 2) . ' per ' . htmlspecialchars($product['unit']) . '</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><strong>Current Price:</strong></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><span class="price">₱' . number_format($current_price, 2) . '</span> per ' . htmlspecialchars($product['unit']) . '</td>
        </tr>
    </table>
    
    <p>The price has ' . $price_direction . ' ₱' . number_format($target_price, 2) . '.</p>
    
    <p style="text-align: center; margin: 30px 0;">
        <a href="' . getCurrentDomain() . '/market-finder.php" class="btn">View All Prices</a>
    </p>
    
    <p><strong>About this alert:</strong><br>
    You are receiving this email because you set up a price alert for this product. 
    If you no longer wish to receive alerts for this product, you can manage your alerts on our website.</p>
    ';
    
    return $content;
}

function createWelcomeEmail($user_name) {
    $content = '
    <h2>Welcome to FarmScout Online!</h2>
    
    <p>Hello ' . htmlspecialchars($user_name) . ',</p>
    
    <p>Thank you for joining FarmScout Online, your trusted partner for real-time market prices from Baloan Public Market!</p>
    
    <div class="alert-box">
        <p><strong>What you can do with your account:</strong></p>
        <ul>
            <li>📨 Set up price alerts for your favorite products</li>
            <li>🛒 Create and manage shopping lists</li>
            <li>📊 Track price history and trends</li>
            <li>🏪 Connect with market vendors</li>
        </ul>
    </div>
    
    <p style="text-align: center; margin: 30px 0;">
        <a href="' . getCurrentDomain() . '/price-alerts.php" class="btn">Set Your First Price Alert</a>
    </p>
    
    <p>If you have any questions, feel free to contact us. We\'re here to help you get the best prices at Baloan Public Market!</p>
    
    <p>Happy shopping!<br>
    <strong>The FarmScout Team</strong></p>
    ';
    
    return $content;
}

function getCurrentDomain() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

?>