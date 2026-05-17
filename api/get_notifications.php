<?php
/**
 * API endpoint to fetch notifications for the logged-in user
 */

// Start output buffering FIRST to catch any notices/warnings
ob_start();
ini_set('display_errors', 0);
error_reporting(0); // Suppress all errors/warnings/notices

require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching notifications.'
        ]);
        exit;
    }
});

// Check if user is logged in
if (!isLoggedIn()) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view notifications.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$limit = min($limit, 50); // Max 50 notifications

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Ensure notifications table exists (same helper as createNotification)
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() === 0 && function_exists('fs_ensure_notifications_table')) {
        fs_ensure_notifications_table($conn);
        $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    }
    if ($table_check->rowCount() === 0) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'notifications' => [],
            'unread_count' => 0
        ]);
        exit;
    }
    
    // Fetch notifications for user
    $query = "SELECT id, type, title, message, link, is_read, created_at, read_at
              FROM notifications
              WHERE user_id = :user_id
              ORDER BY created_at DESC
              LIMIT :limit";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $count_query = "SELECT COUNT(*) as unread_count
                    FROM notifications
                    WHERE user_id = :user_id AND is_read = 0";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $count_stmt->execute();
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = intval($count_result['unread_count'] ?? 0);
    
    // Get user role to fix incorrect notification links
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Format notifications
    foreach ($notifications as &$notification) {
        $notification['id'] = intval($notification['id']);
        $notification['is_read'] = (bool)$notification['is_read'];
        $notification['created_at'] = $notification['created_at'];
        
        // Fix incorrect links: if user is not a farmer but link points to farmer-dashboard, change it
        if (!empty($notification['link'])) {
            if ($user_role !== 'farmer' && strpos($notification['link'], 'farmer-dashboard.php') !== false) {
                // User is not a farmer but link points to farmer dashboard - fix it
                $notification['link'] = str_replace('farmer-dashboard.php?section=reservations', 'user-account.php?section=reservations', $notification['link']);
                $notification['link'] = str_replace('farmer-dashboard.php', 'user-account.php', $notification['link']);
            } elseif ($user_role === 'farmer' && strpos($notification['link'], 'user-account.php?section=reservations') !== false) {
                // User is a farmer but link points to user-account - fix it
                $notification['link'] = str_replace('user-account.php?section=reservations', 'farmer-dashboard.php?section=reservations', $notification['link']);
            }
        }
        
        // Calculate relative time (use function from enhanced_functions.php)
        $timestamp = strtotime($notification['created_at']);
        if (function_exists('getTimeAgo')) {
            $notification['time_ago'] = getTimeAgo($timestamp);
        } else {
            // Fallback if function doesn't exist
            $diff = time() - $timestamp;
            if ($diff < 60) {
                $notification['time_ago'] = 'just now';
            } elseif ($diff < 3600) {
                $minutes = floor($diff / 60);
                $notification['time_ago'] = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                $notification['time_ago'] = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
            } else {
                $notification['time_ago'] = date('M d, Y', $timestamp);
            }
        }
    }
    unset($notification); // Break reference
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
    exit;
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    ob_clean();
    // Return empty notifications instead of error to prevent UI issues
    echo json_encode([
        'success' => true,
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

