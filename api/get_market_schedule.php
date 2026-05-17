<?php
/**
 * Returns market schedule for a given product_id.
 * Used by the SPA Reserve Product modal for pickup date/time validation.
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$product_id = (int)($_GET['product_id'] ?? 0);
if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing product_id']);
    exit;
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            m.id AS market_id,
            m.market_name,
            m.opening_time,
            m.closing_time,
            m.operating_days,
            m.is_open
        FROM market_products mp
        INNER JOIN markets m ON m.id = mp.market_id
        WHERE mp.id = :pid
        LIMIT 1
    ");
    $stmt->bindParam(':pid', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Market not found for this product']);
        exit;
    }

    $days_raw = (string)($row['operating_days'] ?? 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');
    $days = array_values(array_filter(array_map('trim', explode(',', $days_raw))));
    if (empty($days)) {
        $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    }

    // Normalize times to HH:MM
    $open = (string)($row['opening_time'] ?? '06:00:00');
    $close = (string)($row['closing_time'] ?? '18:00:00');
    $open = substr($open, 0, 5);
    $close = substr($close, 0, 5);

    echo json_encode([
        'success' => true,
        'market' => [
            'id' => (int)$row['market_id'],
            'name' => (string)($row['market_name'] ?? ''),
            'is_open' => (int)($row['is_open'] ?? 1),
            'opening_time' => $open,
            'closing_time' => $close,
            'operating_days' => $days,
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load market schedule']);
}

