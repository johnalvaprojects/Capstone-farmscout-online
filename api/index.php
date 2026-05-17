<?php
require_once '../includes/enhanced_functions.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$path = trim($path, '/');

// Route the request
try {
    switch ($path) {
        case 'products':
            handleProducts($method);
            break;
        case 'categories':
            handleCategories($method);
            break;
        case 'search':
            handleSearch($method);
            break;
        case 'price-alerts':
            handlePriceAlerts($method);
            break;
        case 'shopping-list':
            handleShoppingList($method);
            break;
        case 'market-status':
            handleMarketStatus($method);
            break;
        case 'analytics':
            handleAnalytics($method);
            break;
        default:
            sendError('Endpoint not found', 404);
    }
} catch (Exception $e) {
    sendError('Internal server error: ' . $e->getMessage(), 500);
}

function handleProducts($method) {
    switch ($method) {
        case 'GET':
            $category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
            $featured = isset($_GET['featured']) ? true : false;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            if ($category_id) {
                $products = getProductsByCategory($category_id, $limit);
            } elseif ($featured) {
                $products = getFeaturedProducts($limit ?: 4);
            } else {
                $products = getAllProducts($limit, $offset);
            }
            
            sendSuccess($products);
            break;
            
        case 'POST':
            // Create new product (admin only)
            if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
                sendError('Unauthorized', 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                sendError('Invalid JSON data', 400);
            }
            
            $required_fields = ['name', 'filipino_name', 'category_id', 'current_price'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendError("Missing required field: $field", 400);
                }
            }
            
            $product_data = [
                'name' => sanitizeInput($data['name']),
                'filipino_name' => sanitizeInput($data['filipino_name']),
                'description' => sanitizeInput($data['description'] ?? ''),
                'category_id' => intval($data['category_id']),
                'current_price' => floatval($data['current_price']),
                'previous_price' => floatval($data['previous_price'] ?? 0),
                'unit' => sanitizeInput($data['unit'] ?? 'kg'),
                'image_url' => sanitizeInput($data['image_url'] ?? ''),
                'is_featured' => isset($data['is_featured']) ? (bool)$data['is_featured'] : false
            ];
            
            if (addProduct($product_data)) {
                sendSuccess(null, 'Product created successfully');
            } else {
                sendError('Failed to create product', 500);
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function handleCategories($method) {
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
    }
    
    $categories = getCategories();
    sendSuccess($categories);
}

function handleSearch($method) {
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
    }
    
    $search_term = sanitizeInput($_GET['q'] ?? '');
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
    $sort_by = sanitizeInput($_GET['sort'] ?? 'relevance');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    
    if (empty($search_term)) {
        sendError('Search term is required', 400);
    }
    
    // Track search
    trackSearch($search_term);
    
    $products = searchProducts($search_term, $category_id, $sort_by);
    $products = array_slice($products, 0, $limit);
    
    sendSuccess([
        'query' => $search_term,
        'results' => $products,
        'total' => count($products)
    ]);
}

function handlePriceAlerts($method) {
    switch ($method) {
        case 'GET':
            $email = sanitizeInput($_GET['email'] ?? '');
            if (empty($email)) {
                sendError('Email is required', 400);
            }
            
            $alerts = getPriceAlerts($email);
            sendSuccess($alerts);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                sendError('Invalid JSON data', 400);
            }
            
            $email = sanitizeInput($data['email'] ?? '');
            $product_id = intval($data['product_id'] ?? 0);
            $target_price = floatval($data['target_price'] ?? 0);
            $alert_type = sanitizeInput($data['alert_type'] ?? 'below');
            
            if (!validateEmail($email)) {
                sendError('Invalid email address', 400);
            }
            
            if ($product_id <= 0) {
                sendError('Invalid product ID', 400);
            }
            
            if ($target_price <= 0) {
                sendError('Invalid target price', 400);
            }
            
            if (addPriceAlert($email, $product_id, $target_price, $alert_type)) {
                sendSuccess(null, 'Price alert created successfully');
            } else {
                sendError('Failed to create price alert', 500);
            }
            break;
            
        case 'DELETE':
            $alert_id = intval($_GET['id'] ?? 0);
            if ($alert_id <= 0) {
                sendError('Invalid alert ID', 400);
            }
            
            $conn = getDB();
            if ($conn) {
                $query = "UPDATE price_alerts SET is_active = 0 WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $alert_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    sendSuccess(null, 'Price alert removed successfully');
                } else {
                    sendError('Failed to remove price alert', 500);
                }
            } else {
                sendError('Database connection failed', 500);
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function handleShoppingList($method) {
    $session_id = sanitizeInput($_GET['session'] ?? session_id());
    
    // Debug logging - log session info
    if (isset($_GET['debug']) || isset($_POST['debug'])) {
        error_log("ShoppingList API Debug - Method: $method, Session ID: $session_id, Session status: " . 
                 (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active'));
    }
    
    switch ($method) {
        case 'GET':
            $items = getShoppingList($session_id);
            sendSuccess($items);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                sendError('Invalid JSON data', 400);
            }
            
            $product_id = intval($data['product_id'] ?? 0);
            $quantity = floatval($data['quantity'] ?? 1);
            $notes = sanitizeInput($data['notes'] ?? '');
            
            if ($product_id <= 0) {
                sendError('Invalid product ID', 400);
            }
            
            if (addToShoppingList($session_id, $product_id, $quantity, $notes)) {
                sendSuccess(null, 'Item added to shopping list');
            } else {
                sendError('Failed to add item to shopping list', 500);
            }
            break;
            
        case 'DELETE':
            $item_id = intval($_GET['id'] ?? 0);
            if ($item_id <= 0) {
                sendError('Invalid item ID', 400);
            }
            
            $conn = getDB();
            if ($conn) {
                $query = "DELETE FROM shopping_lists WHERE id = :id AND user_session = :session";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $item_id, PDO::PARAM_INT);
                $stmt->bindParam(':session', $session_id);
                if ($stmt->execute()) {
                    sendSuccess(null, 'Item removed from shopping list');
                } else {
                    sendError('Failed to remove item from shopping list', 500);
                }
            } else {
                sendError('Database connection failed', 500);
            }
            break;
            
        default:
            sendError('Method not allowed', 405);
    }
}

function handleMarketStatus($method) {
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
    }
    
    $status = getMarketStatus();
    sendSuccess($status);
}

function handleAnalytics($method) {
    if ($method !== 'GET') {
        sendError('Method not allowed', 405);
    }
    
    // Basic analytics data
    $analytics = [
        'total_products' => getTotalProductsCount(),
        'total_categories' => count(getCategories()),
        'market_status' => getMarketStatus(),
        'popular_searches' => getPopularSearches(),
        'price_trends' => getPriceTrends()
    ];
    
    sendSuccess($analytics);
}

function getPopularSearches() {
    // This would typically come from a search log table
    return [
        'Kamatis', 'Bangus', 'Bigas', 'Sibuyas', 'Manok', 'Baboy', 'Tilapia', 'Saging'
    ];
}

function getPriceTrends() {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT 
                DATE(recorded_at) as date,
                AVG(price) as avg_price,
                COUNT(*) as price_updates
              FROM price_history 
              WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              GROUP BY DATE(recorded_at)
              ORDER BY date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'code' => $code
    ]);
    exit;
}

function sendSuccess($data = null, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}
?>
