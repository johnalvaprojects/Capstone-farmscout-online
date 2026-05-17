<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/security.php';

$page_title = 'Register - FarmScout Online';
$page_description = 'Create your FarmScout account to receive price alerts';

$error_message = '';
$success_message = '';

// Initialize variables
$error_message = '';
$success_message = '';
$form_data = [];

// Generate captcha only on GET requests (initial page load)
// On POST requests, retrieve the existing captcha question from session
// This prevents regenerating the captcha when processing POST (which would invalidate the user's answer)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $register_captcha_question = generateSimpleCaptcha('register_form');
} else {
    // On POST, retrieve the existing question from session
    if (isset($_SESSION['simple_captcha_questions']['register_form'])) {
        $register_captcha_question = getSimpleCaptchaQuestion('register_form');
    } else {
        // If no question exists (shouldn't happen), generate a new one
        $register_captcha_question = generateSimpleCaptcha('register_form');
    }
}

// Check for session messages (from redirect after successful registration only)
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
    // Clear form data on success
    $form_data = [];
    // Regenerate captcha for fresh page
    $register_captcha_question = generateSimpleCaptcha('register_form');
} else {
    // On error or initial load, preserve form data from POST or session
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        // Use POST data directly (form is still on same page)
        $form_data = $_POST;
    } elseif (isset($_SESSION['registration_form_data'])) {
        // Use session data (from redirect)
        $form_data = $_SESSION['registration_form_data'];
        unset($_SESSION['registration_form_data']);
    }
    
    // Check for error message
    if (isset($_SESSION['registration_error'])) {
        $error_message = $_SESSION['registration_error'];
        unset($_SESSION['registration_error']);
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        $form_data = $_POST;
    } elseif (!checkRateLimit('registration_attempt', 3, 1800)) {
        $error_message = 'Too many registration attempts. Please wait a few minutes before trying again.';
        $form_data = $_POST;
        logSecurityEvent('registration_rate_limited', [
            'username' => $_POST['username'] ?? null,
            'email' => $_POST['email'] ?? null,
        ]);
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitizeInput($_POST['full_name'] ?? '');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            $error_message = 'Please fill in all required fields.';
            $form_data = $_POST;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
            $form_data = $_POST;
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
            $form_data = $_POST;
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
            $form_data = $_POST;
        } elseif (strlen($username) < 3) {
            $error_message = 'Username must be at least 3 characters long.';
            $form_data = $_POST;
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error_message = 'Username can only contain letters, numbers, and underscores.';
            $form_data = $_POST;
        } elseif (!validateSimpleCaptcha('register_form', $_POST['captcha_answer'] ?? '')) {
            $error_message = 'Security check failed. Please try again.';
            $form_data = $_POST;
            // Regenerate captcha for retry (already generated at top, but regenerate on error)
            $register_captcha_question = generateSimpleCaptcha('register_form');
        } else {
            // Get user role (default to consumer if not set)
            $user_role = sanitizeInput($_POST['user_role'] ?? 'consumer');
            if (!in_array($user_role, ['consumer', 'farmer'])) {
                $user_role = 'consumer'; // Default to consumer if invalid role
            }
            
            // Attempt registration
            $result = registerUser($username, $email, $password, $full_name, $user_role);
            
            if ($result && $result['success']) {
                // Different messages based on role
                if ($user_role === 'farmer') {
                    // Send welcome email to farmers (informational only - no email verification needed)
                    // Email will be sent if email is configured, but registration works either way
                    $email_sent = sendFarmerWelcomeEmail($email, $username);
                    if ($email_sent) {
                        error_log("Farmer welcome email sent successfully to $email for user $username");
                    } else {
                        error_log("Farmer welcome email failed to send to $email for user $username");
                    }
                    $_SESSION['registration_success'] = "Registration successful! Your farmer account is pending admin verification. Please wait for admin approval - you'll be able to add products once verified.";
                } else {
                    // Send verification email for consumers
                    if (sendVerificationEmail($email, $username, $result['verification_token'])) {
                        $_SESSION['registration_success'] = "Registration successful! Please check your email ($email) and click the verification link to activate your account.";
                    } else {
                        // Registration successful but email failed - show manual verification option
                        $verification_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $result['verification_token'];
                        $_SESSION['registration_success'] = "Registration successful! However, we couldn't send the verification email. You can manually verify your account by clicking this link: <a href='" . $verification_url . "' class='text-primary underline'>Verify Account</a>";
                    }
                }
                logSecurityEvent('registration_success', [
                    'user_id' => $result['user_id'],
                    'username' => $username,
                    'email' => $email,
                    'user_role' => $user_role,
                ]);
                // Redirect to prevent form resubmission
                header('Location: register.php?registered=1');
                exit;
            } else {
                $error_message = 'Registration failed. Username or email may already be in use.';
                $form_data = $_POST;
                logSecurityEvent('registration_failed', [
                    'username' => $username,
                    'email' => $email,
                ]);
            }
        }
    }
}

