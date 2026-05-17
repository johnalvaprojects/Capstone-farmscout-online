<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json');

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $hasDeletedAt = false;
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
        $hasDeletedAt = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasDeletedAt = false;
    }

    $hasApprovalStatus = false;
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
        $hasApprovalStatus = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasApprovalStatus = false;
    }

    $deletedWhere = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
    $approvalWhere = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "";

    $sql = "
        SELECT
            c.id,
            c.name,
            c.filipino_name,
            COALESCE(c.description, '') AS description,
            COALESCE(cnt.product_count, 0) AS product_count
        FROM categories c
        LEFT JOIN (
            SELECT
                LOWER(TRIM(mp.category)) AS cat_key,
                COUNT(DISTINCT mp.product_name) AS product_count
            FROM market_products mp
            WHERE mp.is_available = 1
            $deletedWhere
            $approvalWhere
            GROUP BY LOWER(TRIM(mp.category))
        ) cnt ON cnt.cat_key = LOWER(TRIM(c.name))
        WHERE c.is_active = 1
        ORDER BY c.sort_order ASC, c.name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'filipino_name' => (string)($row['filipino_name'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'product_count' => (int)($row['product_count'] ?? 0),
        ];
    }

    echo json_encode(['success' => true, 'categories' => $out], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error in get_categories_all.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'categories' => [], 'error' => 'Failed to load categories'], JSON_UNESCAPED_UNICODE);
}

