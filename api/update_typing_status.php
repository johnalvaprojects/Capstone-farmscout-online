<?php
require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';
require_once '../includes/fs_reservation_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate CSRF token
if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$user_id = $_SESSION['user_id'];
$reservation_id = intval($input['reservation_id'] ?? 0);
$thread_id = $reservation_id;
$is_typing = isset($input['is_typing']) ? (bool)$input['is_typing'] : false;

if ($reservation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if typing_status table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'typing_status'");
    if ($table_check->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Typing status table does not exist. Please run migration: run_typing_status_migration.php'
        ]);
        exit;
    }

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Verify reservation exists and user has access
    $reservation_check = $conn->prepare("
        SELECT id FROM reservations 
        WHERE id = :reservation_id 
        AND (user_id = :user_id OR farmer_id = :user_id)
    ");
    $reservation_check->execute([
        ':reservation_id' => $reservation_id,
        ':user_id' => $user_id
    ]);
    
    if ($reservation_check->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or access denied']);
        exit;
    }
    
    // Insert or update typing status using REPLACE (simpler and more reliable)
    $is_typing_value = $is_typing ? 1 : 0;
    
    // Use REPLACE INTO which will INSERT if not exists, or DELETE and INSERT if exists
    // This avoids the duplicate key error
    $replace_query = "
        REPLACE INTO typing_status (reservation_id, user_id, is_typing, last_activity)
        VALUES (:reservation_id, :user_id, :is_typing, NOW())
    ";
    $replace_stmt = $conn->prepare($replace_query);
    $replace_stmt->execute([
        ':reservation_id' => $thread_id,
        ':user_id' => $user_id,
        ':is_typing' => $is_typing_value
    ]);
    
    error_log("Typing status REPLACED: reservation_id=$thread_id, user_id=$user_id, is_typing=$is_typing_value");
    
    // Verify the update worked by checking what's in the database
    $verify_query = "SELECT * FROM typing_status WHERE reservation_id = :reservation_id AND user_id = :user_id";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->execute([
        ':reservation_id' => $thread_id,
        ':user_id' => $user_id
    ]);
    $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    if ($verify_result) {
        error_log("Typing status verification SUCCESS: " . json_encode($verify_result));
    } else {
        error_log("Typing status verification FAILED: Record not found after insert/update!");
    }
    
    // Auto-cleanup: Remove typing statuses older than 15 seconds (increased to give more buffer)
    // Only clean up records that are both old AND not currently typing
    // BUT: Don't clean up the record we just inserted/updated
    $cleanup_query = "DELETE FROM typing_status 
                      WHERE last_activity < DATE_SUB(NOW(), INTERVAL 15 SECOND)
                      AND is_typing = 0
                      AND NOT (reservation_id = :reservation_id AND user_id = :user_id)";
    $cleanup_stmt = $conn->prepare($cleanup_query);
    $cleanup_stmt->execute([
        ':reservation_id' => $thread_id,
        ':user_id' => $user_id
    ]);
    $cleanup_count = $cleanup_stmt->rowCount();
    if ($cleanup_count > 0) {
        error_log("Cleaned up $cleanup_count old inactive typing statuses (excluding current user)");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Typing status updated',
        'debug' => [
            'reservation_id' => $thread_id,
            'user_id' => $user_id,
            'is_typing' => $is_typing ? 1 : 0,
            'verified' => $verify_result ? 'found' : 'not_found'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error updating typing status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating typing status: ' . $e->getMessage()
    ]);
}
