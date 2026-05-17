<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

if ($product_id <= 0) {
    echo json_encode(['error' => 'Invalid product ID']);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get price history for the last N days
$query = "SELECT price, DATE(recorded_at) as date, recorded_at 
          FROM price_history 
          WHERE product_id = :product_id 
          AND recorded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
          ORDER BY recorded_at ASC";

$stmt = $conn->prepare($query);
$stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
$stmt->bindParam(':days', $days, PDO::PARAM_INT);
$stmt->execute();

$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format data for chart
$chart_data = [
    'labels' => [],
    'prices' => [],
    'dates' => []
];

foreach ($history as $record) {
    $chart_data['labels'][] = date('M j', strtotime($record['recorded_at']));
    $chart_data['prices'][] = floatval($record['price']);
    $chart_data['dates'][] = $record['date'];
}

echo json_encode($chart_data);

