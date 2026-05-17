<?php
/**
 * Cleanup script to automatically delete old read notifications
 * This should be run periodically (e.g., via cron job or on page load)
 * 
 * Deletes:
 * - Read notifications older than 30 days
 * - All notifications older than 90 days (regardless of read status)
 */

require_once '../includes/enhanced_functions.php';

// Start output buffering
ob_start();
ini_set('display_errors', 0);
error_reporting(0);

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->rowCount() === 0) {
        // Table doesn't exist, nothing to clean
        exit;
    }
    
    // Delete read notifications older than 30 days
    $delete_read_query = "DELETE FROM notifications 
                         WHERE is_read = 1 
                         AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($delete_read_query);
    $stmt->execute();
    $deleted_read = $stmt->rowCount();
    
    // Delete all notifications (read or unread) older than 90 days
    $delete_old_query = "DELETE FROM notifications 
                        WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    $stmt = $conn->prepare($delete_old_query);
    $stmt->execute();
    $deleted_old = $stmt->rowCount();
    
    // Log cleanup (optional)
    if ($deleted_read > 0 || $deleted_old > 0) {
        error_log("Notification cleanup: Deleted $deleted_read read notifications (30+ days old) and $deleted_old old notifications (90+ days old)");
    }
    
} catch (Exception $e) {
    error_log("Error cleaning up notifications: " . $e->getMessage());
}

