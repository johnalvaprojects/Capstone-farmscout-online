<?php
/**
 * Distinct product names for Market Map search autocomplete (same visibility rules as search-product-markets.php).
 */
require_once '../config/database.php';
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
$limit = max(1, min(20, $limit));

if (strlen($q) < 2) {
    echo json_encode(['suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        echo json_encode(['suggestions' => []]);
        exit;
    }

    $hasDeletedAt = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
        $hasDeletedAt = $col_check && $col_check->rowCount() > 0;
    } catch (Exception $e) {
    }
    $deletedAtCheck = $hasDeletedAt ? 'AND mp.deleted_at IS NULL' : '';

    $hasApprovalStatus = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
        $hasApprovalStatus = $col_check && $col_check->rowCount() > 0;
    } catch (Exception $e) {
    }
    $approvalCheck = $hasApprovalStatus
        ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)"
        : '';

    $hasCategory = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'category'");
        $hasCategory = $col_check && $col_check->rowCount() > 0;
    } catch (Exception $e) {
    }
    $categorySelect = $hasCategory
        ? ", MAX(NULLIF(TRIM(mp.category), '')) AS category"
        : ', NULL AS category';

    $like = '%' . $q . '%';
    $starts = $q . '%';

    $sql = "
        SELECT
            mp.product_name,
            MIN(CAST(mp.price AS DECIMAL(12,2))) AS min_price,
            MIN(mp.unit) AS unit,
            COUNT(DISTINCT m.id) AS market_count
            $categorySelect
        FROM market_products mp
        INNER JOIN markets m ON mp.market_id = m.id
        WHERE LOWER(mp.product_name) LIKE LOWER(?)
        AND mp.is_available = 1
        $deletedAtCheck
        $approvalCheck
        AND m.status = 'active'
        GROUP BY mp.product_name
        ORDER BY
            CASE
                WHEN LOWER(mp.product_name) = LOWER(?) THEN 0
                WHEN LOWER(mp.product_name) LIKE LOWER(?) THEN 1
                ELSE 2
            END,
            CHAR_LENGTH(mp.product_name) ASC,
            mp.product_name ASC
        LIMIT " . (int) $limit;

    $stmt = $conn->prepare($sql);
    $stmt->execute([$like, $q, $starts]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $suggestions = [];
    foreach ($rows as $row) {
        $suggestions[] = [
            'product_name' => $row['product_name'],
            'min_price' => $row['min_price'] !== null ? (float) $row['min_price'] : null,
            'unit' => $row['unit'] ?? 'kg',
            'market_count' => (int) ($row['market_count'] ?? 0),
            'category' => $row['category'] ?? null,
        ];
    }

    echo json_encode(['suggestions' => $suggestions], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('search-map-product-autocomplete: ' . $e->getMessage());
    echo json_encode(['suggestions' => []]);
}
