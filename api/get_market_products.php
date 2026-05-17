<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

$market_id = isset($_GET['market_id']) ? intval($_GET['market_id']) : 0;

if ($market_id <= 0) {
    echo json_encode(['error' => 'Valid market ID is required']);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if deleted_at column exists
    $hasDeletedAt = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
        $hasDeletedAt = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, that's okay
    }
    $deletedAtCheck = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
    
    // Get products from the selected market (include out-of-stock; UI will disable reserve)
    $query = "SELECT 
                mp.id,
                mp.product_name as name,
                mp.product_name as filipino_name,
                mp.product_description as description,
                mp.category,
                COALESCE(c.filipino_name, mp.category) as category_filipino,
                mp.price as current_price,
                mp.unit,
                mp.product_image as image_url,
                mp.is_available,
                mp.market_id,
                m.market_name
              FROM market_products mp
              LEFT JOIN categories c ON LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name))
              LEFT JOIN markets m ON mp.market_id = m.id
              WHERE mp.market_id = :market_id 
              $deletedAtCheck
              ORDER BY mp.is_available DESC, mp.product_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':market_id', $market_id, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $products = attachUnitOptionsToProducts($products);
    
    // Format products for JSON response
    $formatted_products = [];
    foreach ($products as $product) {
        $unit_options = [];
        if (!empty($product['unit_options']) && is_array($product['unit_options'])) {
            foreach ($product['unit_options'] as $option) {
                $unit_options[] = [
                    'label' => $option['display_label'],
                    'unit_label' => $option['unit_label'],
                    'quantity' => $option['quantity'],
                    'price' => $option['price'],
                    'is_default' => !empty($option['is_default'])
                ];
            }
        }
        $formatted_products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'filipino_name' => $product['filipino_name'],
            'description' => $product['description'] ?? '',
            'category' => $product['category'] ?? '',
            'category_filipino' => $product['category_filipino'] ?? '',
            'current_price' => '₱' . number_format($product['current_price'], 2),
            'raw_price' => (float)($product['current_price'] ?? 0),
            'unit' => $product['unit'],
            'image_url' => $product['image_url'] ?? '',
            'is_available' => !empty($product['is_available']),
            'market_id' => $product['market_id'],
            'market_name' => $product['market_name'],
            'unit_options' => $unit_options
        ];
    }
    
    echo json_encode(['products' => $formatted_products]);
    
} catch (Exception $e) {
    error_log('Error fetching market products: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to load products. Please try again.']);
}
?>

