<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['suggestions' => []]);
    exit;
}

// Search in market_products table
$search_term = '%' . $query . '%';
$sql = "SELECT DISTINCT 
            mp.filipino_name,
            mp.name,
            mp.current_price,
            mp.unit,
            m.market_name,
            c.name as category_name
        FROM market_products mp
        JOIN markets m ON mp.market_id = m.id
        LEFT JOIN categories c ON mp.category_id = c.id
        WHERE (mp.filipino_name LIKE :search OR mp.name LIKE :search)
        AND mp.is_active = 1
        ORDER BY 
            CASE 
                WHEN mp.filipino_name LIKE :exact_start THEN 1
                WHEN mp.name LIKE :exact_start THEN 2
                WHEN mp.filipino_name LIKE :search THEN 3
                ELSE 4
            END,
            mp.filipino_name ASC
        LIMIT :limit";

$stmt = $conn->prepare($sql);
$exact_start = $query . '%';
$stmt->bindParam(':search', $search_term);
$stmt->bindParam(':exact_start', $exact_start);
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$suggestions = [];
foreach ($results as $row) {
    $suggestions[] = [
        'text' => $row['filipino_name'] . ' (' . $row['name'] . ')',
        'filipino_name' => $row['filipino_name'],
        'name' => $row['name'],
        'price' => $row['current_price'],
        'unit' => $row['unit'],
        'market' => $row['market_name'],
        'category' => $row['category_name']
    ];
}

echo json_encode(['suggestions' => $suggestions]);

