<?php
require_once 'includes/enhanced_functions.php';

$page_title = 'Verify Account - FarmScout Online';
$page_description = 'Verify your FarmScout account';

$message = '';
$message_type = 'info';

// Handle verification
if (isset($_GET['token'])) {
    $token = sanitizeInput($_GET['token']);
    
    if (empty($token)) {
        $message = 'Invalid verification link.';
        $message_type = 'error';
    } else {
        $user = verifyUser($token);
        
        if ($user) {
            $message = "Account verified successfully! Welcome to FarmScout, " . htmlspecialchars($user['username']) . "! You can now log in and start using all features.";
            $message_type = 'success';
        } else {
            $message = 'Invalid or expired verification link. Please try registering again or contact support.';
            $message_type = 'error';
        }
    }
} else {
    $message = 'No verification token provided.';
    $message_type = 'error';
}
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
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        
        .custom-font-label {
            font-family: 'VT323', monospace !important;
            font-size: 1rem;
            letter-spacing: 0.05em;
        }
        
        /* Market Finder Style Buttons */
        .btn-primary {
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
            text-decoration: none !important;
            display: inline-block !important;
            text-align: center !important;
        }
        
        .btn-primary:hover {
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
            transition: all 0.3s ease 0s !important;
        }
        
        .btn-secondary {
            font-family: 'VT323', monospace !important;
            background-color: var(--bg-color) !important;
            color: var(--text-color) !important;
            border: 2px solid var(--border-color) !important;
            padding: 0.75rem 1.5rem !important;
            font-size: 1rem !important;
            font-weight: bold !important;
            letter-spacing: 0.1em !important;
            text-transform: uppercase !important;
            transition: all 0.3s ease 0.1s !important;
            text-decoration: none !important;
            display: inline-block !important;
            text-align: center !important;
        }
        
        .btn-secondary:hover {
            background-color: #f0f0f0 !important;
            transition: all 0.3s ease 0s !important;
        }
        
        /* Status Boxes */
        .status-box {
            border: 3px solid var(--border-color);
            padding: 2rem;
            background: var(--bg-color);
            margin-bottom: 1.5rem;
        }
        
        .status-success {
            border-color: #10b981;
        }
        
        .status-error {
            border-color: #dc2626;
        }
        
        .status-info {
            border-color: #0ea5e9;
        }
        
        /* Help Section */
        .help-section {
            border: 2px solid var(--border-color);
            padding: 1.5rem;
            background: #f9f9f9;
            margin-top: 2rem;
        }
        
        .help-item {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
            position: relative;
        }
        
        .help-item:before {
            content: "•";
            position: absolute;
            left: 0;
            font-size: 1.5rem;
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
    <div class="flex min-h-screen">
        <!-- Main Content -->
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
                        <h1 class="text-2xl font-bold text-primary custom-font-title">FARMSCOUT</h1>
                        <p class="text-sm text-gray-600 custom-font-subtitle">Tapat na Presyo</p>
                    </div>
                </div>
            </div>

            <!-- Title -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2 custom-font-title">Account Verification</h1>
            </div>
            
            <!-- Status Box -->
            <div class="status-box <?php 
                echo $message_type === 'success' ? 'status-success' : 
                    ($message_type === 'error' ? 'status-error' : 'status-info'); 
            ?>">
                <?php if ($message_type === 'success'): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold mb-4 custom-font-title">Verification Successful!</h2>
                        <p class="text-lg mb-6 custom-font-body"><?php echo htmlspecialchars($message); ?></p>
                        
                        <div class="space-y-3">
                            <a href="login.php" class="btn-primary w-full inline-block text-center">
                                Sign In to Your Account
                            </a>
                            
                            <a href="index.php" class="btn-secondary w-full inline-block text-center">
                                Continue to Homepage
                            </a>
                        </div>
                    </div>
                    
                <?php elseif ($message_type === 'error'): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold mb-4 custom-font-title">Verification Failed</h2>
                        <p class="text-lg mb-6 custom-font-body"><?php echo htmlspecialchars($message); ?></p>
                        
                        <div class="space-y-3">
                            <a href="register.php" class="btn-primary w-full inline-block text-center">
                                Try Registering Again
                            </a>
                            
                            <a href="login.php" class="btn-secondary w-full inline-block text-center">
                                Already have an account? Sign In
                            </a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="mx-auto h-16 w-16 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold mb-4 custom-font-title">Verification Status</h2>
                        <p class="text-lg mb-6 custom-font-body"><?php echo htmlspecialchars($message); ?></p>
                        
                        <div class="space-y-3">
                            <a href="index.php" class="btn-primary w-full inline-block text-center">
                                Go to Homepage
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Help Section -->
            <div class="help-section">
                <h3 class="text-xl font-bold mb-4 custom-font-title">Need Help?</h3>
                <div class="space-y-3 custom-font-body">
                    <div class="help-item">
                        <p><strong>Verification links expire in 24 hours</strong></p>
                        <p>If your link has expired, please register again to get a new verification email.</p>
                    </div>
                    <div class="help-item">
                        <p><strong>Check your spam folder</strong></p>
                        <p>Verification emails sometimes end up in spam or junk folders.</p>
                    </div>
                    <div class="help-item">
                        <p><strong>Contact support</strong></p>
                        <p>If you continue having issues, contact the administrator for assistance.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
