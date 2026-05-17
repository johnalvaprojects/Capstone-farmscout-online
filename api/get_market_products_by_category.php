<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json');

$market_id = isset($_GET['market_id']) ? intval($_GET['market_id']) : 0;
$category_name = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($market_id <= 0 || empty($category_name)) {
    echo json_encode(['success' => false, 'error' => 'Valid market ID and category are required']);
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
    
    // Check if approval_status column exists
    $hasApprovalStatus = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
        $hasApprovalStatus = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, that's okay
    }
    $approvalCheck = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "";
    
    // Get products from the selected market and category
    // Match category by name (English or Filipino) or by category ID
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
                mp.market_id,
                m.market_name
              FROM market_products mp
              LEFT JOIN categories c ON (
                  LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name)) 
                  OR LOWER(TRIM(mp.category)) = LOWER(TRIM(c.filipino_name))
                  OR TRIM(mp.category) = CAST(c.id AS CHAR)
              )
              LEFT JOIN markets m ON mp.market_id = m.id
              WHERE mp.market_id = :market_id 
              AND mp.is_available = 1 
              $approvalCheck
              AND (
                  LOWER(TRIM(mp.category)) = LOWER(TRIM(:category_name))
                  OR LOWER(TRIM(mp.category)) = LOWER(TRIM(:category_filipino))
                  OR LOWER(TRIM(c.name)) = LOWER(TRIM(:category_name))
                  OR LOWER(TRIM(c.filipino_name)) = LOWER(TRIM(:category_name))
                  OR LOWER(TRIM(c.name)) = LOWER(TRIM(:category_filipino))
                  OR LOWER(TRIM(c.filipino_name)) = LOWER(TRIM(:category_filipino))
              )
              $deletedAtCheck
              ORDER BY mp.product_name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':market_id', $market_id, PDO::PARAM_INT);
    $stmt->bindParam(':category_name', $category_name, PDO::PARAM_STR);
    $stmt->bindParam(':category_filipino', $category_name, PDO::PARAM_STR);
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
        
        $rawImg = trim((string)($product['image_url'] ?? ''));
        if ($rawImg === '') {
            $image_url = fs_public_asset_url('/assets/images/placeholder-product.svg');
        } elseif (preg_match('#^https?://#i', $rawImg)) {
            $image_url = $rawImg;
        } else {
            $image_url = fs_public_asset_url('/' . ltrim($rawImg, '/'));
        }
        
        $formatted_products[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'filipino_name' => $product['filipino_name'],
            'description' => $product['description'] ?? '',
            'current_price' => '₱' . number_format($product['current_price'], 2),
            'unit' => $product['unit'],
            'image_url' => $image_url,
            'market_id' => $product['market_id'],
            'market_name' => $product['market_name'],
            'unit_options' => $unit_options
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $formatted_products
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching market products by category: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load products. Please try again.'
    ]);
}
?>

