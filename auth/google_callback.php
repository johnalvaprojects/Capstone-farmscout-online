<?php
/**
 * Google OAuth Callback Handler
 */

require_once __DIR__ . '/../includes/google_oauth.php';

/**
 * Normalize post-OAuth redirect (same rules as login.php).
 */
function google_oauth_normalize_redirect(?string $path): string {
    if (!is_string($path) || $path === '') {
        return '';
    }
    $path = urldecode(trim($path));
    if (strpos($path, '?app/') !== false || strpos($path, '/3Fapp/') !== false) {
        return function_exists('fs_classic_account_url') ? fs_classic_account_url() : '/user-account.php';
    }
    if (
        $path === '/account'
        || $path === '/app/account'
        || $path === '/farmscout_online/app/account'
        || str_ends_with($path, '/app/account')
    ) {
        return function_exists('fs_classic_account_url') ? fs_classic_account_url() : '/user-account.php';
    }
    if (strpos($path, '/') !== 0) {
        return '';
    }
    if (preg_match('#^https?://#i', $path) || strpos($path, '//') !== false) {
        return '';
    }
    return $path;
}

try {
    // Check if we have the authorization code
    if (!isset($_GET['code'])) {
        throw new Exception('Authorization code not received');
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Initialize Google OAuth
    $google_oauth = new GoogleOAuth();
    
    // Handle the callback
    $user = $google_oauth->handleCallback($code, $state);

    $saved = google_oauth_normalize_redirect($_SESSION['oauth_login_redirect'] ?? '');
    unset($_SESSION['oauth_login_redirect']);

    $user_role = $user['user_role'] ?? '';
    if ($user_role === '' || $user_role === null) {
        $legacy = $user['role'] ?? '';
        if ($legacy === 'admin') {
            $user_role = 'admin';
        } elseif ($legacy === 'vendor') {
            $user_role = 'farmer';
        } else {
            $user_role = 'consumer';
        }
    }

    $welcome = 'Welcome to FarmScout, ' . ($user['username'] ?? 'member') . '! You are now logged in.';
    if ($saved !== '') {
        $sep = strpos($saved, '?') !== false ? '&' : '?';
        $redirect_url = $saved . $sep . 'message=' . urlencode($welcome);
    } elseif ($user_role === 'super_admin' || $user_role === 'admin') {
        $redirect_url = '../admin-console.php?message=' . urlencode('Welcome back, ' . ($user['username'] ?? '') . '! You have been logged in successfully.');
    } elseif ($user_role === 'farmer') {
        $redirect_url = '../farmer-dashboard.php?message=' . urlencode('Welcome back, ' . ($user['username'] ?? '') . '! You have been logged in successfully.');
    } else {
        $classic = function_exists('fs_classic_account_url') ? fs_classic_account_url() : '../user-account.php';
        $redirect_url = $classic . (str_contains($classic, '?') ? '&' : '?') . 'message=' . urlencode($welcome);
    }
    
    header('Location: ' . $redirect_url);
    exit;
    
} catch (Throwable $e) {
    // Log the error
    error_log('Google OAuth Error: ' . $e->getMessage());
    
    // Redirect to login page with error message
    header('Location: ../login.php?error=' . urlencode('Google authentication failed: ' . $e->getMessage()));
    exit;
}
?>
