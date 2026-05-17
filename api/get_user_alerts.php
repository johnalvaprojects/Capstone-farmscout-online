<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$email = sanitizeInput($_POST['email'] ?? '');

if (empty($email) || !validateEmail($email)) {
    echo json_encode(['error' => 'Valid email address is required']);
    exit;
}

try {
    $alerts = getUserPriceAlerts($email);
    
    if (empty($alerts)) {
        echo json_encode(['alerts' => [], 'message' => 'No active price alerts found for this email address.']);
        exit;
    }
    
    // Format alerts for display
    $formatted_alerts = [];
    foreach ($alerts as $alert) {
        $alert_text = '';
        switch ($alert['alert_type']) {
            case 'below':
                $alert_text = 'Alert when price drops below ₱' . number_format($alert['target_price'], 2);
                break;
            case 'above':
                $alert_text = 'Alert when price rises above ₱' . number_format($alert['target_price'], 2);
                break;
            case 'change':
                $alert_text = 'Alert on any price change';
                break;
        }
        
        $market_name = isset($alert['market_name']) ? ' - ' . $alert['market_name'] : '';
        $formatted_alerts[] = [
            'id' => $alert['id'],
            'product_id' => $alert['product_id'], // Add product_id for price history
            'product_name' => $alert['filipino_name'] . ' (' . $alert['name'] . ')' . $market_name,
            'alert_text' => $alert_text,
            'current_price' => '₱' . number_format($alert['current_price'], 2) . '/' . $alert['unit'],
            'created_at' => date('M j, Y', strtotime($alert['created_at'])),
            'image_url' => $alert['image_url'],
            'target_price' => $alert['target_price'],
            'alert_type' => $alert['alert_type']
        ];
    }
    
    echo json_encode(['alerts' => $formatted_alerts]);
    
} catch (Exception $e) {
    error_log('Error fetching user alerts: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to load alerts. Please try again.']);
}
?>