<?php
require_once 'includes/enhanced_functions.php';

$error_message = '';
$success_message = '';
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// Validate token and email
if (empty($token) || empty($email)) {
    $error_message = 'Invalid or missing reset link.';
} elseif (!validateEmail($email)) {
    $error_message = 'Invalid email address.';
} elseif (!validatePasswordResetToken($email, $token)) {
    $error_message = 'Invalid or expired reset link. Please request a new one.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password)) {
        $error_message = 'Please enter a new password.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $result = resetUserPassword($email, $token, $new_password);
        
        if ($result['success']) {
            $success_message = 'Your password has been reset successfully. You can now log in with your new password.';
            // Clear the form
            $token = '';
            $email = '';
        } else {
            $error_message = $result['message'] ?? 'Failed to reset password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FarmScout Online</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/enhancements.css">
    <link rel="icon" type="image/gif" href="assets/images/demi doggu.gif">
    
    <!-- Logo Animation Styles -->
    <style>
        /* Animated Logo Enhancements */
        .animated-logo {
            border-radius: 8px;
        }
        
        /* Mobile optimization */
        @media (max-width: 640px) {
            .animated-logo {
                height: 2.5rem; /* h-10 equivalent */
                width: 2.5rem;
                border-radius: 6px;
            }
        }
        
        /* Tablet optimization */
        @media (min-width: 641px) and (max-width: 768px) {
            .animated-logo {
                height: 3rem; /* h-12 equivalent */
                width: 3rem;
            }
        }
        
        /* Desktop optimization */
        @media (min-width: 769px) {
            .animated-logo {
                height: 3.5rem; /* h-14 equivalent */
                width: 3.5rem;
            }
        }
        
        /* Smooth loading for GIF */
        .animated-logo {
            opacity: 0;
            animation: fadeInLogo 0.5s ease-in forwards;
        }
        
        @keyframes fadeInLogo {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Prefers reduced motion - respect accessibility */
        @media (prefers-reduced-motion: reduce) {
            .animated-logo {
                animation: none;
                opacity: 1;
                transform: none;
            }
        }
        
        /* Modern Logo Text */
        .modern-logo-text {
            font-weight: 800;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            letter-spacing: -0.03em;
            color: #1a1a1a;
            font-size: 1.5rem;
        }
        
        .modern-tagline {
            font-weight: 400;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #666666;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        
        /* Modern Button Styles */
        .modern-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
            border: none;
            cursor: pointer;
        }
        
        .modern-button-primary {
            background: #1a1a1a;
            color: #ffffff;
        }
        
        .modern-button-primary:hover {
            background: #333333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 26, 26, 0.2);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center flex-shrink-0">
                    <div class="flex-shrink-0">
                        <img src="assets/images/gif-wazulafu-no-bg.gif" 
                             alt="FarmScout - Tapat na Presyo" 
                             class="h-12 w-12 md:h-14 md:w-14 object-contain logo-img animated-logo" 
                             onerror="this.src='assets/images/farmscoutlogo.png'; this.onerror=null;" />
                    </div>
                    <div class="ml-4">
                        <h1 class="modern-logo-text">FARMSCOUT</h1>
                        <p class="modern-tagline">Tapat na Presyo</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="modern-button modern-button-primary">Back to Login</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex items-center justify-center min-h-[calc(100vh-4rem)] py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <h2 class="mt-6 text-3xl font-bold text-gray-900 custom-font-heading">
                    Reset Your Password
                </h2>
                <p class="mt-2 text-sm text-gray-600 custom-font-body">
                    Enter your new password below.
                </p>
            </div>

            <!-- Form -->
            <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200">
                <?php if ($error_message): ?>
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-800 custom-font-body"><?php echo htmlspecialchars($error_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-800 custom-font-body"><?php echo htmlspecialchars($success_message); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 text-center">
                        <a href="login.php" 
                           class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-primary-600 focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors custom-font-button inline-block">
                            Go to Login
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 custom-font-label">
                                New Password
                            </label>
                            <div class="mt-1 relative">
                                <input id="password" name="password" type="password" autocomplete="new-password" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pr-12 custom-font-body"
                                       placeholder="Enter your new password">
                                <button type="button" onclick="togglePassword('password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <svg id="eye-icon-password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 custom-font-body">
                                Password must be at least 8 characters long.
                            </p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 custom-font-label">
                                Confirm New Password
                            </label>
                            <div class="mt-1 relative">
                                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pr-12 custom-font-body"
                                       placeholder="Confirm your new password">
                                <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <svg id="eye-icon-confirm_password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <button type="submit" 
                                    class="w-full bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-primary-600 focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors custom-font-button">
                                Reset Password
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 custom-font-body">
                        Remember your password? 
                        <a href="login.php" class="font-medium text-primary hover:text-primary-600">
                            Sign in here
                        </a>
                    </p>
                </div>
            </div>

            <!-- Help Section -->
            <div class="text-center">
                <p class="text-xs text-gray-500 custom-font-body">
                    If you're having trouble, please contact our support team.
                </p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="text-center text-sm text-gray-500 custom-font-body">
                <p>&copy; <?php echo date('Y'); ?> FarmScout Online. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById('eye-icon-' + fieldId);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                `;
            } else {
                field.type = 'password';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
    </script>
</body>
</html>
