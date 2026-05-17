<?php
require_once 'includes/enhanced_functions.php';

$page_title = 'Unsubscribe - FarmScout Online';
$page_description = 'Unsubscribe from price alerts';

$message = '';
$message_type = '';
$email = '';
$token = '';

// Handle unsubscribe request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $email = sanitizeInput($_GET['email'] ?? '');
    $token = sanitizeInput($_GET['token'] ?? '');
    
    if (!empty($email) && !empty($token)) {
        // Verify token and unsubscribe
        if (verifyUnsubscribeToken($email, $token)) {
            if (unsubscribeAllAlerts($email)) {
                $message = 'You have been successfully unsubscribed from all price alerts.';
                $message_type = 'success';
            } else {
                $message = 'Failed to unsubscribe. Please try again or contact support.';
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid unsubscribe link. Please contact support if you continue to receive emails.';
            $message_type = 'error';
        }
    }
}

// Handle manual unsubscribe form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (!validateEmail($email)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        // Send unsubscribe confirmation email
        if (sendUnsubscribeConfirmation($email)) {
            $message = 'We have sent you a confirmation email. Please check your inbox and click the unsubscribe link.';
            $message_type = 'success';
        } else {
            $message = 'Failed to send unsubscribe confirmation. Please contact support.';
            $message_type = 'error';
        }
    }
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-primary-50 to-surface-100 py-12">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow-card p-8">
            <div class="text-center mb-8">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                    <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-primary mb-2">Unsubscribe from Price Alerts</h1>
                <p class="text-text-secondary">Stop receiving price alert emails from FarmScout</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                    <div class="flex items-center">
                        <?php if ($message_type === 'success'): ?>
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        <?php else: ?>
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message_type !== 'success'): ?>
                <div class="space-y-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="font-semibold text-yellow-800 mb-1">Important Notice</h3>
                                <p class="text-yellow-700 text-sm">
                                    Unsubscribing will stop all price alert emails for this email address. 
                                    You can always set up new alerts by visiting our price alerts page.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-text-primary mb-2">
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" required 
                                   class="input-field w-full" 
                                   placeholder="your@email.com"
                                   value="<?php echo htmlspecialchars($email); ?>"
                                   autocomplete="off"
                                   data-lpignore="true"
                                   data-form-type="other">
                            <p class="text-xs text-text-secondary mt-1">
                                Enter the email address you want to unsubscribe from price alerts
                            </p>
                        </div>

                        <button type="submit" class="btn-primary w-full flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"/>
                            </svg>
                            Send Unsubscribe Confirmation
                        </button>
                    </form>

                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="font-semibold text-text-primary mb-3">Alternative Options</h3>
                        <div class="space-y-3">
                            <a href="price-alerts.php" class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-primary mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                                </svg>
                                <div>
                                    <div class="font-medium text-text-primary">Manage Your Alerts</div>
                                    <div class="text-sm text-text-secondary">View and remove specific price alerts</div>
                                </div>
                            </a>
                            
                            <a href="mailto:support@farmscout.com" class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <svg class="w-5 h-5 text-primary mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <div>
                                    <div class="font-medium text-text-primary">Contact Support</div>
                                    <div class="text-sm text-text-secondary">Get help with unsubscribing or other issues</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center">
                    <div class="mb-6">
                        <a href="index.php" class="btn-primary inline-flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Return to Homepage
                        </a>
                    </div>
                    
                    <p class="text-text-secondary text-sm">
                        You can always set up new price alerts by visiting our 
                        <a href="price-alerts.php" class="text-primary hover:underline">price alerts page</a>.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
