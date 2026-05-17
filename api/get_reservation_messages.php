<?php
/**
 * API endpoint to fetch messages for a reservation thread
 */

// Start output buffering FIRST
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';
require_once '../includes/fs_reservation_helpers.php';

header('Content-Type: application/json');

// Register shutdown function
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching messages.'
        ]);
        exit;
    }
});

// Check if user is logged in
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view messages.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
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
    
    // Check if reservation_messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'reservation_messages'");
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'messages' => [],
            'unread_count' => 0
        ]);
        exit;
    }

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Verify user has access to this reservation (thread is always the root row)
    $check_query = "SELECT user_id, farmer_id, public_ref, chat_public_ref FROM reservations WHERE id = :reservation_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $reservation = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Reservation not found.'
        ]);
        exit;
    }
    
    // Check access
    if ((int)$reservation['user_id'] != (int)$user_id && (int)$reservation['farmer_id'] != (int)$user_id) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this conversation.'
        ]);
        exit;
    }
    
    // Check if message_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
    $has_message_type = $column_check->rowCount() > 0;
    
    // Fetch messages
    $messages_query = "SELECT rm.*, u.full_name, u.username" . ($has_message_type ? ", rm.message_type" : "") . "
                      FROM reservation_messages rm
                      LEFT JOIN users u ON rm.sender_id = u.id
                      WHERE rm.reservation_id = :reservation_id
                      ORDER BY rm.created_at ASC";
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    $messages_stmt->execute();
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark messages as read (where current user is recipient) - only for non-system messages
    $recipient_id = ($reservation['user_id'] == $user_id) ? $reservation['farmer_id'] : $reservation['user_id'];
    if ($has_message_type) {
        $mark_read_query = "UPDATE reservation_messages 
                           SET read_at = NOW() 
                           WHERE reservation_id = :reservation_id 
                           AND sender_id = :recipient_id 
                           AND message_type != 'system'
                           AND read_at IS NULL";
    } else {
        $mark_read_query = "UPDATE reservation_messages 
                           SET read_at = NOW() 
                           WHERE reservation_id = :reservation_id 
                           AND sender_id = :recipient_id 
                           AND read_at IS NULL";
    }
    $mark_read_stmt = $conn->prepare($mark_read_query);
    $mark_read_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    $mark_read_stmt->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
    $mark_read_stmt->execute();
    
    // Get user role for message transformation
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Get customer name for farmer view
    $customer_name = null;
    if ($user_role === 'farmer') {
        $customer_query = "SELECT full_name, username FROM users WHERE id = :customer_id";
        $customer_stmt = $conn->prepare($customer_query);
        $customer_stmt->bindParam(':customer_id', $reservation['user_id'], PDO::PARAM_INT);
        $customer_stmt->execute();
        $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customer_name = $customer['full_name'] ?: $customer['username'];
        }
    }
    
    // Format messages
    $formatted_messages = [];
    foreach ($messages as $message) {
        $message_type = $has_message_type ? ($message['message_type'] ?? 'user') : 'user';
        $is_system = ($message_type === 'system');
        
        // For system messages, don't show sender name
        $sender_name = $is_system ? '' : ($message['full_name'] ?? $message['username'] ?? 'Unknown');
        $is_sender = ($message['sender_id'] == $user_id);
        
        // Transform system message text based on viewer role
        $message_body = $message['message_body'];
        if ($is_system) {
            $message_body = transformSystemMessage($message_body, $user_role, $reservation['user_id'], $user_id, $customer_name);
        }
        
        // Calculate relative time
        $timestamp = strtotime($message['created_at']);
        $time_ago = getTimeAgoLocal($timestamp);
        
        $formatted_messages[] = [
            'id' => intval($message['id']),
            'sender_id' => intval($message['sender_id']),
            'sender_name' => $sender_name,
            'is_sender' => $is_sender,
            'message_type' => $message_type,
            'is_system' => $is_system,
            'message_body' => $message_body,
            'created_at' => $message['created_at'],
            'time_ago' => $time_ago,
            'read_at' => $message['read_at']
        ];
    }
    
    // Count unread messages (messages sent by the other person that haven't been read)
    $unread_query = "SELECT COUNT(*) as unread_count
                    FROM reservation_messages
                    WHERE reservation_id = :reservation_id
                    AND sender_id = :recipient_id
                    AND read_at IS NULL";
    $unread_stmt = $conn->prepare($unread_query);
    $unread_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    $unread_stmt->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = intval($unread_result['unread_count'] ?? 0);

    $product_summary = '';
    if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
        $ps = $conn->prepare(
            "SELECT GROUP_CONCAT(CONCAT(r.quantity, ' ', r.unit, ' ', mp.product_name) ORDER BY r.id SEPARATOR '; ') AS s
             FROM reservations r
             INNER JOIN market_products mp ON r.product_id = mp.id
             WHERE r.id = :tid OR r.parent_reservation_id = :tid2"
        );
        $ps->execute([':tid' => $thread_id, ':tid2' => $thread_id]);
        $product_summary = (string)($ps->fetchColumn() ?: '');
    } else {
        $ps = $conn->prepare(
            "SELECT CONCAT(r.quantity, ' ', r.unit, ' ', mp.product_name) FROM reservations r
             INNER JOIN market_products mp ON r.product_id = mp.id WHERE r.id = :tid LIMIT 1"
        );
        $ps->execute([':tid' => $thread_id]);
        $product_summary = (string)($ps->fetchColumn() ?: '');
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'unread_count' => $unread_count,
        'reservation_id' => $thread_id,
        'public_ref' => $reservation['public_ref'] ?? null,
        'chat_public_ref' => $reservation['chat_public_ref'] ?? null,
        'product_summary' => $product_summary,
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching messages.',
        'error' => $e->getMessage() // Include error for debugging
    ]);
    exit;
} catch (Error $e) {
    error_log("Fatal error fetching messages: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'A fatal error occurred while fetching messages.',
        'error' => $e->getMessage() // Include error for debugging
    ]);
    exit;
}

