<?php
/**
 * API endpoint to delete a chat message
 * Users can only delete their own messages
 * System messages cannot be deleted
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
            'message' => 'An error occurred while deleting the message.'
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to delete messages.'
    ]);
    exit;
}

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf_token)) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

if ($message_id <= 0) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid message ID.'
    ]);
    exit;
}

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
            'success' => false,
            'message' => 'Chat system not available.'
        ]);
        exit;
    }
    
    // Check if message_type column exists
    $has_message_type = false;
    try {
        $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
        $has_message_type = $column_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist
    }
    
    // Get message details and verify ownership
    $message_query = "SELECT rm.*, r.user_id, r.farmer_id
                      FROM reservation_messages rm
                      INNER JOIN reservations r ON rm.reservation_id = r.id
                      WHERE rm.id = :message_id";
    
    $message_stmt = $conn->prepare($message_query);
    $message_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $message_stmt->execute();
    $message = $message_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Message not found.'
        ]);
        exit;
    }
    
    // Verify user has access to this reservation
    if ($message['user_id'] != $user_id && $message['farmer_id'] != $user_id) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this message.'
        ]);
        exit;
    }
    
    // Check if message is a system message (cannot be deleted)
    if ($has_message_type && isset($message['message_type']) && $message['message_type'] === 'system') {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'System messages cannot be deleted.'
        ]);
        exit;
    }
    
    // Verify user owns the message (can only delete own messages)
    if ($message['sender_id'] != $user_id) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'You can only delete your own messages.'
        ]);
        exit;
    }
    
    // Delete the message
    $delete_query = "DELETE FROM reservation_messages WHERE id = :message_id";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    
    if ($delete_stmt->execute()) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Message deleted successfully.'
        ]);
        exit;
    } else {
        throw new Exception('Failed to delete message');
    }
    
} catch (Exception $e) {
    error_log("Error deleting message: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the message.'
    ]);
    exit;
}

