<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

// Generate captcha for price alert modal
$formKey = 'price_alert_modal';
generateSimpleCaptcha($formKey);
$question = getSimpleCaptchaQuestion($formKey);

echo json_encode([
    'success' => true,
    'question' => $question,
    'csrf_token' => getCSRFToken()
]);

