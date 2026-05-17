<?php
require_once '../config/database.php';
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

// Allow GET or POST requests
$query = isset($_GET['q']) ? trim($_GET['q']) : (isset($_POST['q']) ? trim($_POST['q']) : '');

if (empty($query)) {
    echo json_encode(['error' => 'Product name is required']);
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
    
    // Search for markets that have the product
    // Case-insensitive search, partial match
    $searchTerm = '%' . $query . '%';
    
    $stmt = $conn->prepare("
        SELECT DISTINCT
            m.id as market_id,
            m.market_name,
            m.address,
            m.latitude,
            m.longitude,
            m.operating_hours,
            mp.product_name,
            mp.price as legacy_price,
            mp.unit as legacy_unit,
            mp.id as product_id,
            COUNT(DISTINCT mf.farmer_id) as farmer_count,
            COUNT(DISTINCT mp2.id) as product_count
        FROM market_products mp
        JOIN markets m ON mp.market_id = m.id
        LEFT JOIN market_farmers mf ON m.id = mf.market_id AND mf.approval_status = 'approved'
        LEFT JOIN market_products mp2 ON m.id = mp2.market_id
        WHERE LOWER(mp.product_name) LIKE LOWER(?)
        AND mp.is_available = 1
        $deletedAtCheck
        $approvalCheck
        AND m.status = 'active'
        GROUP BY m.id, mp.id, mp.product_name, mp.price, mp.unit
        ORDER BY m.market_name ASC
    ");
    
    $stmt->execute([$searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Attach unit options to get correct pricing
    $products = [];
    foreach ($results as $row) {
        $products[] = [
            'id' => $row['product_id'],
            'market_product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'price' => $row['legacy_price'],
            'unit' => $row['legacy_unit'],
            'market_id' => $row['market_id'],
            'market_name' => $row['market_name'],
            'address' => $row['address'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'operating_hours' => $row['operating_hours'],
            'farmer_count' => $row['farmer_count'],
            'product_count' => $row['product_count']
        ];
    }
    
    // Use attachUnitOptionsToProducts to get correct prices from market_product_units
    $products = attachUnitOptionsToProducts($products);
    
    if (empty($products)) {
        echo json_encode([
            'markets' => [],
            'product_name' => $query,
            'message' => 'No markets found with this product'
        ]);
        exit;
    }
    
    // Group by market (in case a market has multiple entries for the same product)
    $markets = [];
    $cheapestPrice = null;
    
    foreach ($products as $product) {
        $marketId = $product['market_id'];
        
        // Get the price from unit_options if available, otherwise use legacy price
        $productPrice = $product['price'] ?? $product['legacy_price'] ?? 0;
        $productUnit = $product['unit'] ?? $product['legacy_unit'] ?? 'kg';
        
        // If unit_options exist, use the default or first option's price
        if (!empty($product['unit_options']) && is_array($product['unit_options'])) {
            $defaultOption = null;
            foreach ($product['unit_options'] as $option) {
                if (!empty($option['is_default'])) {
                    $defaultOption = $option;
                    break;
                }
            }
            if (!$defaultOption) {
                $defaultOption = $product['unit_options'][0];
            }
            if ($defaultOption) {
                $productPrice = floatval($defaultOption['price']);
                $productUnit = $defaultOption['display_label'] ?? $defaultOption['unit_label'] ?? $productUnit;
            }
        } else {
            $productPrice = floatval($productPrice);
        }
        
        // Find the cheapest price for this product at this market
        if (!isset($markets[$marketId])) {
            $markets[$marketId] = [
                'id' => $marketId,
                'market_id' => $marketId,
                'market_name' => $product['market_name'],
                'address' => $product['address'],
                'latitude' => $product['latitude'],
                'longitude' => $product['longitude'],
                'operating_hours' => $product['operating_hours'],
                'farmer_count' => $product['farmer_count'],
                'product_count' => $product['product_count'],
                'product_name' => $product['product_name'],
                'price' => $productPrice,
                'unit' => $productUnit,
                'product_id' => $product['id']
            ];
            
            // Track cheapest price
            if ($cheapestPrice === null || $productPrice < $cheapestPrice) {
                $cheapestPrice = $productPrice;
            }
        } else {
            // If market already exists, keep the cheapest price
            if ($productPrice < $markets[$marketId]['price']) {
                $markets[$marketId]['price'] = $productPrice;
                $markets[$marketId]['unit'] = $productUnit;
                $markets[$marketId]['product_id'] = $product['id'];
            }
        }
    }
    
    // Mark which markets have the cheapest price
    foreach ($markets as &$market) {
        $market['is_cheapest'] = ($market['price'] == $cheapestPrice);
        $market['price_diff'] = $market['price'] - $cheapestPrice;
    }
    unset($market);
    
    // Convert to indexed array and sort by price
    $marketsArray = array_values($markets);
    usort($marketsArray, function($a, $b) {
        return $a['price'] <=> $b['price'];
    });
    
    echo json_encode([
        'markets' => $marketsArray,
        'product_name' => $query,
        'cheapest_price' => $cheapestPrice,
        'count' => count($marketsArray)
    ]);
    
} catch (Exception $e) {
    error_log('Error searching product markets: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to search markets. Please try again.']);
}
?>

