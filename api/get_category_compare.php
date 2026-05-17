<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json');

$categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
if ($categoryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid category_id is required']);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Resolve category name (market_products stores category as a string)
    $stmt = $conn->prepare("SELECT id, name, filipino_name FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $categoryId]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat) {
        echo json_encode(['success' => false, 'error' => 'Category not found']);
        exit;
    }

    $catName = (string)($cat['name'] ?? '');
    $catFil = (string)($cat['filipino_name'] ?? '');

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
            mp.id AS product_id,
            mp.product_name,
            mp.product_image,
            mp.product_description,
            mp.price,
            mp.unit,
            mp.is_available,
            mp.market_id,
            mp.farmer_id,
            mp.category AS category,
            m.market_name
        FROM market_products mp
        JOIN markets m ON m.id = mp.market_id
        WHERE 1=1
          $deletedWhere
          $approvalWhere
          AND (
            LOWER(TRIM(mp.category)) = LOWER(TRIM(:catName))
            OR LOWER(TRIM(mp.category)) = LOWER(TRIM(:catFil))
            OR TRIM(mp.category) = CAST(:catId AS CHAR)
          )
        ORDER BY mp.product_name ASC, m.market_name ASC, mp.is_available DESC, mp.price ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':catName' => $catName,
        ':catFil' => $catFil,
        ':catId' => $categoryId,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    /*
     * One card per market listing (not one row per product name).
     * Previously we collapsed duplicate names to a single "best" offer (lowest in-stock price),
     * which hid other markets' listings (e.g. Balaoan mango when San Juan was cheaper).
     */
    $products = [];
    foreach ($rows as $r) {
        $name = (string)($r['product_name'] ?? '');
        if ($name === '') {
            continue;
        }
        $price = (float)($r['price'] ?? 0);
        $best = [
            'product_id' => (int)($r['product_id'] ?? 0),
            'market_id' => (int)($r['market_id'] ?? 0),
            'farmer_id' => (int)($r['farmer_id'] ?? 0),
            'market_name' => (string)($r['market_name'] ?? ''),
            'product_image' => (string)($r['product_image'] ?? ''),
            'product_description' => (string)($r['product_description'] ?? ''),
            'price' => $price,
            'unit' => (string)($r['unit'] ?? ''),
            'is_available' => !empty($r['is_available']),
            'category' => (string)($r['category'] ?? ''),
            'category_filipino' => (string)($r['category'] ?? ''),
        ];
        $products[] = [
            'product_name' => $name,
            'best' => $best,
            'min' => $price,
            'max' => $price,
        ];
    }

    echo json_encode([
        'success' => true,
        'category' => [
            'id' => (int)$categoryId,
            'name' => $catName,
            'filipino_name' => $catFil,
        ],
        'products' => $products,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error in get_category_compare.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load comparison'], JSON_UNESCAPED_UNICODE);
}

