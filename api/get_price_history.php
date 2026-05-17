<?php
/**
 * API endpoint to get price history for a product
 * Public access - any user can view price history for any product
 */

// Start output buffering FIRST
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching price history.'
        ]);
        exit;
    }
});

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$days = isset($_GET['days']) ? intval($_GET['days']) : 30; // Default 30 days
$days = min($days, 365); // Max 1 year
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 500;
$limit = min($limit, 500); // Max 500 records

// Filter parameters
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$change_type = isset($_GET['change_type']) ? trim($_GET['change_type']) : ''; // 'all', 'increase', 'decrease', 'new'

// If days parameter is provided, calculate date range
if ($days > 0 && empty($date_from) && empty($date_to)) {
    $date_from = date('Y-m-d', strtotime("-{$days} days"));
    $date_to = date('Y-m-d');
}

// Validate date format
if (!empty($date_from) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '';
}
if (!empty($date_to) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '';
}
// Validate change_type
if (!empty($change_type) && !in_array($change_type, ['all', 'increase', 'decrease', 'new'])) {
    $change_type = '';
}

if ($product_id <= 0) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid product ID.'
    ]);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if price_history table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'price_history'");
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'price_history' => [],
            'message' => 'Price history table does not exist. Please run migration.'
        ]);
        exit;
    }
    
    // Verify product exists (public access - no farmer restriction)
    $product_check = $conn->prepare("SELECT id, product_name, price AS current_price, unit FROM market_products WHERE id = :product_id");
    $product_check->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $product_check->execute();
    $product = $product_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Product not found.'
        ]);
        exit;
    }
    
    // Build query with filters - get price history for this product
    // Note: The actual table schema uses 'price' column, not 'old_price'/'new_price'
    $query = "SELECT id, price, recorded_at, change_type, change_percentage
              FROM price_history
              WHERE product_id = :product_id";
    
    // Add date range filter
    if (!empty($date_from)) {
        $query .= " AND DATE(recorded_at) >= :date_from";
    }
    if (!empty($date_to)) {
        $query .= " AND DATE(recorded_at) <= :date_to";
    }
    
    // Add change type filter
    if (!empty($change_type) && $change_type !== 'all') {
        if ($change_type === 'new') {
            $query .= " AND old_price IS NULL";
        } elseif ($change_type === 'increase') {
            $query .= " AND new_price > old_price AND old_price IS NOT NULL";
        } elseif ($change_type === 'decrease') {
            $query .= " AND new_price < old_price AND old_price IS NOT NULL";
        }
    }
    
    $query .= " ORDER BY recorded_at ASC LIMIT :limit"; // ASC for chronological chart display
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
    if (!empty($date_from)) {
        $stmt->bindParam(':date_from', $date_from);
    }
    if (!empty($date_to)) {
        $stmt->bindParam(':date_to', $date_to);
    }
    
    $stmt->execute();
    $price_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log query for debugging
    error_log("Price history query executed. Found " . count($price_history) . " records for product_id: " . $product_id);
    
    // Format price history for chart display
    $price_data = [];
    $prices = [];
    
    foreach ($price_history as $record) {
        $timestamp = strtotime($record['recorded_at']);
        $date = date('Y-m-d', $timestamp);
        $price = floatval($record['price']); // Use 'price' column instead of 'new_price'
        
        // For chart: use price as the price point
        $price_data[] = [
            'date' => $date,
            'price' => $price,
            'formatted_date' => date('M d, Y', $timestamp),
            'recorded_at' => $record['recorded_at']
        ];
        
        $prices[] = $price;
    }
    
    // Add current price as the latest point if no history exists or if it's newer
    $current_price = floatval($product['current_price'] ?? 0);
    if ($current_price > 0) {
        // Check if we need to add current price
        $latest_date = !empty($price_data) ? end($price_data)['date'] : null;
        $today = date('Y-m-d');
        
        if (!$latest_date || $latest_date < $today) {
            $price_data[] = [
                'date' => $today,
                'price' => $current_price,
                'formatted_date' => date('M d, Y'),
                'recorded_at' => date('Y-m-d H:i:s')
            ];
            $prices[] = $current_price;
        }
    }
    
    // Calculate statistics
    $stats = [
        'min_price' => !empty($prices) ? round(min($prices), 2) : $current_price,
        'max_price' => !empty($prices) ? round(max($prices), 2) : $current_price,
        'avg_price' => !empty($prices) ? round(array_sum($prices) / count($prices), 2) : $current_price,
        'current_price' => $current_price,
        'total_data_points' => count($price_data)
    ];
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'product_id' => $product_id,
        'product_name' => $product['product_name'],
        'product_unit' => $product['unit'] ?? '',
        'price_data' => $price_data,
        'stats' => $stats,
        'total_records' => count($price_data)
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("PDO Error fetching price history: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    error_log("Error fetching price history: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}

