<?php
/**
 * API endpoint to get total unread message count for the logged-in user
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'unread_count' => 0
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => true,
        'unread_count' => 0
    ]);
    exit;
}

// Validate CSRF token
$csrf_token = $_GET['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'unread_count' => 0
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
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'reservation_messages'");
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'unread_count' => 0
        ]);
        exit;
    }
    
    // Check if message_type column exists
    $has_message_type = false;
    try {
        $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
        $has_message_type = $column_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, continue without it
    }
    
    // Count unread messages
    // Messages are unread if:
    // - sender_id != current user (message is from the other party)
    // - read_at IS NULL (not yet read)
    // - message_type != 'system' (exclude system messages)
    if ($user_role === 'farmer') {
        // Farmer's unread: messages from customers
        $query = "SELECT COUNT(*) as unread_count
                  FROM reservation_messages rm
                  INNER JOIN reservations r ON rm.reservation_id = r.id
                  WHERE r.farmer_id = :user_id
                  AND rm.sender_id != :user_id
                  AND rm.read_at IS NULL";
        if ($has_message_type) {
            $query .= " AND (rm.message_type IS NULL OR rm.message_type != 'system')";
        }
    } else {
        // User's unread: messages from farmers
        $query = "SELECT COUNT(*) as unread_count
                  FROM reservation_messages rm
                  INNER JOIN reservations r ON rm.reservation_id = r.id
                  WHERE r.user_id = :user_id
                  AND rm.sender_id != :user_id
                  AND rm.read_at IS NULL";
        if ($has_message_type) {
            $query .= " AND (rm.message_type IS NULL OR rm.message_type != 'system')";
        }
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $unread_count = intval($result['unread_count'] ?? 0);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error fetching unread count: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => true,
        'unread_count' => 0
    ]);
    exit;
}

