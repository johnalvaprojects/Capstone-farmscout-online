<?php
/**
 * API endpoint to fetch all chat conversations for the logged-in user
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';
require_once '../includes/fs_reservation_helpers.php';

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching conversations.'
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view conversations.'
    ]);
    exit;
}

// Validate CSRF token
$csrf_token = $_GET['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'user';

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $resTable = $conn->query("SHOW TABLES LIKE 'reservations'");
    if ($resTable->rowCount() === 0) {
        ob_clean();
        echo json_encode(['success' => true, 'conversations' => []]);
        exit;
    }

    $rmTable = $conn->query("SHOW TABLES LIKE 'reservation_messages'");
    $has_reservation_messages = $rmTable->rowCount() > 0;

    $hasParent = fs_reservations_parent_column_exists($conn);
    $rootFilter = $hasParent ? ' AND r.parent_reservation_id IS NULL ' : '';
    $hasRefs = fs_reservations_public_ref_columns_exist($conn);
    $refCols = $hasRefs ? 'r.public_ref, r.chat_public_ref,' : 'NULL AS public_ref, NULL AS chat_public_ref,';
    $productSummaryExpr = $hasParent
        ? "(SELECT GROUP_CONCAT(CONCAT(r2.quantity, ' ', r2.unit, ' ', mp2.product_name) ORDER BY r2.id SEPARATOR '; ') FROM reservations r2 INNER JOIN market_products mp2 ON r2.product_id = mp2.id WHERE r2.id = r.id OR r2.parent_reservation_id = r.id) AS product_summary"
        : 'mp.product_name AS product_summary';

    $has_message_type = false;
    if ($has_reservation_messages) {
        $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
        $has_message_type = $column_check->rowCount() > 0;
    }

    // Build query based on user role.
    // Include reservations even when no messages yet. If reservation_messages table is missing, list threads from reservations only (run install_missing_dashboard_tables.sql to enable messaging).
    $orderExpr = $has_reservation_messages
        ? "COALESCE(
                (SELECT rm.created_at FROM reservation_messages rm
                 WHERE rm.reservation_id = r.id
                 ORDER BY rm.created_at DESC LIMIT 1),
                r.updated_at,
                r.created_at
              )"
        : 'COALESCE(r.updated_at, r.created_at)';

    if ($has_reservation_messages) {
        if ($user_role === 'farmer') {
            $query = "SELECT r.id as reservation_id,
                  r.status,
                  mp.product_name,
                  $refCols
                  $productSummaryExpr,
                  u.full_name as other_party_name,
                  u.username as other_party_username,
                  (SELECT COUNT(*) FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   AND rm.sender_id != :user_id 
                   " . ($has_message_type ? "AND (rm.message_type IS NULL OR rm.message_type != 'system')" : "") . "
                   AND rm.read_at IS NULL) as unread_count,
                  (SELECT rm.message_body FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   ORDER BY rm.created_at DESC LIMIT 1) as last_message,
                  (SELECT rm.created_at FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   ORDER BY rm.created_at DESC LIMIT 1) as last_message_time
                  FROM reservations r
                  INNER JOIN market_products mp ON r.product_id = mp.id
                  INNER JOIN users u ON r.user_id = u.id
                  WHERE r.farmer_id = :user_id
                  $rootFilter
                  ORDER BY $orderExpr DESC";
        } else {
            $query = "SELECT r.id as reservation_id,
                  r.status,
                  mp.product_name,
                  $refCols
                  $productSummaryExpr,
                  u.full_name as other_party_name,
                  u.username as other_party_username,
                  (SELECT COUNT(*) FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   AND rm.sender_id != :user_id 
                   " . ($has_message_type ? "AND (rm.message_type IS NULL OR rm.message_type != 'system')" : "") . "
                   AND rm.read_at IS NULL) as unread_count,
                  (SELECT rm.message_body FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   ORDER BY rm.created_at DESC LIMIT 1) as last_message,
                  (SELECT rm.created_at FROM reservation_messages rm 
                   WHERE rm.reservation_id = r.id 
                   ORDER BY rm.created_at DESC LIMIT 1) as last_message_time
                  FROM reservations r
                  INNER JOIN market_products mp ON r.product_id = mp.id
                  INNER JOIN users u ON r.farmer_id = u.id
                  WHERE r.user_id = :user_id
                  $rootFilter
                  ORDER BY $orderExpr DESC";
        }
    } else {
        if ($user_role === 'farmer') {
            $query = "SELECT r.id as reservation_id,
                  r.status,
                  mp.product_name,
                  $refCols
                  $productSummaryExpr,
                  u.full_name as other_party_name,
                  u.username as other_party_username,
                  0 as unread_count,
                  NULL as last_message,
                  NULL as last_message_time
                  FROM reservations r
                  INNER JOIN market_products mp ON r.product_id = mp.id
                  INNER JOIN users u ON r.user_id = u.id
                  WHERE r.farmer_id = :user_id
                  $rootFilter
                  ORDER BY $orderExpr DESC";
        } else {
            $query = "SELECT r.id as reservation_id,
                  r.status,
                  mp.product_name,
                  $refCols
                  $productSummaryExpr,
                  u.full_name as other_party_name,
                  u.username as other_party_username,
                  0 as unread_count,
                  NULL as last_message,
                  NULL as last_message_time
                  FROM reservations r
                  INNER JOIN market_products mp ON r.product_id = mp.id
                  INNER JOIN users u ON r.farmer_id = u.id
                  WHERE r.user_id = :user_id
                  $rootFilter
                  ORDER BY $orderExpr DESC";
        }
    }
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("SQL Error in get_chat_conversations: " . $e->getMessage());
        error_log("Query: " . $query);
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
    
    // Format conversations
    $formatted_conversations = [];
    foreach ($conversations as $conv) {
        $formatted_conversations[] = [
            'reservation_id' => intval($conv['reservation_id']),
            'product_name' => $conv['product_name'],
            'product_summary' => $conv['product_summary'] ?? $conv['product_name'],
            'public_ref' => $conv['public_ref'] ?? null,
            'chat_public_ref' => $conv['chat_public_ref'] ?? null,
            'other_party_name' => $conv['other_party_name'] ?: $conv['other_party_username'],
            'status' => $conv['status'],
            'unread_count' => intval($conv['unread_count'] ?? 0),
            'last_message' => $conv['last_message'] ? substr($conv['last_message'], 0, 50) : '',
            'last_message_time' => $conv['last_message_time'],
            'user_role' => $user_role // Include user role to determine label
        ];
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'conversations' => $formatted_conversations
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching conversations.',
        'error' => $e->getMessage() // Include error in response for debugging
    ]);
    exit;
}

