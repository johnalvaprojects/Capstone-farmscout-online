<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/security.php';

/**
 * Whether the client expects JSON (SPA fetch / XHR) instead of an HTML redirect.
 */
function loginWantsJsonResponse(): bool {
    if (($_POST['format'] ?? '') === 'json') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json')) {
        return true;
    }
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
        return true;
    }
    return false;
}

function loginJsonError(string $message): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function loginJsonSuccess(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => true], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

$page_title = 'Login - FarmScout Online';
$page_description = 'Login to access FarmScout Online';

$error_message = '';
$success_message = '';
$redirect_input = '';

// Handle logout message
if (isset($_GET['message'])) {
    $success_message = sanitizeInput($_GET['message']);
}

// Handle error message
if (isset($_GET['error'])) {
    $error_message = sanitizeInput($_GET['error']);
}

// Preserve redirect target across POST (form action points to login.php without query string).
$incomingRedirect = $_GET['redirect'] ?? '';
if (is_string($incomingRedirect) && $incomingRedirect !== '') {
    $decodedRedirect = urldecode(trim($incomingRedirect));
    if (strpos($decodedRedirect, '?app/') !== false || strpos($decodedRedirect, '/3Fapp/') !== false) {
        $decodedRedirect = fs_classic_account_url();
    }
    if (
        $decodedRedirect === '/account'
        || $decodedRedirect === '/app/account'
        || $decodedRedirect === '/farmscout_online/app/account'
        || str_ends_with($decodedRedirect, '/app/account')
    ) {
        $decodedRedirect = fs_classic_account_url();
    }
    if (strpos($decodedRedirect, '/') === 0) {
        $redirect_input = $decodedRedirect;
    }
}

// JSON preflight for SPA/AJAX: CSRF + captcha (same session as POST login)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['format']) && $_GET['format'] === 'json')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'csrf_token' => getCSRFToken(),
        'captcha_question' => generateSimpleCaptcha('login_form'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$wantsJsonLogin = false;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $wantsJsonLogin = loginWantsJsonResponse();

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (!checkRateLimit('login_attempt', RATE_LIMIT_LOGIN_ATTEMPTS, RATE_LIMIT_LOGIN_WINDOW)) {
        $error_message = 'Too many login attempts. Please try again in a few minutes.';
        logSecurityEvent('login_rate_limited', [
            'username' => $_POST['username'] ?? null,
        ]);
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } elseif (!validateSimpleCaptcha('login_form', $_POST['captcha_answer'] ?? '')) {
            $error_message = 'Security check failed. Please try again.';
        } else {
            $user = authenticateUser($username, $password);

            if ($user) {
                logSecurityEvent('login_success', [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['user_role'],
                ]);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['user_role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Capture the markets this account manages or belongs to
                $marketAccess = getUserMarketAccess((int) $user['id']);
                $_SESSION['market_access'] = $marketAccess;

                $managedMarketIds = [];
                foreach (array_merge($marketAccess['owned'] ?? [], $marketAccess['member'] ?? []) as $marketRow) {
                    if (isset($marketRow['id'])) {
                        $managedMarketIds[] = (int) $marketRow['id'];
                    }
                }
                $_SESSION['managed_market_ids'] = $managedMarketIds;

                $defaultMarketId = null;
                if (!empty($marketAccess['owned'])) {
                    $defaultMarketId = (int) $marketAccess['owned'][0]['id'];
                } elseif (!empty($marketAccess['member'])) {
                    $defaultMarketId = (int) $marketAccess['member'][0]['id'];
                }
                $_SESSION['active_market_id'] = $defaultMarketId;

                // Handle remember me functionality
                if (isset($_POST['remember-me']) && $_POST['remember-me'] === 'on') {
                    setcookie('farmscout_username', $username, time() + (30 * 24 * 60 * 60), '/', '', false, false);
                } else {
                    setcookie('farmscout_username', '', time() - 3600, '/', '', false, false);
                }

                $redirectParam = $_POST['redirect'] ?? $_GET['redirect'] ?? null;
                $redirectParam = is_string($redirectParam) ? urldecode(trim($redirectParam)) : '';
                if (strpos($redirectParam, '?app/') !== false || strpos($redirectParam, '/3Fapp/') !== false) {
                    $redirectParam = fs_classic_account_url();
                }
                if (
                    $redirectParam === '/account'
                    || $redirectParam === '/app/account'
                    || $redirectParam === '/farmscout_online/app/account'
                    || str_ends_with($redirectParam, '/app/account')
                ) {
                    $redirectParam = fs_classic_account_url();
                }
                if ($redirectParam !== '' && strpos($redirectParam, '/') !== 0) {
                    $redirectParam = '';
                }

                // Redirect based on user role (explicit ?redirect= / POST redirect wins when provided)
                if ($user['user_role'] === 'super_admin') {
                    $message = 'Super Admin login successful';
                    $redirect = $redirectParam !== '' ? $redirectParam : 'admin-console.php';
                } elseif ($user['user_role'] === 'admin') {
                    $message = 'DTI Admin login successful';
                    $redirect = $redirectParam !== '' ? $redirectParam : 'admin-console.php';
                } elseif ($user['user_role'] === 'farmer') {
                    $message = 'Farmer Login successfully';
                    $redirect = $redirectParam !== '' ? $redirectParam : 'farmer-dashboard.php';
                } else {
                    $message = 'Login successfully';
                    $redirect = $redirectParam !== '' ? $redirectParam : fs_classic_account_url();
                }

                if ($wantsJsonLogin) {
                    loginJsonSuccess([
                        'message' => $message,
                        'redirect' => $redirect,
                        'user' => [
                            'id' => (int) $user['id'],
                            'username' => $user['username'],
                            'full_name' => $user['full_name'],
                            'user_role' => $user['user_role'],
                        ],
                    ]);
                }

                header('Location: ' . $redirect . '?message=' . urlencode($message));
                exit;
            }
            $error_message = 'Invalid username or password.';
            logSecurityEvent('login_failed', [
                'username' => $username,
            ]);
        }
    }
}

