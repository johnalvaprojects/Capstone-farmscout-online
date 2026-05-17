<?php
/**
 * API endpoint to fetch ALL messages from ALL reservations for the logged-in user
 * Returns messages grouped by reservation_id
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
            'message' => 'An error occurred while fetching messages.'
        ]);
        exit;
    }
});

if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view messages.'
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
    
    // Check if tables exist
    $table_check = $conn->query("SHOW TABLES LIKE 'reservation_messages'");
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'messages' => [],
            'reservations' => []
        ]);
        exit;
    }
    
    // Check if message_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
    $has_message_type = $column_check->rowCount() > 0;
    
    // Check if hide_from_chat columns exist
    $has_hide_columns = false;
    try {
        $check_user = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hide_from_chat_user'");
        $check_farmer = $conn->query("SHOW COLUMNS FROM reservations LIKE 'hide_from_chat_farmer'");
        $has_hide_columns = ($check_user->rowCount() > 0 && $check_farmer->rowCount() > 0);
    } catch (Exception $e) {
        // Columns don't exist
    }
    
    // Get all reservations with messages for this user
    // Filter out reservations hidden by the current user
    if ($user_role === 'farmer') {
        $hide_condition = $has_hide_columns ? "AND (r.hide_from_chat_farmer = 0 OR r.hide_from_chat_farmer IS NULL)" : "";
        $reservations_query = "SELECT DISTINCT r.id as reservation_id,
                              r.status,
                              mp.product_name,
                              u.full_name as other_party_name,
                              u.username as other_party_username
                              FROM reservations r
                              INNER JOIN reservation_messages rm ON r.id = rm.reservation_id
                              INNER JOIN market_products mp ON r.product_id = mp.id
                              INNER JOIN users u ON r.user_id = u.id
                              WHERE r.farmer_id = :user_id
                              $hide_condition
                              ORDER BY r.created_at DESC";
    } else {
        $hide_condition = $has_hide_columns ? "AND (r.hide_from_chat_user = 0 OR r.hide_from_chat_user IS NULL)" : "";
        $reservations_query = "SELECT DISTINCT r.id as reservation_id,
                              r.status,
                              mp.product_name,
                              u.full_name as other_party_name,
                              u.username as other_party_username
                              FROM reservations r
                              INNER JOIN reservation_messages rm ON r.id = rm.reservation_id
                              INNER JOIN market_products mp ON r.product_id = mp.id
                              INNER JOIN users u ON r.farmer_id = u.id
                              WHERE r.user_id = :user_id
                              $hide_condition
                              ORDER BY r.created_at DESC";
    }
    
    $reservations_stmt = $conn->prepare($reservations_query);
    $reservations_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $reservations_stmt->execute();
    $reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all messages from all reservations
    // Filter out messages from hidden reservations
    $hide_condition = $has_hide_columns ? 
        ($user_role === 'farmer' ? "AND (r.hide_from_chat_farmer = 0 OR r.hide_from_chat_farmer IS NULL)" : 
         "AND (r.hide_from_chat_user = 0 OR r.hide_from_chat_user IS NULL)") : "";
    
    $messages_query = "SELECT rm.*, 
                      r.id as reservation_id,
                      mp.product_name,
                      u.full_name as sender_full_name,
                      u.username as sender_username" . 
                      ($has_message_type ? ", rm.message_type" : "") . "
                      FROM reservation_messages rm
                      INNER JOIN reservations r ON rm.reservation_id = r.id
                      INNER JOIN market_products mp ON r.product_id = mp.id
                      LEFT JOIN users u ON rm.sender_id = u.id
                      WHERE " . ($user_role === 'farmer' ? "r.farmer_id" : "r.user_id") . " = :user_id
                      $hide_condition
                      ORDER BY rm.created_at ASC";
    
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $messages_stmt->execute();
    $all_messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user role for message transformation
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Get reservation details for each reservation to transform system messages
    $reservation_details = [];
    foreach ($reservations as $res) {
        $reservation_details[$res['reservation_id']] = [
            'user_id' => null,
            'customer_name' => null
        ];
    }
    
    // Fetch customer details for each reservation
    if ($user_role === 'farmer' && !empty($reservation_details)) {
        $reservation_ids = array_keys($reservation_details);
        if (!empty($reservation_ids)) {
            $placeholders = implode(',', array_fill(0, count($reservation_ids), '?'));
            $res_query = "SELECT r.id, r.user_id, u.full_name, u.username 
                         FROM reservations r
                         LEFT JOIN users u ON r.user_id = u.id
                         WHERE r.id IN ($placeholders)";
            $res_stmt = $conn->prepare($res_query);
            $res_stmt->execute($reservation_ids);
            $res_data = $res_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($res_data as $res_data_item) {
                $res_id = intval($res_data_item['id']);
                $reservation_details[$res_id]['user_id'] = intval($res_data_item['user_id']);
                $reservation_details[$res_id]['customer_name'] = $res_data_item['full_name'] ?: $res_data_item['username'];
            }
        }
    }
    
    // Format messages
    $formatted_messages = [];
    foreach ($all_messages as $msg) {
        $message_type = $has_message_type ? ($msg['message_type'] ?? 'user') : 'user';
        $is_system = ($message_type === 'system');
        
        $sender_name = $is_system ? '' : ($msg['sender_full_name'] ?: $msg['sender_username'] ?? 'Unknown');
        $is_sender = ($msg['sender_id'] == $user_id);
        
        // Transform system message text based on viewer role
        $message_body = $msg['message_body'];
        if ($is_system) {
            $res_id = intval($msg['reservation_id']);
            $res_detail = $reservation_details[$res_id] ?? null;
            if ($res_detail) {
                $customer_id = $res_detail['user_id'];
                $customer_name = $res_detail['customer_name'];
                $message_body = transformSystemMessage($message_body, $user_role, $customer_id, $user_id, $customer_name);
            }
        }
        
        // Calculate relative time
        $timestamp = strtotime($msg['created_at']);
        $time_ago = '';
        if ($timestamp) {
            $diff = time() - $timestamp;
            if ($diff < 60) {
                $time_ago = 'just now';
            } elseif ($diff < 3600) {
                $time_ago = floor($diff / 60) . ' min ago';
            } elseif ($diff < 86400) {
                $time_ago = floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 604800) {
                $time_ago = floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
            } else {
                $time_ago = date('M d, Y g:i A', $timestamp);
            }
        }
        
        $formatted_messages[] = [
            'id' => intval($msg['id']),
            'reservation_id' => intval($msg['reservation_id']),
            'product_name' => $msg['product_name'],
            'sender_id' => intval($msg['sender_id']),
            'sender_name' => $sender_name,
            'is_sender' => $is_sender,
            'message_type' => $message_type,
            'is_system' => $is_system,
            'message_body' => $message_body,
            'created_at' => $msg['created_at'],
            'time_ago' => $time_ago,
            'read_at' => $msg['read_at']
        ];
    }
    
    // Format reservations for dropdown
    $formatted_reservations = [];
    foreach ($reservations as $res) {
        $formatted_reservations[] = [
            'reservation_id' => intval($res['reservation_id']),
            'product_name' => $res['product_name'],
            'other_party_name' => $res['other_party_name'] ?: $res['other_party_username'],
            'status' => $res['status']
        ];
    }
    
    // Mark messages as read (where current user is recipient)
    if (!empty($formatted_messages)) {
        $reservation_ids = array_unique(array_column($formatted_messages, 'reservation_id'));
        foreach ($reservation_ids as $res_id) {
            // Get the other party's ID for this reservation
            $reservation_check = $conn->prepare("SELECT " . ($user_role === 'farmer' ? "user_id" : "farmer_id") . " as other_party_id FROM reservations WHERE id = :reservation_id");
            $reservation_check->bindParam(':reservation_id', $res_id, PDO::PARAM_INT);
            $reservation_check->execute();
            $reservation_data = $reservation_check->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation_data) {
                $other_party_id = $reservation_data['other_party_id'];
                if ($has_message_type) {
                    $mark_read_query = "UPDATE reservation_messages 
                                       SET read_at = NOW() 
                                       WHERE reservation_id = :reservation_id 
                                       AND sender_id = :other_party_id 
                                       AND (message_type IS NULL OR message_type != 'system')
                                       AND read_at IS NULL";
                } else {
                    $mark_read_query = "UPDATE reservation_messages 
                                       SET read_at = NOW() 
                                       WHERE reservation_id = :reservation_id 
                                       AND sender_id = :other_party_id 
                                       AND read_at IS NULL";
                }
                $mark_read_stmt = $conn->prepare($mark_read_query);
                $mark_read_stmt->bindParam(':reservation_id', $res_id, PDO::PARAM_INT);
                $mark_read_stmt->bindParam(':other_party_id', $other_party_id, PDO::PARAM_INT);
                $mark_read_stmt->execute();
            }
        }
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'reservations' => $formatted_reservations
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error fetching all messages: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching messages.',
        'error' => $e->getMessage()
    ]);
    exit;
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
    if (preg_match('/^You reserved (.+)$/i', $message_body, $matches)) {
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
