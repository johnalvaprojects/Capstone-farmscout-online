<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json');

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $hasStatus = false;
    $hasDeletedAt = false;

    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM markets LIKE 'status'");
        $hasStatus = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasStatus = false;
    }

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

    $hasOperatingHours = false;
    $hasIsOpen = false;
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM markets LIKE 'operating_hours'");
        $hasOperatingHours = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasOperatingHours = false;
    }
    try {
        $colCheck = $conn->query("SHOW COLUMNS FROM markets LIKE 'is_open'");
        $hasIsOpen = $colCheck && $colCheck->rowCount() > 0;
    } catch (Exception $e) {
        $hasIsOpen = false;
    }

    $extraMarketCols = '';
    if ($hasOperatingHours) {
        $extraMarketCols .= ', COALESCE(m.operating_hours, \'\') AS operating_hours';
    }
    if ($hasIsOpen) {
        $extraMarketCols .= ', COALESCE(m.is_open, 1) AS is_open';
    }

    /* Market Finder hub lists every market row (do not hide inactive/draft here). */
    $marketWhere = "";
    $deletedWhere = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
    // Same visibility rules as getProductsByCategories / Market Finder category pills
    $approvalWhere = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "";

    $sql = "
        SELECT
            m.id,
            m.market_name,
            m.address,
            COALESCE(m.latitude, 0) AS latitude,
            COALESCE(m.longitude, 0) AS longitude,
            COALESCE(mp_counts.product_count, 0) AS product_count
            $extraMarketCols
        FROM markets m
        LEFT JOIN (
            SELECT
                mp.market_id,
                COUNT(*) AS product_count
            FROM market_products mp
            WHERE mp.is_available = 1
            $deletedWhere
            $approvalWhere
            GROUP BY mp.market_id
        ) mp_counts ON mp_counts.market_id = m.id
        $marketWhere
        ORDER BY m.market_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $markets = [];
    foreach ($rows as $row) {
        $item = [
            'id' => (int)($row['id'] ?? 0),
            'market_name' => (string)($row['market_name'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
            'latitude' => (float)($row['latitude'] ?? 0),
            'longitude' => (float)($row['longitude'] ?? 0),
            'product_count' => (int)($row['product_count'] ?? 0),
        ];
        if ($hasOperatingHours) {
            $item['operating_hours'] = (string)($row['operating_hours'] ?? '');
        }
        if ($hasIsOpen) {
            $item['is_open'] = (int)($row['is_open'] ?? 1) === 1;
        }
        $markets[] = $item;
    }

    echo json_encode([
        'success' => true,
        'markets' => $markets,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('Error in get_markets.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load markets',
        'markets' => [],
    ], JSON_UNESCAPED_UNICODE);
}
?>