if ($wantsJsonLogin && $error_message !== '') {
    loginJsonError($error_message);
}

$login_captcha_question = generateSimpleCaptcha('login_form');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
    
    <!-- Cache busting -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Typography: match SPA (Poppins/Inter) -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #000000;
        }
        
        body {
            font-family: "Poppins", Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif !important;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        /* Align login typography with SPA */
        .custom-font-title {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            font-weight: 800;
            font-size: 2rem;
            letter-spacing: -0.01em;
        }
        
        .custom-font-subtitle {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            font-size: 0.95rem;
            letter-spacing: 0;
        }
        
        .custom-font-body {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            font-size: 0.95rem;
            letter-spacing: 0;
        }
        
        .custom-font-button {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            font-weight: 800;
            font-size: 0.9rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        
        .custom-font-label {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
        }
        
        /* Market Finder Style Inputs */
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            border: 2px solid var(--border-color) !important;
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
            padding: 0.75rem 1rem !important;
            font-size: 0.95rem !important;
            letter-spacing: 0 !important;
            border-radius: 0 !important;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none !important;
            border-color: var(--border-color) !important;
            box-shadow: none !important;
        }
        
        /* Market Finder Style Buttons */
        button[type="submit"],
        .bg-primary {
            font-family: "Poppins", Inter, system-ui, sans-serif !important;
            background-color: var(--text-color) !important;
            color: var(--bg-color) !important;
            border: 2px solid var(--border-color) !important;
            padding: 0.75rem 1.5rem !important;
            font-size: 0.9rem !important;
            font-weight: 900 !important;
            letter-spacing: 0.08em !important;
            text-transform: uppercase !important;
            transition: all 0.3s ease 0.1s !important;
        }
        
        button[type="submit"]:hover,
        .bg-primary:hover {
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
            transition: all 0.3s ease 0s !important;
        }
        
        /* Checkbox Style */
        input[type="checkbox"] {
            border: 2px solid var(--border-color) !important;
            width: 1.25rem !important;
            height: 1.25rem !important;
        }
        
        /* Three-panel sliding system */
        .left-panel-container {
            transition: transform 0.5s ease-in-out;
            transform: translateX(0);
            width: 300%; /* 3 panels side by side */
        }
        
        .left-panel-container.slide-to-register {
            transform: translateX(-33.333%);
        }
        
        .left-panel-container.slide-to-forgot {
            transform: translateX(-66.666%);
        }
        
        .left-panel-container.slide-to-login {
            transform: translateX(0);
        }
        
        /* Ensure all panels have same height */
        .panel {
            min-height: 100vh;
            width: 33.333%; /* Each panel takes 1/3 of the container */
            flex-shrink: 0;
        }
        
        /* Form container with proper spacing */
        .form-container {
            min-height: 500px;
            padding-bottom: 2rem;
            overflow: hidden; /* Hide overflow for sliding animation */
            position: relative;
        }
        
        /* Two-step registration form system (within register panel) */
        .registration-form-container {
            position: relative;
            width: 200%; /* 2 steps side by side */
            display: flex;
            transition: transform 0.5s ease-in-out;
            transform: translateX(0);
        }
        
        .registration-form-container.step-2 {
            transform: translateX(-50%);
        }
        
        .registration-step {
            width: 50%; /* Each step takes 50% of container */
            flex-shrink: 0;
            padding-right: 1rem;
        }
        
        .registration-step-2 {
            padding-left: 1rem;
        }
        
        /* Dark Notification Style - Same as My Account page */
        .slide-notification {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #000000;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            max-width: 400px;
            animation: slideInFromLeft 0.3s ease-out forwards;
            border: 1px solid #333333;
        }
        
        .slide-notification .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .slide-notification .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        
        .slide-notification .notification-text {
            flex: 1;
            color: #ffffff;
            font-weight: 500;
            font-size: 14px;
        }
        
        .slide-notification .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            padding: 6px;
            border-radius: 4px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            opacity: 0.7;
        }
        
        .slide-notification .close-btn:hover {
            background: #333333;
            opacity: 1;
        }
        
        @keyframes slideInFromLeft {
            0% {
                left: -400px;
                opacity: 0;
            }
            100% {
                left: 20px;
                opacity: 1;
            }
        }
        
        /* Auto-hide animation */
        .slide-notification.auto-hide {
            animation: slideInFromLeft 0.3s ease-out forwards, slideOutToLeft 0.3s ease-in 4.7s forwards;
        }
        
        @keyframes slideOutToLeft {
            0% {
                left: 20px;
                opacity: 1;
            }
            100% {
                left: -400px;
                opacity: 0;
            }
        }
        
    </style>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/enhancements.css?v=<?php echo time(); ?>">
    <?php if (isset($_GET['theme']) && $_GET['theme'] === 'wireframe'): ?>
    <link rel="stylesheet" href="css/login-wireframe-theme.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <?php if (isset($_GET['embed']) && $_GET['embed'] === '1'): ?>
    <style>
        body.login-embed-mode .flex.min-h-screen { min-height: 100vh; }
        body.login-embed-mode { overflow-x: hidden; }
    </style>
    <?php endif; ?>
    <style id="login-bg-video-crossfade">
        /* One place to edit crossfade length (seconds). JS reads this for early handoff timing. */
        #loginVideoPanel {
            --login-crossfade-sec: 1.5;
        }
        #loginVideoPanel .login-bg-video {
            transition: opacity calc(var(--login-crossfade-sec) * 1s) cubic-bezier(0.33, 0, 0.25, 1);
            will-change: opacity;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        @media (prefers-reduced-motion: reduce) {
            #loginVideoPanel {
                --login-crossfade-sec: 0;
            }
            #loginVideoPanel .login-bg-video {
                transition-duration: 0.01ms;
            }
        }
    </style>

    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#000000',
                        'primary-600': '#1a1a1a',
                        'primary-700': '#0d0d0d',
                        'text-primary': '#1F2937',
                        'text-secondary': '#6B7280',
                        'bg-primary': '#F9FAFB',
                        'bg-secondary': '#F3F4F6'
                    }
                }
            }
        }
    </script>
