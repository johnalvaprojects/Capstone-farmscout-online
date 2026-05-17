<?php
/**
 * Proceed to payment for a reservation (simple, simulated).
 *
 * - Cash on Pickup: payment_method=cash, status=confirmed
 * - GCash simulated: payment_method=gcash, save reference, status=paid
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/fs_reservation_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to continue.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$reservation_id = (int)($input['reservation_id'] ?? 0);
$root_reservation_id = $reservation_id;
$method = strtolower(trim((string)($input['payment_method'] ?? '')));
$reference = trim((string)($input['gcash_reference'] ?? ''));

if ($reservation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation.']);
    exit;
}
if (!in_array($method, ['cash', 'gcash'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method.']);
    exit;
}
if ($method === 'gcash' && $reference === '') {
    echo json_encode(['success' => false, 'message' => 'Reference number is required for GCash.']);
    exit;
}
if (strlen($reference) > 120) {
    echo json_encode(['success' => false, 'message' => 'Reference number is too long.']);
    exit;
}

try {
    $conn = getDB();
    if (!$conn) throw new Exception('Database connection failed');

    if (function_exists('fs_reservation_root_id')) {
        $root_reservation_id = fs_reservation_root_id($conn, $reservation_id);
    }

    $hasCol = function(string $col) use ($conn): bool {
        try {
            $c = $conn->prepare("SHOW COLUMNS FROM reservations LIKE :col");
            $c->execute([':col' => $col]);
            return $c->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    };

    $hasPaymentMethod = $hasCol('payment_method');
    $hasGcashRef = $hasCol('gcash_reference');
    $hasConfirmedAt = $hasCol('confirmed_at');
    $hasPaidAt = $hasCol('paid_at');
    $hasUpdatedAt = $hasCol('updated_at');

    // Ensure reservation exists and belongs to user (root row holds payment for the group)
    $stmt = $conn->prepare("SELECT id, status FROM reservations WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute([':id' => $root_reservation_id, ':user_id' => $user_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
        exit;
    }

    $status = strtolower((string)($res['status'] ?? 'pending'));
    // Best-practice flow:
    // - Reservation remains PENDING until farmer confirms.
    // - Payment selection/reference can be saved while pending/confirmed,
    //   but must NOT auto-confirm or auto-mark-paid.
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        echo json_encode(['success' => false, 'message' => 'This reservation can no longer be paid.']);
        exit;
    }

    if ($method === 'cash') {
        $sets = [];
        if ($hasPaymentMethod) $sets[] = "payment_method = 'cash'";
        if ($hasGcashRef) $sets[] = "gcash_reference = NULL";
        if ($hasUpdatedAt) $sets[] = "updated_at = NOW()";
        $whereRoot = fs_reservations_parent_column_exists($conn)
            ? '(id = :id OR parent_reservation_id = :id2) AND user_id = :user_id'
            : 'id = :id AND user_id = :user_id';
        $upd = $conn->prepare("UPDATE reservations SET " . implode(",\n                ", $sets) . " WHERE " . $whereRoot);
        $params = [':id' => $root_reservation_id, ':user_id' => $user_id];
        if (fs_reservations_parent_column_exists($conn)) {
            $params[':id2'] = $root_reservation_id;
        }
        $upd->execute($params);
        echo json_encode([
            'success' => true,
            'status' => $status,
            'message' => 'Payment method saved. Waiting for farmer confirmation.',
        ]);
        exit;
    }

    // gcash
    $sets = [];
    if ($hasPaymentMethod) $sets[] = "payment_method = 'gcash'";
    if ($hasGcashRef) $sets[] = "gcash_reference = :ref";
    if ($hasUpdatedAt) $sets[] = "updated_at = NOW()";
    $whereRoot = fs_reservations_parent_column_exists($conn)
        ? '(id = :id OR parent_reservation_id = :id2) AND user_id = :user_id'
        : 'id = :id AND user_id = :user_id';
    $upd = $conn->prepare("UPDATE reservations SET " . implode(",\n            ", $sets) . " WHERE " . $whereRoot);
    $params = [':id' => $root_reservation_id, ':user_id' => $user_id];
    if (fs_reservations_parent_column_exists($conn)) {
        $params[':id2'] = $root_reservation_id;
    }
    if ($hasGcashRef) $params[':ref'] = $reference;
    $upd->execute($params);
    echo json_encode([
        'success' => true,
        'status' => $status,
        'message' => 'GCash reference saved. Waiting for farmer confirmation.',
    ]);
} catch (Exception $e) {
    error_log('proceed_to_payment: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not proceed to payment.']);
}

