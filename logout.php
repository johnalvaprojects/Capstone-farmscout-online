<?php
require_once 'includes/enhanced_functions.php';

// Handle logout
if (isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'User';
    
    // Try to log the logout (if function exists)
    if (function_exists('logSecurityEvent')) {
        try {
            logSecurityEvent('user_logout', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // If logging fails, continue with logout
            error_log("Logout logging failed: " . $e->getMessage());
        }
    }
    
    // Destroy session
    session_destroy();
    
    // Keep username cookie for autofill (don't clear it on logout)
    // User can uncheck "Remember me" on login page to clear it
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    $message = "You have been logged out successfully, $username.";
} else {
    $message = "You were not logged in.";
}

// Redirect to login page with message
header("Location: login.php?message=" . urlencode($message));
exit;
?>
