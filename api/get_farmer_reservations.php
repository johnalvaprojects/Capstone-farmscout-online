<?php
/**
 * Reservations for the logged-in farmer (for SPA dashboard).
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';
require_once __DIR__ . '/../includes/fs_reservation_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Farmer login required.', 'reservations' => []]);
    exit;
}

$farmer_id = (int) $_SESSION['user_id'];

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $table = $conn->query("SHOW TABLES LIKE 'reservations'");
    if (!$table || $table->rowCount() === 0) {
        echo json_encode(['success' => true, 'reservations' => []]);
        exit;
    }

    $parent_ok = function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn);
    $root_filter = $parent_ok ? ' AND r.parent_reservation_id IS NULL ' : '';

    $summary_select = $parent_ok
        ? "(SELECT GROUP_CONCAT(CONCAT(r2.quantity, ' ', r2.unit, ' ', mp2.product_name) ORDER BY r2.id SEPARATOR '; ') FROM reservations r2 INNER JOIN market_products mp2 ON r2.product_id = mp2.id WHERE r2.id = r.id OR r2.parent_reservation_id = r.id) AS product_summary"
        : "CONCAT(r.quantity, ' ', r.unit, ' ', mp.product_name) AS product_summary";

    $sql = "SELECT r.id,
                   r.status,
                   r.quantity,
                   r.unit,
                   mp.product_name,
                   r.public_ref,
                   COALESCE(u.full_name, u.username) AS user_name,
                   {$summary_select}
            FROM reservations r
            INNER JOIN market_products mp ON r.product_id = mp.id
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.farmer_id = :farmer_id
            {$root_filter}
            ORDER BY r.created_at DESC
            LIMIT 200";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':farmer_id', $farmer_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reservations = [];
    foreach ($rows as $r) {
        $summary = trim((string)($r['product_summary'] ?? ''));
        if ($summary === '') {
            $summary = (string)($r['product_name'] ?? '');
        }
        $reservations[] = [
            'id' => (int) $r['id'],
            'status' => strtolower((string) ($r['status'] ?? 'pending')),
            'quantity' => (float) ($r['quantity'] ?? 0),
            'unit' => $r['unit'] ?? 'kg',
            'product_name' => $r['product_name'] ?? '',
            'product_summary' => $summary,
            'public_ref' => isset($r['public_ref']) ? (string) $r['public_ref'] : '',
            'user_name' => $r['user_name'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'reservations' => $reservations]);
} catch (Exception $e) {
    error_log('get_farmer_reservations: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not load reservations.', 'reservations' => []]);
}
