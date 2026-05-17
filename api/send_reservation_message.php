<?php
/**
 * API endpoint to send a message in a reservation thread
 */

// Start output buffering FIRST
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';
require_once '../includes/fs_reservation_helpers.php';

header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while sending the message.'
        ]);
        exit;
    }
});

// Check if user is logged in
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to send messages.'
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate CSRF token
if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$reservation_id = isset($input['reservation_id']) ? intval($input['reservation_id']) : 0;
$thread_id = $reservation_id;
$message_body = isset($input['message']) ? trim($input['message']) : '';

// Validate input
if ($reservation_id <= 0) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reservation ID.'
    ]);
    exit;
}

if (empty($message_body)) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Message cannot be empty.'
    ]);
    exit;
}

// Limit message length (1000 characters)
if (strlen($message_body) > 1000) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Message is too long. Maximum 1000 characters.'
    ]);
    exit;
}

// Sanitize message (allow basic formatting, strip dangerous HTML)
$message_body = sanitizeInput($message_body);
$message_body = htmlspecialchars($message_body, ENT_QUOTES, 'UTF-8');

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
            'success' => false,
            'message' => 'Chat system not available. Please run migration.'
        ]);
        exit;
    }

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Verify user has access to this reservation (must be either user_id or farmer_id)
    $check_query = "SELECT user_id, farmer_id FROM reservations WHERE id = :reservation_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
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
    
    // Check access: user must be either the consumer or the farmer
    if ((int)$reservation['user_id'] != (int)$user_id && (int)$reservation['farmer_id'] != (int)$user_id) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'You do not have access to this conversation.'
        ]);
        exit;
    }
    
    // Rate limiting: Check if user sent too many messages recently (10 messages per 5 minutes)
    $rate_limit_query = "SELECT COUNT(*) as message_count 
                        FROM reservation_messages 
                        WHERE sender_id = :user_id 
                        AND reservation_id = :reservation_id
                        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $rate_limit_stmt = $conn->prepare($rate_limit_query);
    $rate_limit_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $rate_limit_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
    $rate_limit_stmt->execute();
    $rate_result = $rate_limit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rate_result['message_count'] >= 10) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Too many messages sent. Please wait a few minutes before sending again.'
        ]);
        exit;
    }
    
    // Determine message type based on user role
    $user_role = $_SESSION['user_role'] ?? 'user';
    $message_type = ($user_role === 'farmer') ? 'farmer' : 'user';
    
    // Check if message_type column exists
    $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
    $has_message_type = $column_check->rowCount() > 0;
    
    // Insert message
    if ($has_message_type) {
        $insert_query = "INSERT INTO reservation_messages (reservation_id, sender_id, message_type, message_body, created_at) 
                         VALUES (:reservation_id, :sender_id, :message_type, :message_body, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':message_type', $message_type);
        $insert_stmt->bindParam(':message_body', $message_body);
    } else {
        $insert_query = "INSERT INTO reservation_messages (reservation_id, sender_id, message_body, created_at) 
                         VALUES (:reservation_id, :sender_id, :message_body, NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':reservation_id', $thread_id, PDO::PARAM_INT);
        $insert_stmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
    }
    $insert_stmt->bindParam(':message_body', $message_body);
    $insert_stmt->execute();
    
    $message_id = $conn->lastInsertId();
    
    // Get recipient ID (the other person in the conversation)
    $recipient_id = ($reservation['user_id'] == $user_id) ? $reservation['farmer_id'] : $reservation['user_id'];
    
    // Get recipient and product details for notifications
    $recipient_query = "SELECT full_name, username, email, user_role FROM users WHERE id = :recipient_id";
    $recipient_stmt = $conn->prepare($recipient_query);
    $recipient_stmt->bindParam(':recipient_id', $recipient_id, PDO::PARAM_INT);
    $recipient_stmt->execute();
    $recipient = $recipient_stmt->fetch(PDO::FETCH_ASSOC);
    
    $product_query = "SELECT product_name FROM market_products WHERE id = :product_id";
    $product_stmt = $conn->prepare($product_query);
    $product_stmt->bindParam(':product_id', $reservation['product_id'], PDO::PARAM_INT);
    $product_stmt->execute();
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create in-app notification for recipient
    if (function_exists('createNotification') && $recipient) {
        try {
            $sender_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Someone';
            $notification_title = 'New Message';
            $notification_message = $sender_name . ' sent you a message about your reservation.';
            // Use recipient's role to determine the correct link, not sender's role
            $notification_link = ($recipient['user_role'] ?? '') === 'farmer' 
                ? 'farmer-dashboard.php?section=reservations' 
                : 'user-account.php?section=reservations';
            
            createNotification(
                $recipient_id,
                'new_message',
                $notification_title,
                $notification_message,
                $notification_link
            );
        } catch (Exception $notifError) {
            error_log("Failed to create notification: " . $notifError->getMessage());
        }
    }
    
    // Send email notification to recipient
    if (function_exists('sendChatMessageEmail') && $recipient && $product) {
        try {
            $sender_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
            $recipient_name = $recipient['full_name'] ?? $recipient['username'] ?? 'User';
            
            sendChatMessageEmail([
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient_name,
                'recipient_role' => $recipient['user_role'] ?? 'user',
                'sender_name' => $sender_name,
                'product_name' => $product['product_name'],
                'message_body' => $message_body
            ]);
        } catch (Exception $emailError) {
            error_log("Failed to send email notification: " . $emailError->getMessage());
            // Don't fail the message send if email fails
        }
    }
    
    // Get sender name for response
    $sender_query = "SELECT full_name, username FROM users WHERE id = :user_id";
    $sender_stmt = $conn->prepare($sender_query);
    $sender_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $sender_stmt->execute();
    $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
    $sender_name = $sender['full_name'] ?? $sender['username'] ?? 'User';
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully.',
        'message_id' => $message_id,
        'sender_name' => $sender_name,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error sending message: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while sending the message.'
    ]);
    exit;
}