</head>
<?php
$loginBodyClasses = ['min-h-screen'];
if (isset($_GET['embed']) && $_GET['embed'] === '1') {
    $loginBodyClasses[] = 'login-embed-mode';
}
if (isset($_GET['theme']) && $_GET['theme'] === 'wireframe') {
    $loginBodyClasses[] = 'login-theme-wireframe';
}
?>
<body class="<?php echo htmlspecialchars(implode(' ', $loginBodyClasses), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Success Notification -->
    <?php if ($success_message): ?>
    <div class="slide-notification auto-hide" id="notification">
        <div class="notification-content">
            <img src="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>" alt="Success!" class="notification-icon" loading="lazy" decoding="async">
            <div class="notification-text"><?php echo htmlspecialchars($success_message); ?></div>
            <button class="close-btn" onclick="document.getElementById('notification').remove()">×</button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Two Column Layout -->
    <div class="flex min-h-screen">
        <!-- Left Panel Container - This will slide -->
        <div class="flex-1 bg-white relative overflow-hidden">
            <div class="left-panel-container flex">
                <!-- Login Panel -->
                <div class="panel flex flex-col justify-center px-8 lg:px-16 xl:px-24">
            <!-- Back Button -->
            <div class="absolute top-6 left-6 z-10">
                <a href="/farmscout_online/app/" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors" onclick="return true;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
            </div>
            
            <!-- Logo -->
            <div class="mb-8">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <img src="<?php echo htmlspecialchars(assetUrl('assets/images/gif-wazulafu-no-bg.gif')); ?>" 
                             alt="FarmScout - Tapat na Presyo" 
                             class="h-12 w-12 object-contain" 
                             loading="lazy"
                             decoding="async"
                             onerror="this.src='<?php echo assetUrl('assets/images/farmscoutlogo.png'); ?>'; this.onerror=null;" />
                    </div>
        <div>
                        <h1 class="text-2xl font-bold text-primary">FARMSCOUT</h1>
                        <p class="text-sm text-gray-600">Tapat na Presyo</p>
                    </div>
                </div>
            </div>

            <!-- Title -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 custom-font-title">Sign in</h1>
                <p class="text-gray-600 custom-font-subtitle">
                    Don't have an account? 
                    <a href="#" onclick="switchToRegister()" class="text-primary hover:text-primary-600 font-medium underline">Create now</a>
            </p>
        </div>
        
            <!-- Error/Success Messages -->
            <?php if ($error_message): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-red-700"><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            
                    <!-- Login Form -->
                    <div class="form-container">
            <form class="space-y-6" method="POST" action="login.php">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            <?php if ($redirect_input !== ''): ?>
                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_input); ?>">
                            <?php endif; ?>
                
                            <!-- Username Field -->
                <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                        Username
                    </label>
                    <input id="username" name="username" type="text" required 
                                       class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                           placeholder="Enter your username"
                           autocomplete="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? $_COOKIE['farmscout_username'] ?? ''); ?>">
                </div>
                
                            <!-- Password Field -->
                <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                        Password
                    </label>
                                <div class="relative">
                    <input id="password" name="password" type="password" required 
                                           class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors pr-12 custom-font-body"
                           placeholder="Enter your password"
                           autocomplete="current-password">
                                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                </div>

                <!-- Simple Captcha -->
                <div>
                    <label for="captcha_answer" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                        Security Check
                        <span class="ml-1 text-primary font-semibold">
                            <?php echo htmlspecialchars($login_captcha_question); ?>
                        </span>
                    </label>
                    <input id="captcha_answer" name="captcha_answer" type="text" inputmode="numeric" required
                           class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                           placeholder="Enter your answer"
                           value="<?php echo htmlspecialchars($_POST['captcha_answer'] ?? ''); ?>">
                    <p class="text-xs text-gray-500 mt-1">This quick math question keeps bots out.</p>
                </div>
                
                            <!-- Remember Me & Forgot Password -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox" 
                               class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                               <?php echo !empty($_COOKIE['farmscout_username']) ? 'checked' : ''; ?>>
                                    <label for="remember-me" class="ml-2 block text-sm text-gray-700 custom-font-label">
                            Remember me
                        </label>
                    </div>
                    
                    <div class="text-sm">
                                    <a href="#" onclick="switchToForgot()" class="font-medium text-primary hover:text-primary-600">
                                        Forgot Password?
                        </a>
                    </div>
                </div>
                
                            <!-- Sign In Button -->
                            <button type="submit" class="w-full bg-black text-white py-3 px-4 border-2 border-black font-medium hover:bg-white hover:text-black focus:outline-none transition-all custom-font-button">
                        SIGN IN
                    </button>
            
                            <?php
                            // Check if Google OAuth is configured
                            require_once 'includes/google_oauth.php';
                            $google_oauth = new GoogleOAuth();
                            $google_oauth_configured = $google_oauth->isConfigured();
                            ?>
                            
                            <?php if ($google_oauth_configured): ?>
                            <!-- OR Separator -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                                        <span class="px-2 bg-white text-gray-500 custom-font-label">OR</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Google Sign-In -->
                            <div class="mt-6">
                                <?php
                                $google_auth_url = $google_oauth->getAuthUrl($redirect_input);
                                ?>
                                <a href="<?php echo htmlspecialchars($google_auth_url); ?>" class="w-full flex items-center justify-center px-4 py-3 border-2 border-black bg-white text-sm font-medium text-black hover:bg-black hover:text-white focus:outline-none transition-all custom-font-button">
                                    <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google" class="w-5 h-5 mr-3">
                                    CONTINUE WITH GOOGLE
                                </a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Register Panel -->
                <div class="panel flex flex-col justify-center px-8 lg:px-16 xl:px-24">
                    <!-- Back Button -->
                    <div class="absolute top-6 left-6 z-10">
                        <a href="/farmscout_online/app/" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors" onclick="return true;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </a>
                    </div>
                    
                    <!-- Logo -->
                    <div class="mb-8">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <img src="<?php echo htmlspecialchars(assetUrl('assets/images/gif-wazulafu-no-bg.gif')); ?>" 
                                     alt="FarmScout - Tapat na Presyo" 
                                     class="h-12 w-12 object-contain" 
                                     onerror="this.src='<?php echo assetUrl('assets/images/farmscoutlogo.png'); ?>'; this.onerror=null;" />
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-primary">FARMSCOUT</h1>
                                <p class="text-sm text-gray-600">Tapat na Presyo</p>
                            </div>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2 custom-font-title">Create Account</h1>
                        <p class="text-gray-600 custom-font-subtitle">
                            Already have an account? 
                            <a href="#" onclick="switchToLogin()" class="text-primary hover:text-primary-600 font-medium underline">Sign in</a>
                        </p>
                    </div>
                    
                    <!-- Registration Form - Two Step Sliding -->
                    <div class="form-container">
                        <div class="registration-form-container" id="loginRegistrationFormContainer">
                            <!-- Step 1: Basic Information -->
                            <div class="registration-step">
                                <form id="loginStep1Form" class="space-y-6">
                                    <!-- Full Name Field -->
                                    <div>
                                        <label for="login_full_name" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Full Name <span class="text-red-500">*</span>
                                        </label>
                                        <input id="login_full_name" name="full_name" type="text" required 
                                               class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                                               placeholder="Enter your full name"
                                               autocomplete="name"
                                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                                    </div>
                                    
                                    <!-- Username Field -->
                                    <div>
                                        <label for="login_reg_username" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Username <span class="text-red-500">*</span>
                                        </label>
                                        <input id="login_reg_username" name="username" type="text" required 
                                               class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                                               placeholder="Choose a username"
                                               autocomplete="username"
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                        <p class="mt-1 text-sm text-gray-500 custom-font-body">3+ characters, letters, numbers, and underscores only</p>
                                    </div>
                                    
                                    <!-- Email Field -->
                                    <div>
                                        <label for="login_email" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Email Address <span class="text-red-500">*</span>
                                        </label>
                                        <input id="login_email" name="email" type="email" required 
                                               class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                                               placeholder="example@gmail.com"
                                               autocomplete="email"
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                    
                                    <!-- Password Field -->
                                    <div>
                                        <label for="login_reg_password" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Password <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input id="login_reg_password" name="password" type="password" required 
                                                   class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors pr-12 custom-font-body"
                                                   placeholder="Create a password"
                                                   autocomplete="new-password">
                                            <button type="button" onclick="toggleRegisterPassword(event)" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                                <svg id="reg-eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Confirm Password Field -->
                                    <div>
                                        <label for="login_confirm_password" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Confirm Password <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <input id="login_confirm_password" name="confirm_password" type="password" required 
                                                   class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors pr-12 custom-font-body"
                                                   placeholder="Confirm your password"
                                                   autocomplete="new-password">
                                            <button type="button" onclick="toggleConfirmPassword(event)" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                                <svg id="confirm-eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500 custom-font-body">Minimum 6 characters</p>
                                    </div>
                                    
                                    <!-- Proceed Button (Step 1) -->
                                    <button type="button" onclick="validateLoginStep1(event)" class="w-full bg-black text-white py-3 px-4 border-2 border-black font-medium hover:bg-white hover:text-black focus:outline-none transition-all custom-font-button">
                                        PROCEED
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Step 2: Role Selection + Security Check -->
                            <div class="registration-step registration-step-2">
                                <?php 
                                // Generate captcha for registration form
                                $register_captcha_question = generateSimpleCaptcha('register_form');
                                ?>
                                <form id="loginStep2Form" class="space-y-6" method="POST" action="register.php">
                                    <input type="hidden" name="action" value="register">
                                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                                    <!-- Hidden fields from step 1 -->
                                    <input type="hidden" id="login_step2_full_name" name="full_name">
                                    <input type="hidden" id="login_step2_username" name="username">
                                    <input type="hidden" id="login_step2_email" name="email">
                                    <input type="hidden" id="login_step2_password" name="password">
                                    <input type="hidden" id="login_step2_confirm_password" name="confirm_password">
                                    
                                    <!-- Title for Step 2 -->
                                    <div class="mb-6">
                                        <h2 class="text-2xl font-bold text-gray-900 mb-2 custom-font-title">Choose your role</h2>
                                        <p class="text-gray-600 custom-font-body">Select how you'll use FarmScout</p>
                                    </div>
                                    
                                    <!-- User Role Selection -->
                                    <div>
                                        <div class="space-y-2">
                                            <label class="flex items-center p-4 border-2 border-black rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                                                <input type="radio" name="user_role" value="consumer" checked 
                                                       class="w-4 h-4 text-primary focus:ring-black border-black">
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900 custom-font-label">Consumer</div>
                                                    <div class="text-xs text-gray-500 custom-font-body">Browse products and set price alerts</div>
                                                </div>
                                            </label>
                                            <label class="flex items-center p-4 border-2 border-black rounded-lg hover:bg-gray-100 cursor-pointer transition-colors">
                                                <input type="radio" name="user_role" value="farmer" 
                                                       class="w-4 h-4 text-primary focus:ring-black border-black">
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900 custom-font-label">Farmer</div>
                                                    <div class="text-xs text-gray-500 custom-font-body">Sell products at markets (requires admin verification)</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Simple Captcha -->
                                    <div>
                                        <label for="login_captcha_answer" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                            Security Check
                                            <span class="ml-1 text-primary font-semibold">
                                                <?php echo htmlspecialchars($register_captcha_question); ?>
                                            </span>
                                        </label>
                                        <input id="login_captcha_answer" name="captcha_answer" type="text" inputmode="numeric" required
                                               class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors custom-font-body"
                                               placeholder="Enter your answer"
                                               value="<?php echo htmlspecialchars($_POST['captcha_answer'] ?? ''); ?>">
                                        <p class="mt-1 text-xs text-gray-500 custom-font-body">Quick math challenge to keep bots away.</p>
                                    </div>
                                    
                                    <!-- Create Account Button (Step 2) -->
                                    <button type="submit" class="w-full bg-black text-white py-3 px-4 border-2 border-black font-medium hover:bg-white hover:text-black focus:outline-none transition-all custom-font-button">
                                        CREATE ACCOUNT
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Forgot Password Panel -->
                <div class="panel flex flex-col justify-center px-8 lg:px-16 xl:px-24">
                    <!-- Back Button -->
                    <div class="absolute top-6 left-6 z-10">
                        <a href="/farmscout_online/app/" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors" onclick="return true;">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </a>
                    </div>
                    
                    <!-- Logo -->
                    <div class="mb-8">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <img src="<?php echo htmlspecialchars(assetUrl('assets/images/gif-wazulafu-no-bg.gif')); ?>" 
                                     alt="FarmScout - Tapat na Presyo" 
                                     class="h-12 w-12 object-contain" 
                                     onerror="this.src='<?php echo assetUrl('assets/images/farmscoutlogo.png'); ?>'; this.onerror=null;" />
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-primary">FARMSCOUT</h1>
                                <p class="text-sm text-gray-600">Tapat na Presyo</p>
                            </div>
                    </div>
                </div>
                
                    <!-- Title -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2 custom-font-title">Forgot Password?</h1>
                        <p class="text-gray-600 custom-font-subtitle">
                            No worries! Enter your email address and we'll send you a link to reset your password.
                        </p>
                    </div>
                    
                    <!-- Forgot Password Form -->
                    <div class="form-container">
                        <form class="space-y-6" method="POST" action="forgot-password.php">
                            <input type="hidden" name="action" value="forgot_password">
                            
                            <!-- Email Field -->
                            <div>
                                <label for="forgot_email" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Email Address
                                </label>
                                <input id="forgot_email" name="email" type="email" required 
                                       class="w-full px-4 py-3 border-2 border-black focus:outline-none transition-colors custom-font-body"
                                       placeholder="Enter your email address"
                                       autocomplete="email">
                            </div>
                            
                            <!-- Send Reset Link Button -->
                            <button type="submit" class="w-full bg-black text-white py-3 px-4 border-2 border-black font-medium hover:bg-white hover:text-black focus:outline-none transition-all custom-font-button">
                                SEND RESET LINK
                            </button>
                            
                            <!-- Back to Login -->
                            <div class="text-center">
                                <a href="#" onclick="switchToLogin()" class="text-sm text-gray-600 hover:text-primary transition-colors custom-font-body">
                                    Remember your password? Sign in here
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Video Background (farmlogin → crossfade → farmlogin2, repeat) -->
        <div class="hidden lg:flex lg:flex-1 relative overflow-hidden bg-black" id="loginVideoPanel">
            <video id="loginBgVideo1" class="login-bg-video absolute inset-0 z-[1] h-full w-full object-cover opacity-100" muted playsinline preload="auto" autoplay aria-hidden="true">
                <source src="<?php echo htmlspecialchars(assetUrl('assets/video/farmlogin.mp4'), ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
            </video>
            <video id="loginBgVideo2" class="login-bg-video absolute inset-0 z-[2] h-full w-full object-cover opacity-0" muted playsinline preload="auto" aria-hidden="true">
                <source src="<?php echo htmlspecialchars(assetUrl('assets/video/farmlogin2.mp4'), ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
            </video>
            <div class="pointer-events-none absolute inset-0 z-[3] bg-black/20"></div>
        </div>
    </div>
