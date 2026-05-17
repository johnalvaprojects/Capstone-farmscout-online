<?php
require_once '../includes/enhanced_functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $market_id = isset($_POST['market_id']) ? intval($_POST['market_id']) : null;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    $conn = getDB();
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $user_session = session_id();
    
    // Check if already favorited
    $check_query = "SELECT id FROM favorites WHERE product_id = :product_id AND market_id " . ($market_id ? "= :market_id" : "IS NULL");
    if ($user_id) {
        $check_query .= " AND user_id = :user_id";
    } else {
        $check_query .= " AND user_session = :user_session";
    }
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    if ($market_id) {
        $check_stmt->bindParam(':market_id', $market_id, PDO::PARAM_INT);
    }
    if ($user_id) {
        $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        $check_stmt->bindParam(':user_session', $user_session);
    }
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        // Remove from favorites
        $delete_query = "DELETE FROM favorites WHERE product_id = :product_id AND market_id " . ($market_id ? "= :market_id" : "IS NULL");
        if ($user_id) {
            $delete_query .= " AND user_id = :user_id";
        } else {
            $delete_query .= " AND user_session = :user_session";
        }
        
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        if ($market_id) {
            $delete_stmt->bindParam(':market_id', $market_id, PDO::PARAM_INT);
        }
        if ($user_id) {
            $delete_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        } else {
            $delete_stmt->bindParam(':user_session', $user_session);
        }
        $delete_stmt->execute();
        
        echo json_encode(['success' => true, 'favorited' => false, 'message' => 'Removed from favorites']);
    } else {
        // Add to favorites
        $insert_query = "INSERT INTO favorites (user_id, user_session, product_id, market_id) VALUES (:user_id, :user_session, :product_id, :market_id)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':user_id', $user_id, $user_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert_stmt->bindParam(':user_session', $user_session);
        $insert_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':market_id', $market_id, $market_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert_stmt->execute();
        
        echo json_encode(['success' => true, 'favorited' => true, 'message' => 'Added to favorites']);
    }
} elseif ($method === 'GET') {
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $user_session = session_id();
    
    $conn = getDB();
    if (!$conn) {
        echo json_encode(['favorites' => []]);
        exit;
    }
    
    // Get all favorites
    $query = "SELECT f.product_id, f.market_id 
              FROM favorites f 
              WHERE " . ($user_id ? "f.user_id = :user_id" : "f.user_session = :user_session");
    
    $stmt = $conn->prepare($query);
    if ($user_id) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        $stmt->bindParam(':user_session', $user_session);
    }
    $stmt->execute();
    
    $favorites = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['product_id'] . '_' . ($row['market_id'] ?? '0');
        $favorites[$key] = true;
    }
    
    echo json_encode(['favorites' => $favorites]);
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

