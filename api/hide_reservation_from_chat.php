<?php
/**
 * API endpoint to hide/show a reservation from chat view
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
            'message' => 'An error occurred.'
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in.'
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
$user_role = $_SESSION['user_role'] ?? 'user';
$reservation_id = isset($_POST['reservation_id']) ? intval($_POST['reservation_id']) : 0;
$thread_id = $reservation_id;
$hide = isset($_POST['hide']) ? (intval($_POST['hide']) === 1) : true;

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
    
    // Check if columns exist
    $check_user = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hide_from_chat_user'");
    $check_farmer = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hide_from_chat_farmer'");
    
    if ($check_user->rowCount() === 0 || $check_farmer->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Feature not available. Please run migration.'
        ]);
        exit;
    }

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Verify user has access to this reservation
    $reservation_query = "SELECT user_id, farmer_id FROM reservations WHERE id = :reservation_id";
    $reservation_stmt = $conn->prepare($reservation_query);
    $reservation_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
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
    
    // Update the appropriate column based on user role
    $hide_value = $hide ? 1 : 0;
    
    $whereGroup = fs_reservations_parent_column_exists($conn)
        ? '(id = :reservation_id OR parent_reservation_id = :reservation_id2)'
        : 'id = :reservation_id';
    if ($user_role === 'farmer') {
        $update_query = "UPDATE reservations SET hide_from_chat_farmer = :hide_value WHERE " . $whereGroup;
    } else {
        $update_query = "UPDATE reservations SET hide_from_chat_user = :hide_value WHERE " . $whereGroup;
    }
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':hide_value', $hide_value, PDO::PARAM_INT);
    $update_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    if (fs_reservations_parent_column_exists($conn)) {
        $update_stmt->bindParam(':reservation_id2', $thread_id, PDO::PARAM_INT);
    }
    
    if ($update_stmt->execute()) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $hide ? 'Reservation hidden from chat.' : 'Reservation shown in chat.',
            'hidden' => $hide
        ]);
        exit;
    } else {
        throw new Exception('Failed to update reservation');
    }
    
} catch (Exception $e) {
    error_log("Error hiding reservation from chat: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred.'
    ]);
    exit;
}

