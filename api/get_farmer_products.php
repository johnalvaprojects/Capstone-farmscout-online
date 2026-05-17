<?php
/**
 * All products for the logged-in farmer (for SPA dashboard).
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Farmer login required.', 'products' => []]);
    exit;
}

$farmer_id = (int) $_SESSION['user_id'];

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $hasDeletedAt = false;
    try {
        $col = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
        $hasDeletedAt = $col && $col->rowCount() > 0;
    } catch (Exception $e) {
        $hasDeletedAt = false;
    }
    $deletedCheck = $hasDeletedAt ? "AND (mp.deleted_at IS NULL OR mp.deleted_at = '0000-00-00 00:00:00')" : "";

    $sql = "SELECT mp.id,
                   mp.product_name,
                   mp.price,
                   mp.unit,
                   mp.is_available,
                   mp.product_image,
                   mp.market_id,
                   m.market_name
            FROM market_products mp
            LEFT JOIN markets m ON mp.market_id = m.id
            WHERE mp.farmer_id = :farmer_id
            $deletedCheck
            ORDER BY mp.product_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':farmer_id', $farmer_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($rows as $p) {
        $products[] = [
            'id' => (int) $p['id'],
            'product_name' => $p['product_name'],
            'price' => $p['price'],
            'unit' => $p['unit'] ?? 'kg',
            'is_available' => (int) ($p['is_available'] ?? 1),
            'product_image' => $p['product_image'] ?? '',
            'market_id' => (int) ($p['market_id'] ?? 0),
            'market_name' => $p['market_name'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    error_log('get_farmer_products: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not load products.', 'products' => []]);
}
