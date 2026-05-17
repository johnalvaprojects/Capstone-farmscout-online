<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not logged in']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '[]', true);
    if (!is_array($payload)) $payload = [];

    $product_id = (int)($payload['product_id'] ?? 0);
    $alert_type = sanitizeInput($payload['alert_type'] ?? 'change');
    $target_price = (float)($payload['target_price'] ?? 0);

    if ($product_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid product']);
        exit;
    }

    if (!in_array($alert_type, ['below', 'above', 'change'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid alert type']);
        exit;
    }

    if ($alert_type !== 'change' && $target_price <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid target price']);
        exit;
    }

    if (!checkRateLimit('price_alert_api', 12, 900)) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many requests']);
        exit;
    }

    // Get user's email (source of truth in DB).
    $conn = getDB();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
        exit;
    }

    $stmt = $conn->prepare("SELECT email FROM users WHERE id = :uid LIMIT 1");
    $stmt->execute([':uid' => (int)$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $email = (string)($row['email'] ?? '');

    if ($email === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing user email']);
        exit;
    }

    if (!function_exists('createPriceAlert')) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Price alert feature unavailable']);
        exit;
    }

    $ok = createPriceAlert($email, $product_id, $alert_type, $target_price);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create alert']);
        exit;
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('api/create_price_alert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
