<?php
/**
 * API endpoint to mark notification(s) as read
 */

// Start output buffering FIRST to catch any notices/warnings
ob_start();
ini_set('display_errors', 0);
error_reporting(0); // Suppress all errors/warnings/notices

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
        exit;
    }
});

// Check if user is logged in
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Please log in.']);
    exit;
}

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : null;
$mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] === 'true';

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Notifications system not available.']);
        exit;
    }
    
    if ($mark_all) {
        // Mark all notifications as read for this user
        $update_query = "UPDATE notifications 
                        SET is_read = 1, read_at = NOW() 
                        WHERE user_id = :user_id AND is_read = 0";
        $stmt = $conn->prepare($update_query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
        exit;
    } elseif ($notification_id) {
        // Mark specific notification as read
        $update_query = "UPDATE notifications 
                        SET is_read = 1, read_at = NOW() 
                        WHERE id = :notification_id AND user_id = :user_id";
        $stmt = $conn->prepare($update_query);
        $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            ob_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read.'
            ]);
            exit;
        } else {
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found or already read.'
            ]);
            exit;
        }
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request. Please specify notification_id or mark_all.'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred.'
    ]);
    exit;
}