// Captcha is always generated at the top, so it's always available
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
    
    <!-- Custom Fonts - Market Finder Style -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
        
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
            font-family: 'VT323', monospace !important;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        /* Apply Market Finder fonts */
        .custom-font-title {
            font-family: 'VT323', monospace !important;
            font-weight: bold;
            font-size: 2rem;
            letter-spacing: 0.05em;
        }
        
        .custom-font-subtitle {
            font-family: 'VT323', monospace !important;
            font-size: 1.1rem;
            letter-spacing: 0.05em;
        }
        
        .custom-font-body {
            font-family: 'VT323', monospace !important;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }
        
        .custom-font-button {
            font-family: 'VT323', monospace !important;
            font-weight: bold;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }
        
        .custom-font-label {
            font-family: 'VT323', monospace !important;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }
        
        /* Ensure forms container can handle longer registration form */
        .forms-container {
            min-height: 600px;
            overflow: hidden; /* Hide overflow for sliding animation */
            position: relative;
        }
        
        /* Two-step sliding form system */
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
        
        /* Input fields - Market Finder style */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            font-family: 'VT323', monospace !important;
            border: 2px solid var(--border-color) !important;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus {
            border-color: var(--border-color) !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Labels - Market Finder style */
        label {
            font-family: 'VT323', monospace !important;
        }
        
        /* Buttons - Market Finder style */
        button[type="submit"],
        .bg-primary {
            font-family: 'VT323', monospace !important;
            background-color: var(--text-color) !important;
            color: var(--bg-color) !important;
            border: 2px solid var(--border-color) !important;
            padding: 0.75rem 1.5rem !important;
            font-size: 1rem !important;
            font-weight: bold !important;
            letter-spacing: 0.1em !important;
            text-transform: uppercase !important;
            transition: all 0.3s ease 0.1s !important;
        }
        
        button[type="submit"]:hover,
        .bg-primary:hover {
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
            transition: all 0.3s ease 0s !important;
        }
        
    </style>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/main.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/enhancements.css?v=<?php echo time(); ?>">
    
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
<body class="min-h-screen">
    <!-- Two Column Layout -->
    <div class="flex min-h-screen">
        <!-- Left Panel - Registration Form -->
        <div class="flex-1 bg-white flex flex-col justify-center px-8 lg:px-16 xl:px-24">
            <!-- Back Button -->
            <div class="absolute top-6 left-6 z-10">
                <a href="index.php" class="flex items-center text-gray-600 hover:text-gray-900 transition-colors">
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
                             onerror="this.src='<?php echo assetUrl('assets/images/farmscoutlogo.png'); ?>'; this.onerror=null();" />
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
                    <a href="login.php" class="text-primary hover:text-primary-600 font-medium underline">Sign in</a>
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
            
            <?php if ($success_message): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <span class="text-black"><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Registration Form - Two Step Sliding -->
            <div class="forms-container">
                <div class="registration-form-container" id="registrationFormContainer">
                    <!-- Step 1: Basic Information -->
                    <div class="registration-step">
                        <form id="step1Form" class="space-y-6">
                            <!-- Full Name Field -->
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input id="full_name" name="full_name" type="text" required 
                                       class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors custom-font-body"
                                       placeholder="Enter your full name"
                                       autocomplete="name"
                                       value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>">
                            </div>
                            
                            <!-- Username Field -->
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Username <span class="text-red-500">*</span>
                                </label>
                                <input id="username" name="username" type="text" required 
                                       class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors custom-font-body"
                                       placeholder="Choose a username"
                                       autocomplete="username"
                                       value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>">
                                <p class="mt-1 text-sm text-gray-500 custom-font-body">3+ characters, letters, numbers, and underscores only</p>
                            </div>
                            
                            <!-- Email Field -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input id="email" name="email" type="email" required 
                                       class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors custom-font-body"
                                       placeholder="example@gmail.com"
                                       autocomplete="email"
                                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>">
                            </div>
                            
                            <!-- Password Field -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input id="password" name="password" type="password" required 
                                           class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors pr-12 custom-font-body"
                                           placeholder="Create a password"
                                           autocomplete="new-password">
                                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 custom-font-body">Minimum 6 characters</p>
                            </div>
                            
                            <!-- Confirm Password Field -->
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input id="confirm_password" name="confirm_password" type="password" required 
                                           class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors pr-12 custom-font-body"
                                           placeholder="Confirm your password"
                                           autocomplete="new-password">
                                    <button type="button" onclick="toggleConfirmPassword()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                        <svg id="confirm-eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Proceed Button (Step 1) -->
                            <button type="button" onclick="validateStep1(event)" class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-primary-600 focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors custom-font-button">
                                PROCEED
                            </button>
                        </form>
                    </div>
                    
                    <!-- Step 2: Role Selection + Security Check -->
                    <div class="registration-step registration-step-2">
                        <form id="step2Form" class="space-y-6" method="POST" action="register.php">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            <!-- Hidden fields from step 1 -->
                            <input type="hidden" id="step2_full_name" name="full_name">
                            <input type="hidden" id="step2_username" name="username">
                            <input type="hidden" id="step2_email" name="email">
                            <input type="hidden" id="step2_password" name="password">
                            <input type="hidden" id="step2_confirm_password" name="confirm_password">
                            
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
                                <label for="captcha_answer" class="block text-sm font-medium text-gray-700 mb-2 custom-font-label">
                                    Security Check
                                    <span class="ml-1 text-primary font-semibold">
                                        <?php echo htmlspecialchars($register_captcha_question); ?>
                                    </span>
                                </label>
                                <input id="captcha_answer" name="captcha_answer" type="text" inputmode="numeric" required
                                       class="w-full px-4 py-3 border-2 border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-colors custom-font-body"
                                       placeholder="Enter your answer"
                                       value="<?php echo htmlspecialchars($form_data['captcha_answer'] ?? ''); ?>">
                                <p class="mt-1 text-xs text-gray-500 custom-font-body">Quick math challenge to keep bots away.</p>
                            </div>
                            
                            <!-- Create Account Button (Step 2) -->
                            <button type="submit" class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-primary-600 focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors custom-font-button">
                                CREATE ACCOUNT
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Video Background -->
        <div class="hidden lg:flex lg:flex-1 relative overflow-hidden bg-black">
            <video autoplay loop muted playsinline preload="auto" class="absolute inset-0 w-full h-full object-cover">
                <source src="assets/video/loginvid.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
            <!-- Optional overlay for better text readability if needed -->
            <div class="absolute inset-0 bg-black/20"></div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                `;
            } else {
                passwordField.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
        
        function toggleConfirmPassword() {
            const passwordField = document.getElementById('confirm_password');
            const eyeIcon = document.getElementById('confirm-eye-icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                `;
            } else {
                passwordField.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
        
        // Multi-step form handling
        function validateStep1(event) {
            if (event) {
                event.preventDefault();
            }
            
            const fullName = document.getElementById('full_name').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
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
            document.getElementById('step2_full_name').value = fullName;
            document.getElementById('step2_username').value = username;
            document.getElementById('step2_email').value = email;
            document.getElementById('step2_password').value = password;
            document.getElementById('step2_confirm_password').value = confirmPassword;
            
            // Slide to step 2
            document.getElementById('registrationFormContainer').classList.add('step-2');
            
            return false;
        }
    </script>
</body>
</html>