/**
 * Get relative time string (e.g., "5 minutes ago")
 * Local function to avoid conflicts with enhanced_functions.php
 */
function getTimeAgoLocal($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y g:i A', $timestamp);
    }
}

/**
 * Transform system message text based on viewer role
 * @param string $message_body Original message text
 * @param string $viewer_role Role of the person viewing (user or farmer)
 * @param int $customer_id ID of the customer who made the reservation
 * @param int $viewer_id ID of the person viewing the message
 * @param string|null $customer_name Name of the customer (for farmer view)
 * @return string Transformed message text
 */
function transformSystemMessage($message_body, $viewer_role, $customer_id, $viewer_id, $customer_name = null) {
    $is_customer = ($viewer_role === 'user' && $viewer_id == $customer_id);
    
    // Transform "You reserved..." messages
    if (preg_match('/^You reserved:?\s*(.+)$/i', $message_body, $matches)) {
        if ($is_customer) {
            // Customer viewing: keep "You reserved..."
            return $message_body;
        } else {
            // Farmer viewing: change to "Customer reserved..." or "[Name] reserved..."
            $rest = $matches[1];
            if ($customer_name) {
                return $customer_name . " reserved " . $rest;
            } else {
                return "Customer reserved " . $rest;
            }
        }
    }
    
    // Transform "You cancelled..." messages
    if (preg_match('/^You cancelled (.+)$/i', $message_body, $matches)) {
        if ($is_customer) {
            // Customer viewing: keep "You cancelled..."
            return $message_body;
        } else {
            // Farmer viewing: change to "Customer cancelled..." or "[Name] cancelled..."
            $rest = $matches[1];
            if ($customer_name) {
                return $customer_name . " cancelled " . $rest;
            } else {
                return "Customer cancelled " . $rest;
            }
        }
    }
    
    // Transform "Reservation accepted by farmer" - already correct for both
    // Transform "Reservation declined by farmer" - already correct for both
    // Transform "Reservation marked as completed" - already correct for both
    
    return $message_body;
}
