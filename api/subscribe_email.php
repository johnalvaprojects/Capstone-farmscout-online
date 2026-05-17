<?php
/**
 * Email Subscription API
 * Handles email subscriptions for newsletter
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/enhanced_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
    exit;
}

// For now, just log the subscription
// In production, you would save this to a database
error_log("New email subscription: " . $email);

// You can add database storage here:
// $conn = getDB();
// $stmt = $conn->prepare("INSERT INTO email_subscriptions (email, subscribed_at) VALUES (:email, NOW())");
// $stmt->bindParam(':email', $email);
// $stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Thank you for subscribing!'
]);

