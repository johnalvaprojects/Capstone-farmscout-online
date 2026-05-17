<?php
// Enhanced Security Functions for FarmScout Online

// Rate limiting configuration
define('RATE_LIMIT_REQUESTS', 100); // requests per window
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds
define('RATE_LIMIT_LOGIN_ATTEMPTS', 5); // login attempts per window
define('RATE_LIMIT_LOGIN_WINDOW', 900); // 15 minutes

// Security headers
function setSecurityHeaders() {
    // Prevent XSS attacks
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://maps.googleapis.com https://maps.gstatic.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
           "font-src 'self' https://fonts.gstatic.com; " .
           "img-src 'self' data: https:; " .
           "connect-src 'self' https://maps.googleapis.com https://maps.gstatic.com; " .
           "frame-ancestors 'none';";
    
    header("Content-Security-Policy: $csp");
    
    // HSTS (HTTP Strict Transport Security) - only for HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Enhanced input validation
function validateInput($data, $type = 'string', $options = []) {
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
            
        case 'price':
            return is_numeric($data) && $data >= 0 && $data <= 999999.99;
            
        case 'integer':
            $min = $options['min'] ?? 0;
            $max = $options['max'] ?? PHP_INT_MAX;
            return is_numeric($data) && $data >= $min && $data <= $max;
            
        case 'string':
            $max_length = $options['max_length'] ?? 255;
            $min_length = $options['min_length'] ?? 0;
            return is_string($data) && 
                   strlen($data) >= $min_length && 
                   strlen($data) <= $max_length;
                   
        case 'phone':
            return preg_match('/^[\+]?[0-9\s\-\(\)]{10,15}$/', $data);
            
        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL) !== false;
            
        default:
            return false;
    }
}

// Enhanced sanitization - function moved to enhanced_functions.php to avoid conflicts

// Enhanced rate limiting - function moved to enhanced_functions.php to avoid conflicts

// Login attempt tracking
function trackLoginAttempt($username, $success = false) {
    $key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }
    
    $_SESSION[$key][] = [
        'username' => $username,
        'success' => $success,
        'timestamp' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    // Clean old attempts (older than 15 minutes)
    $window_start = time() - RATE_LIMIT_LOGIN_WINDOW;
    $_SESSION[$key] = array_filter(
        $_SESSION[$key],
        function($attempt) use ($window_start) {
            return $attempt['timestamp'] > $window_start;
        }
    );
    
    // Check if too many failed attempts
    $failed_attempts = array_filter($_SESSION[$key], function($attempt) {
        return !$attempt['success'];
    });
    
    if (count($failed_attempts) >= RATE_LIMIT_LOGIN_ATTEMPTS) {
        logSecurityEvent('login_brute_force', [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'failed_attempts' => count($failed_attempts)
        ]);
        return false;
    }
    
    return true;
}

// Security event logging
function logSecurityEvent($event_type, $data = []) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $data
    ];
    
    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
}

// CSRF token generation and validation - functions moved to enhanced_functions.php to avoid conflicts

// SQL injection prevention
function escapeSQL($data) {
    if (is_array($data)) {
        return array_map('escapeSQL', $data);
    }
    
    return addslashes($data);
}

// XSS prevention
function preventXSS($data) {
    if (is_array($data)) {
        return array_map('preventXSS', $data);
    }
    
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// File upload security
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2097152) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'No file uploaded';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    if (!isset($allowed_mimes[$file_extension]) || $mime_type !== $allowed_mimes[$file_extension]) {
        $errors[] = 'Invalid file content';
    }
    
    return $errors;
}

// Session security
function secureSession() {
    // Set secure session parameters (only if session hasn't started yet)
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        ini_set('session.use_strict_mode', 1);
    }
    
    // Regenerate session ID periodically (only if session is active)
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

// IP whitelist/blacklist
function checkIPAccess() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Blacklist (block these IPs)
    $blacklist = [
        // Add known malicious IPs here
    ];
    
    if (in_array($ip, $blacklist)) {
        logSecurityEvent('blocked_ip_access', ['ip' => $ip]);
        http_response_code(403);
        exit('Access denied');
    }
    
    // Whitelist (only allow these IPs for admin access)
    $whitelist = [
        // Add trusted IPs here for admin access
    ];
    
    if (!empty($whitelist) && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin'], true)) {
        if (!in_array($ip, $whitelist)) {
            logSecurityEvent('unauthorized_admin_access', ['ip' => $ip]);
            session_destroy();
            http_response_code(403);
            exit('Access denied');
        }
    }
}

// Database security
function secureDatabaseQuery($query, $params = []) {
    // Log potentially dangerous queries
    $dangerous_patterns = [
        '/DROP\s+TABLE/i',
        '/DELETE\s+FROM/i',
        '/UPDATE\s+.*\s+SET/i',
        '/INSERT\s+INTO/i',
        '/ALTER\s+TABLE/i'
    ];
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $query)) {
            logSecurityEvent('dangerous_query_attempt', [
                'query' => $query,
                'params' => $params,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
    }
    
    return true;
}

// Initialize security
function initSecurity() {
    setSecurityHeaders();
    secureSession();
    checkIPAccess();
}

// Error handling
function handleSecurityError($message, $code = 403) {
    logSecurityEvent('security_error', [
        'message' => $message,
        'code' => $code,
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);
    
    http_response_code($code);
    
    if ($code === 403) {
        echo json_encode(['error' => 'Access denied']);
    } else {
        echo json_encode(['error' => 'Security violation']);
    }
    
    exit;
}

// Security functions ready for use
// Call initSecurity() manually when needed
?>
