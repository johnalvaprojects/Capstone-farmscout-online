<?php
/**
 * Auto-expire pending reservations older than 24 hours.
 * Intended for cron use. Safe to run repeatedly.
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'reservations'");
    if (!$table_check || $table_check->rowCount() === 0) {
        echo json_encode(['success' => true, 'expired' => 0]);
        exit;
    }

    $hasUpdatedAt = false;
    try {
        $c = $conn->query("SHOW COLUMNS FROM reservations LIKE 'updated_at'");
        $hasUpdatedAt = $c && $c->rowCount() > 0;
    } catch (Exception $e) {
        $hasUpdatedAt = false;
    }

    $sql = "
        UPDATE reservations
        SET status = 'cancelled'" . ($hasUpdatedAt ? ", updated_at = NOW()" : "") . "
        WHERE status = 'pending'
          AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $expired = (int)$stmt->rowCount();

    if ($expired > 0) {
        error_log('expire_pending_reservations: cancelled ' . $expired . ' pending reservations (24h+)');
    }

    echo json_encode(['success' => true, 'expired' => $expired]);
} catch (Exception $e) {
    error_log('expire_pending_reservations: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'expired' => 0]);
}

