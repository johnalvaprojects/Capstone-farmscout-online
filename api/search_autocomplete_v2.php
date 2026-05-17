<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=UTF-8');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$limit = max(1, min(12, $limit));

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = getDB();
if (!$pdo) {
    echo json_encode(['success' => false, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$hasDeletedAt = false;
try {
    $c = $pdo->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
    $hasDeletedAt = $c && $c->rowCount() > 0;
} catch (Exception $e) {
    $hasDeletedAt = false;
}

$hasApprovalStatus = false;
try {
    $c = $pdo->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
    $hasApprovalStatus = $c && $c->rowCount() > 0;
} catch (Exception $e) {
    $hasApprovalStatus = false;
}

$deletedWhere = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
$approvalWhere = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "";

$like = '%' . $q . '%';
$starts = $q . '%';

$sql = "
    SELECT
        mp.id,
        mp.product_name,
        COALESCE(mp.category, '') AS category,
        COALESCE(mp.unit, '') AS unit,
        COALESCE(mp.price, 0) AS price,
        mp.market_id,
        COALESCE(m.market_name, '') AS market_name
    FROM market_products mp
    JOIN markets m ON m.id = mp.market_id
    WHERE mp.is_available = 1
      $deletedWhere
      $approvalWhere
      AND (
        mp.product_name LIKE :like
        OR mp.category LIKE :like
        OR m.market_name LIKE :like
      )
    ORDER BY
      CASE
        WHEN mp.product_name LIKE :starts THEN 1
        WHEN m.market_name LIKE :starts THEN 2
        ELSE 3
      END,
      mp.product_name ASC
    LIMIT :lim
";

try {
    $st = $pdo->prepare($sql);
    $st->bindValue(':like', $like, PDO::PARAM_STR);
    $st->bindValue(':starts', $starts, PDO::PARAM_STR);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('search_autocomplete_v2: ' . $e->getMessage());
    echo json_encode(['success' => false, 'suggestions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$out = [];
foreach ($rows as $r) {
    $name = (string)($r['product_name'] ?? '');
    $market = (string)($r['market_name'] ?? '');
    $cat = (string)($r['category'] ?? '');
    $unit = (string)($r['unit'] ?? '');
    $price = (float)($r['price'] ?? 0);
    $text = $name;
    if ($market !== '') $text .= " — " . $market;
    if ($cat !== '') $text .= " (" . $cat . ")";

    $out[] = [
        'text' => $text,
        'product_name' => $name,
        'market_name' => $market,
        'category' => $cat,
        'market_id' => (int)($r['market_id'] ?? 0),
        'unit' => $unit,
        'price' => $price,
    ];
}

echo json_encode(['success' => true, 'suggestions' => $out], JSON_UNESCAPED_UNICODE);

