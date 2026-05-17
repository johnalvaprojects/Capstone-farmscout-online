<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

$market_id = isset($_GET['market_id']) ? intval($_GET['market_id']) : 0;

if ($market_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid market ID is required']);
    exit;
}

try {
    // Use the existing function to get categories with product counts
    $categories = getProductsByCategories([$market_id]);
    
    // Format the response
    $formatted_categories = [];
    foreach ($categories as $category) {
        $formatted_categories[] = [
            'category_id' => $category['category_id'] ?? null,
            'category_name' => $category['category_name'] ?? $category['name'] ?? '',
            'filipino_name' => $category['category_filipino'] ?? $category['filipino_name'] ?? '',
            'description' => $category['category_description'] ?? $category['description'] ?? '',
            'product_count' => intval($category['product_count'] ?? 0),
            'icon_path' => $category['icon_path'] ?? null,
            'price_range' => $category['price_range'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $formatted_categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
?>

