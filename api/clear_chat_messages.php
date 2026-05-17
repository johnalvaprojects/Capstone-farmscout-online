<?php
/**
 * API endpoint to clear all user/farmer messages from a reservation
 * System messages are preserved
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
            'message' => 'An error occurred while clearing messages.'
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to clear messages.'
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
$reservation_id = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
$thread_id = $reservation_id;

if ($reservation_id <= 0) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reservation ID.'
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

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Verify user has access to this reservation
    $reservation_query = "SELECT user_id, farmer_id FROM reservations WHERE id = :reservation_id";
    $reservation_stmt = $conn->prepare($reservation_query);
    $reservation_stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
    $reservation_stmt->execute();
    $reservation = $reservation_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Reservation not found.'
        ]);
        exit;
    }
    
    // Verify user has access (must be either user or farmer)
    if ($reservation['user_id'] != $user_id && $reservation['farmer_id'] != $user_id) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this reservation.'
        ]);
        exit;
    }
    
    // Delete all user/farmer messages (preserve system messages)
    if ($has_message_type) {
        $delete_query = "DELETE FROM reservation_messages 
                        WHERE reservation_id = :reservation_id 
                        AND (message_type IS NULL OR message_type != 'system')";
    } else {
        // If message_type column doesn't exist, delete all messages (backward compatibility)
        $delete_query = "DELETE FROM reservation_messages WHERE reservation_id = :reservation_id";
    }
    
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    
    if ($delete_stmt->execute()) {
        $deleted_count = $delete_stmt->rowCount();
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Chat cleared successfully.',
            'deleted_count' => $deleted_count
        ]);
        exit;
    } else {
        throw new Exception('Failed to clear messages');
    }
    
} catch (Exception $e) {
    error_log("Error clearing messages: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while clearing messages.'
    ]);
    exit;
}

