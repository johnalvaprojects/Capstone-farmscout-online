<?php
$debug_mode = true;
error_reporting(E_ALL);
ini_set('display_errors', $debug_mode ? 1 : 0);
ini_set('log_errors', 1);

if (!ob_get_level()) {
    ob_start();
}

$shutdown_debug = $debug_mode;
register_shutdown_function(function () use ($shutdown_debug) {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            ob_clean();
            http_response_code(500);
            header('Content-Type: application/json');
            $error_message = 'An error occurred. Please try again.';
            if ($shutdown_debug) {
                $error_message .= ' (Fatal: ' . $error['message'] . ' in ' . basename($error['file']) . ':' . $error['line'] . ')';
            }
            echo json_encode([
                'success' => false,
                'message' => $error_message,
                'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'],
            ]);
        }
    }
});

require_once __DIR__ . '/../includes/enhanced_functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/fs_reservation_helpers.php';

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to make a reservation.']);
    exit;
}

$rateLimitKey = 'reservation_ajax_' . $_SESSION['user_id'];
if (!checkRateLimit($rateLimitKey, 5, 900)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait a bit before trying again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

if (!validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$preferred_pickup_date = sanitizeInput($input['preferred_pickup_date'] ?? '');
$preferred_pickup_time = sanitizeInput($input['preferred_pickup_time'] ?? '');
$notes = sanitizeInput($input['notes'] ?? '');

$items = [];
if (!empty($input['items']) && is_array($input['items'])) {
    foreach ($input['items'] as $it) {
        $pid = (int) ($it['product_id'] ?? 0);
        $qty = floatval($it['quantity'] ?? 0);
        $unit = sanitizeInput($it['unit'] ?? '');
        if ($pid > 0 && $qty > 0 && $unit !== '') {
            $items[] = ['product_id' => $pid, 'quantity' => $qty, 'unit' => $unit];
        }
    }
}
if (empty($items)) {
    $pid = (int) ($input['product_id'] ?? 0);
    $qty = floatval($input['quantity'] ?? 0);
    $unit = sanitizeInput($input['unit'] ?? '');
    if ($pid > 0 && $qty > 0 && $unit !== '') {
        $items[] = ['product_id' => $pid, 'quantity' => $qty, 'unit' => $unit];
    }
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Please add at least one product with quantity and unit.']);
    exit;
}

if (count($items) > 10) {
    echo json_encode(['success' => false, 'message' => 'A reservation can include at most 10 products.']);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$hasParent = fs_reservations_parent_column_exists($conn);
$hasRefs = fs_reservations_public_ref_columns_exist($conn);

if (count($items) > 1 && !$hasParent) {
    echo json_encode([
        'success' => false,
        'message' => 'Multi-product reservations require a database update. Please run database/reservation_multi_product_and_public_refs.sql.',
    ]);
    exit;
}

try {
    $hasApprovalStatus = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
        $hasApprovalStatus = $col_check->rowCount() > 0;
    } catch (Exception $e) {
    }
    $approvalCheck = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : '';

    $loaded = [];
    foreach ($items as $it) {
        $product_id = (int) $it['product_id'];
        $product_query = "SELECT mp.*, mp.farmer_id, mp.market_id,
                      mp.product_name, mp.product_image,
                      u.full_name as farmer_name, u.email as farmer_email,
                      m.market_name
                      FROM market_products mp
                      LEFT JOIN users u ON mp.farmer_id = u.id
                      LEFT JOIN markets m ON mp.market_id = m.id
                      WHERE mp.id = :product_id
                      $approvalCheck
                      AND mp.is_available = 1";
        $product_stmt = $conn->prepare($product_query);
        $product_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $product_stmt->execute();
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'One or more products were not found or are not available.']);
            exit;
        }
        if (empty($product['farmer_id']) || empty($product['market_id'])) {
            echo json_encode(['success' => false, 'message' => 'Product information is incomplete. Please contact support.']);
            exit;
        }
        if ($user_id === (int) $product['farmer_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot reserve your own products.']);
            exit;
        }
        $loaded[] = [
            'product' => $product,
            'quantity' => (float) $it['quantity'],
            'unit' => $it['unit'],
        ];
    }

    $first = $loaded[0]['product'];
    $first_market = (int) $first['market_id'];
    $first_farmer = (int) $first['farmer_id'];

    foreach ($loaded as $row) {
        $p = $row['product'];
        if ((int) $p['market_id'] !== $first_market) {
            echo json_encode([
                'success' => false,
                'message' => 'All products in one reservation must be from the same market. Remove items from another market or submit this reservation first.',
            ]);
            exit;
        }
        if ((int) $p['farmer_id'] !== $first_farmer) {
            echo json_encode([
                'success' => false,
                'message' => 'All products in one reservation must be from the same seller. Finish or clear your cart before adding items from another seller.',
            ]);
            exit;
        }
    }

    if (!empty($preferred_pickup_date)) {
        $date_parts = explode('-', $preferred_pickup_date);
        if (count($date_parts) !== 3 || !checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0])) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid pickup date.']);
            exit;
        }
        $pickup_date_obj = new DateTime($preferred_pickup_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        if ($pickup_date_obj < $today) {
            echo json_encode(['success' => false, 'message' => 'Pickup date cannot be in the past.']);
            exit;
        }
        $max_date = new DateTime();
        $max_date->modify('+30 days');
        if ($pickup_date_obj > $max_date) {
            echo json_encode(['success' => false, 'message' => 'Reservations can only be made up to 30 days in advance.']);
            exit;
        }
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'reservations'");
    if ($table_check->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Reservations system is not set up. Please contact the administrator.',
        ]);
        exit;
    }

    $preferred_pickup_date_value = !empty($preferred_pickup_date) ? $preferred_pickup_date : null;
    $preferred_pickup_time_value = !empty($preferred_pickup_time) ? $preferred_pickup_time : null;
    $notes_value = !empty($notes) ? $notes : null;

    [$public_ref, $chat_public_ref] = $hasRefs ? fs_next_reservation_public_refs($conn) : [null, null];

    $conn->beginTransaction();

    $root_id = null;
    $insertLine = function (array $product, float $quantity, string $unit, ?int $parent_id) use (
        $conn,
        $user_id,
        $preferred_pickup_date_value,
        $preferred_pickup_time_value,
        $notes_value,
        $hasParent,
        $hasRefs,
        $public_ref,
        $chat_public_ref
    ) {
        $farmer_id = (int) $product['farmer_id'];
        $market_id = (int) $product['market_id'];
        $product_id = (int) $product['id'];

        $cols = [
            'user_id',
            'farmer_id',
            'product_id',
            'market_id',
            'quantity',
            'unit',
            'preferred_pickup_date',
            'preferred_pickup_time',
            'notes',
            'status',
        ];
        $vals = [
            ':user_id',
            ':farmer_id',
            ':product_id',
            ':market_id',
            ':quantity',
            ':unit',
            ':preferred_pickup_date',
            ':preferred_pickup_time',
            ':notes',
            "'pending'",
        ];

        if ($hasParent && $parent_id !== null) {
            $cols[] = 'parent_reservation_id';
            $vals[] = ':parent_reservation_id';
        }
        if ($hasRefs) {
            $cols[] = 'public_ref';
            $cols[] = 'chat_public_ref';
            if ($parent_id === null) {
                $vals[] = ':public_ref';
                $vals[] = ':chat_public_ref';
            } else {
                $vals[] = 'NULL';
                $vals[] = 'NULL';
            }
        }

        $sql = 'INSERT INTO reservations (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
        $st = $conn->prepare($sql);
        $st->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $st->bindValue(':farmer_id', $farmer_id, PDO::PARAM_INT);
        $st->bindValue(':product_id', $product_id, PDO::PARAM_INT);
        $st->bindValue(':market_id', $market_id, PDO::PARAM_INT);
        $st->bindValue(':quantity', $quantity);
        $st->bindValue(':unit', $unit);
        $st->bindValue(':preferred_pickup_date', $preferred_pickup_date_value);
        $st->bindValue(':preferred_pickup_time', $preferred_pickup_time_value);
        $st->bindValue(':notes', $notes_value);
        if ($hasParent && $parent_id !== null) {
            $st->bindValue(':parent_reservation_id', $parent_id, PDO::PARAM_INT);
        }
        if ($hasRefs && $parent_id === null) {
            $st->bindValue(':public_ref', $public_ref);
            $st->bindValue(':chat_public_ref', $chat_public_ref);
        }
        if (!$st->execute()) {
            throw new Exception('Insert failed');
        }
        return (int) $conn->lastInsertId();
    };

    $firstProduct = $loaded[0]['product'];
    $root_id = $insertLine($firstProduct, $loaded[0]['quantity'], $loaded[0]['unit'], null);

    for ($i = 1; $i < count($loaded); $i++) {
        $row = $loaded[$i];
        $insertLine($row['product'], $row['quantity'], $row['unit'], $root_id);
    }

    $conn->commit();

    $reservation_id = $root_id;

    $user_stmt = $conn->prepare('SELECT full_name, email FROM users WHERE id = :user_id');
    $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    $summaryParts = [];
    foreach ($loaded as $row) {
        $summaryParts[] = $row['quantity'] . ' ' . $row['unit'] . ' of ' . $row['product']['product_name'];
    }
    $summaryText = implode('; ', $summaryParts);
    $first_for_email = $loaded[0]['product'];

    if (function_exists('sendReservationEmail')) {
        try {
            sendReservationEmail([
                'type' => 'user_submitted',
                'user_email' => $user['email'],
                'user_name' => $user['full_name'],
                'product_name' => count($loaded) > 1 ? ('Multiple items: ' . $summaryText) : $first_for_email['product_name'],
                'quantity' => $loaded[0]['quantity'],
                'unit' => $loaded[0]['unit'],
                'market_name' => $first_for_email['market_name'],
                'pickup_date' => $preferred_pickup_date,
                'pickup_time' => $preferred_pickup_time,
            ]);
        } catch (Exception $emailError) {
            error_log('Failed to send reservation email to user: ' . $emailError->getMessage());
        }
        try {
            sendReservationEmail([
                'type' => 'farmer_notification',
                'farmer_email' => $first_for_email['farmer_email'],
                'farmer_name' => $first_for_email['farmer_name'],
                'user_name' => $user['full_name'],
                'user_email' => $user['email'],
                'product_name' => count($loaded) > 1 ? ('Multiple items: ' . $summaryText) : $first_for_email['product_name'],
                'quantity' => $loaded[0]['quantity'],
                'unit' => $loaded[0]['unit'],
                'market_name' => $first_for_email['market_name'],
                'pickup_date' => $preferred_pickup_date,
                'pickup_time' => $preferred_pickup_time,
                'notes' => $notes,
                'reservation_id' => $reservation_id,
            ]);
        } catch (Exception $emailError) {
            error_log('Failed to send reservation email to farmer: ' . $emailError->getMessage());
        }
    }

    if (function_exists('createNotification')) {
        try {
            createNotification(
                $first_farmer,
                'new_reservation',
                'New Reservation Request',
                $user['full_name'] . ' wants to reserve: ' . $summaryText,
                'farmer-dashboard.php?section=reservations'
            );
        } catch (Exception $notifError) {
            error_log('Failed to create notification for farmer: ' . $notifError->getMessage());
        }
    }

    if (function_exists('createSystemMessage')) {
        try {
            createSystemMessage($reservation_id, 'You reserved: ' . $summaryText);
        } catch (Exception $msgError) {
            error_log('Failed to create system message: ' . $msgError->getMessage());
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Reservation submitted successfully! The farmer will review your request.',
        'reservation_id' => $reservation_id,
        'public_ref' => $public_ref,
        'chat_public_ref' => $chat_public_ref,
    ]);
    exit;
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Reservation creation PDO error: ' . $e->getMessage());
    ob_clean();
    $error_message = 'An error occurred. Please try again.';
    if ($debug_mode) {
        $error_message .= ' (PDO: ' . $e->getMessage() . ')';
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Reservation creation error: ' . $e->getMessage());
    ob_clean();
    $error_message = 'An error occurred. Please try again.';
    if ($debug_mode) {
        $error_message .= ' (Error: ' . $e->getMessage() . ')';
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}