</div>

    <!-- JavaScript -->
<script>
        let currentForm = 'login'; // Track current form state
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
        `;
    } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
        
        function toggleRegisterPassword(event) {
            // Get the button that was clicked
            const button = event ? event.currentTarget : document.querySelector('button[onclick*="toggleRegisterPassword"]');
            // Find the password input in the same container
            const container = button ? button.closest('.relative') : null;
            const passwordInput = container ? container.querySelector('input[type="password"], input[type="text"]') : (document.getElementById('login_reg_password') || document.getElementById('reg_password'));
            const eyeIcon = container ? container.querySelector('svg') : document.getElementById('reg-eye-icon');
            
            if (passwordInput && eyeIcon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                    `;
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    `;
                }
            }
        }
        
        function toggleConfirmPassword(event) {
            // Get the button that was clicked
            const button = event ? event.currentTarget : document.querySelector('button[onclick*="toggleConfirmPassword"]');
            // Find the password input in the same container
            const container = button ? button.closest('.relative') : null;
            const passwordInput = container ? container.querySelector('input[type="password"], input[type="text"]') : (document.getElementById('login_confirm_password') || document.getElementById('confirm_password'));
            const eyeIcon = container ? container.querySelector('svg') : document.getElementById('confirm-eye-icon');
            
            if (passwordInput && eyeIcon) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                    `;
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    `;
                }
            }
        }
        
        function switchToRegister() {
            if (currentForm === 'login') {
                const container = document.querySelector('.left-panel-container');
                container.classList.remove('slide-to-login', 'slide-to-forgot');
                container.classList.add('slide-to-register');
                currentForm = 'register';
            }
        }
        
        function switchToLogin() {
            if (currentForm !== 'login') {
                const container = document.querySelector('.left-panel-container');
                container.classList.remove('slide-to-register', 'slide-to-forgot');
                container.classList.add('slide-to-login');
                currentForm = 'login';
            }
        }
        
        // Multi-step registration form handling (for login.php)
        function validateLoginStep1(event) {
            if (event) {
                event.preventDefault();
            }
            
            const fullName = document.getElementById('login_full_name').value.trim();
            const username = document.getElementById('login_reg_username').value.trim();
            const email = document.getElementById('login_email').value.trim();
            const password = document.getElementById('login_reg_password').value;
            const confirmPassword = document.getElementById('login_confirm_password').value;
            
            // Basic validation
            if (!fullName || !username || !email || !password || !confirmPassword) {
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            // Store values in hidden fields for step 2
            document.getElementById('login_step2_full_name').value = fullName;
            document.getElementById('login_step2_username').value = username;
            document.getElementById('login_step2_email').value = email;
            document.getElementById('login_step2_password').value = password;
            document.getElementById('login_step2_confirm_password').value = confirmPassword;
            
            // Slide to step 2
            document.getElementById('loginRegistrationFormContainer').classList.add('step-2');
            
            return false;
        }
        
        function switchToForgot() {
            if (currentForm === 'login') {
                const container = document.querySelector('.left-panel-container');
                container.classList.remove('slide-to-login', 'slide-to-register');
                container.classList.add('slide-to-forgot');
                currentForm = 'forgot';
            }
        }

        (function initLoginBgVideoCrossfade() {
            const v1 = document.getElementById('loginBgVideo1');
            const v2 = document.getElementById('loginBgVideo2');
            const loginVideoPanel = document.getElementById('loginVideoPanel');
            if (!v1 || !v2) return;

            var LOGIN_CROSSFADE_SEC = 1.5;
            if (loginVideoPanel) {
                var parsedSec = parseFloat(
                    getComputedStyle(loginVideoPanel).getPropertyValue('--login-crossfade-sec').trim()
                );
                if (isFinite(parsedSec) && parsedSec >= 0) {
                    LOGIN_CROSSFADE_SEC = parsedSec;
                }
            }

            function playSafe(v) {
                const p = v.play();
                if (p && typeof p.catch === 'function') {
                    p.catch(function () {});
                }
            }

            /** Let the incoming clip decode a frame while hidden, then fade (smoother than fade+play together). */
            function afterIncomingReady(incoming, runFade) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(runFade);
                });
            }

            /** Start crossfade this many seconds before the clip ends (from --login-crossfade-sec on #loginVideoPanel). */
            function fadeStartTime(duration) {
                if (!duration || !isFinite(duration) || duration <= 0) return Infinity;
                var lead = LOGIN_CROSSFADE_SEC;
                if (lead <= 0.01) {
                    return Math.max(0, duration - 0.02);
                }
                if (duration > lead + 0.15) return duration - lead;
                return Math.max(0.08, duration * 0.22);
            }

            var loginVidSwitching = false;

            /** Keep outgoing video playing during the crossfade; pause only after opacity hits 0. */
            function pauseAfterFadeOut(videoEl, onPaused) {
                function onEnd(e) {
                    if (e.target !== videoEl || e.propertyName !== 'opacity') return;
                    videoEl.removeEventListener('transitionend', onEnd);
                    videoEl.pause();
                    if (typeof onPaused === 'function') onPaused();
                }
                videoEl.addEventListener('transitionend', onEnd);
            }

            function showFirst() {
                v1.currentTime = 0;
                playSafe(v1);
                afterIncomingReady(v1, function () {
                    pauseAfterFadeOut(v2, function () {
                        loginVidSwitching = false;
                    });
                    v1.classList.remove('opacity-0');
                    v1.classList.add('opacity-100');
                    v2.classList.remove('opacity-100');
                    v2.classList.add('opacity-0');
                });
            }

            function showSecond() {
                v2.currentTime = 0;
                playSafe(v2);
                afterIncomingReady(v2, function () {
                    pauseAfterFadeOut(v1, function () {
                        loginVidSwitching = false;
                    });
                    v2.classList.remove('opacity-0');
                    v2.classList.add('opacity-100');
                    v1.classList.remove('opacity-100');
                    v1.classList.add('opacity-0');
                });
            }

            function armSwitchToSecond() {
                if (loginVidSwitching) return;
                loginVidSwitching = true;
                showSecond();
            }

            function armSwitchToFirst() {
                if (loginVidSwitching) return;
                loginVidSwitching = true;
                showFirst();
            }

            v1.addEventListener('timeupdate', function () {
                if (loginVidSwitching) return;
                var d = v1.duration;
                if (v1.currentTime >= fadeStartTime(d)) armSwitchToSecond();
            });
            v2.addEventListener('timeupdate', function () {
                if (loginVidSwitching) return;
                var d = v2.duration;
                if (v2.currentTime >= fadeStartTime(d)) armSwitchToFirst();
            });

            v1.addEventListener('ended', function () {
                if (loginVidSwitching) return;
                armSwitchToSecond();
            });
            v2.addEventListener('ended', function () {
                if (loginVidSwitching) return;
                armSwitchToFirst();
            });
        })();
        
</script>


</body>
</html>
