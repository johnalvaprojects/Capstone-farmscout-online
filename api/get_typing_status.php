<?php
require_once '../includes/enhanced_functions.php';
require_once '../includes/security.php';
require_once '../includes/fs_reservation_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$reservation_id = isset($_GET['reservation_id']) ? intval($_GET['reservation_id']) : 0;
$thread_id = $reservation_id;

if ($reservation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    if (function_exists('fs_reservation_root_id')) {
        $thread_id = fs_reservation_root_id($conn, $reservation_id);
    }
    
    // Check if typing_status table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'typing_status'");
    if ($table_check->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'typing_users' => [],
            'message' => 'Typing status table does not exist. Please run migration.'
        ]);
        exit;
    }
    
    // Verify reservation exists and user has access
    $reservation_check = $conn->prepare("
        SELECT id FROM reservations 
        WHERE id = :reservation_id 
        AND (user_id = :user_id OR farmer_id = :user_id)
    ");
    $reservation_check->execute([
        ':reservation_id' => $reservation_id,
        ':user_id' => $user_id,
    ]);
    
    if ($reservation_check->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found or access denied']);
        exit;
    }
    
    // Get typing statuses for this reservation (excluding current user, only active in last 10 seconds)
    // Increased window to catch typing status even if there's a slight delay
    $query = "
        SELECT ts.user_id, ts.is_typing, u.username, u.full_name, ts.last_activity
        FROM typing_status ts
        JOIN users u ON ts.user_id = u.id
        WHERE ts.reservation_id = :reservation_id
        AND ts.user_id != :user_id
        AND ts.is_typing = 1
        AND ts.last_activity >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ORDER BY ts.last_activity DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':reservation_id' => $thread_id,
        ':user_id' => $user_id
    ]);
    
    $typing_users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $typing_users[] = [
            'user_id' => intval($row['user_id']),
            'user_name' => $row['full_name'] ?: $row['username'],
            'is_typing' => (bool)$row['is_typing'],
            'last_activity' => $row['last_activity']
        ];
    }
    
    // Debug logging - check all typing statuses for this reservation
    $debug_query = "SELECT ts.*, u.username, u.full_name, TIMESTAMPDIFF(SECOND, ts.last_activity, NOW()) as seconds_ago FROM typing_status ts LEFT JOIN users u ON ts.user_id = u.id WHERE ts.reservation_id = :reservation_id";
    $debug_stmt = $conn->prepare($debug_query);
    $debug_stmt->execute([':reservation_id' => $thread_id]);
    $all_statuses_raw = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to a simpler format for JSON encoding
    $all_statuses = [];
    foreach ($all_statuses_raw as $status) {
        $all_statuses[] = [
            'id' => intval($status['id']),
            'reservation_id' => intval($status['reservation_id']),
            'user_id' => intval($status['user_id']),
            'username' => $status['username'] ?? null,
            'full_name' => $status['full_name'] ?? null,
            'is_typing' => (bool)$status['is_typing'],
            'last_activity' => $status['last_activity'],
            'seconds_ago' => intval($status['seconds_ago'] ?? 0)
        ];
    }
    
    error_log("All typing statuses for reservation $thread_id: " . json_encode($all_statuses));
    
    // Also log what the query is looking for
    error_log("Query conditions: reservation_id=$thread_id, viewer_user_id=$user_id, is_typing=1, last_activity >= 10 seconds ago");
    error_log("Typing status query for reservation $thread_id (viewer user $user_id): Found " . count($typing_users) . " typing users");
    
    // Log each typing user found
    foreach ($typing_users as $tu) {
        error_log("Found typing user: " . json_encode($tu));
    }
    
    // Auto-cleanup: Remove typing statuses older than 15 seconds (increased to give more buffer)
    // Only clean up records that are both old AND not currently typing
    $cleanup_query = "DELETE FROM typing_status 
                      WHERE last_activity < DATE_SUB(NOW(), INTERVAL 15 SECOND)
                      AND is_typing = 0";
    $cleanup_count = $conn->exec($cleanup_query);
    if ($cleanup_count > 0) {
        error_log("Cleaned up $cleanup_count old inactive typing statuses");
    }
    
    echo json_encode([
        'success' => true,
        'typing_users' => $typing_users,
        'debug' => [
            'reservation_id' => $thread_id,
            'viewer_user_id' => $user_id,
            'all_statuses_count' => count($all_statuses),
            'typing_users_count' => count($typing_users),
            'all_statuses' => $all_statuses // Include all statuses for debugging
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error getting typing status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error getting typing status: ' . $e->getMessage(),
        'typing_users' => []
    ]);
}
