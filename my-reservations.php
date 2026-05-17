<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/fs_reservation_helpers.php';
require_once 'includes/security.php';

$page_title = 'My Reservations - FarmScout Online';
$page_description = 'View and manage your product reservations';

// Track page view
trackPageView('my_reservations');

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('my-reservations.php'));
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Check for success message from redirect
if (isset($_GET['cancelled']) && $_GET['cancelled'] == '1') {
    $message = 'Reservation cancelled successfully.';
    $message_type = 'success';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rateLimitKey = 'reservation_' . ($action ?: 'general');
    $rateLimit = $action === 'cancel_reservation' ? 10 : 5;
    $rateWindow = 900; // 15 minutes
    
    if (!checkRateLimit($rateLimitKey, $rateLimit, $rateWindow)) {
        $message = 'Too many requests. Please wait a bit before trying again.';
        $message_type = 'error';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        switch ($action) {
            case 'cancel_reservation':
                $reservation_id = intval($_POST['reservation_id'] ?? 0);
                if ($reservation_id > 0) {
                    $conn = getDB();
                    if ($conn) {
                        try {
                            $root_id = function_exists('fs_reservation_root_id')
                                ? fs_reservation_root_id($conn, $reservation_id)
                                : $reservation_id;

                            $check_stmt = $conn->prepare("SELECT status FROM reservations WHERE id = :id AND user_id = :user_id LIMIT 1");
                            $check_stmt->bindParam(':id', $root_id, PDO::PARAM_INT);
                            $check_stmt->bindParam(':user_id', $user_id);
                            $check_stmt->execute();
                            $reservation = $check_stmt->fetch(PDO::FETCH_ASSOC);

                            if ($reservation && in_array($reservation['status'], ['pending', 'confirmed'], true)) {
                                if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
                                    $update_stmt = $conn->prepare(
                                        "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE (id = :id OR parent_reservation_id = :id2) AND user_id = :user_id"
                                    );
                                    $cancel_ok = $update_stmt->execute([':id' => $root_id, ':id2' => $root_id, ':user_id' => $user_id]);
                                } else {
                                    $update_stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = :id AND user_id = :user_id");
                                    $cancel_ok = $update_stmt->execute([':id' => $root_id, ':user_id' => $user_id]);
                                }

                                if ($cancel_ok) {
                                    if (function_exists('createSystemMessage')) {
                                        createSystemMessage($root_id, 'You cancelled this reservation');
                                    }
                                    header('Location: my-reservations.php?cancelled=1');
                                    exit;
                                }
                                $message = 'Failed to cancel reservation.';
                                $message_type = 'error';
                            } else {
                                $message = 'Reservation not found or cannot be cancelled.';
                                $message_type = 'error';
                            }
                        } catch (PDOException $e) {
                            error_log("Error cancelling reservation: " . $e->getMessage());
                            $message = 'An error occurred. Please try again.';
                            $message_type = 'error';
                        }
                    }
                }
                break;
        }
    }
}

// Get user's reservations
$conn = getDB();
$reservations = [];

if ($conn) {
    try {
        $status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
        $status_condition = '';
        if ($status_filter && in_array($status_filter, ['pending', 'confirmed', 'paid', 'completed', 'cancelled'])) {
            $status_condition = "AND r.status = :status";
        }

        $root_only = '';
        if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
            $root_only = ' AND r.parent_reservation_id IS NULL ';
        }

        $query = "SELECT r.*, 
                  mp.product_name, mp.product_image,
                  u.full_name as farmer_name, u.email as farmer_email, u.phone_number as farmer_phone,
                  m.market_name, m.address as market_address
                  FROM reservations r
                  LEFT JOIN market_products mp ON r.product_id = mp.id
                  LEFT JOIN users u ON r.farmer_id = u.id
                  LEFT JOIN markets m ON r.market_id = m.id
                  WHERE r.user_id = :user_id
                  $root_only
                  $status_condition
                  ORDER BY r.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        if ($status_filter) {
            $stmt->bindParam(':status', $status_filter);
        }
        $stmt->execute();
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn) && $reservations) {
            $lineStmt = $conn->prepare(
                "SELECT r.id, r.quantity, r.unit, mp.product_name, mp.product_image
                 FROM reservations r
                 INNER JOIN market_products mp ON r.product_id = mp.id
                 WHERE r.id = :root_id OR r.parent_reservation_id = :root_id2
                 ORDER BY r.id ASC"
            );
            foreach ($reservations as $idx => $resRow) {
                $rid = (int)($resRow['id'] ?? 0);
                $lineStmt->execute([':root_id' => $rid, ':root_id2' => $rid]);
                $reservations[$idx]['line_items'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching reservations: " . $e->getMessage());
        $reservations = [];
    }
}

// Set current page for navigation
$current_page = 'my-reservations.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>">
</head>
<body class="reservations-page">
<?php
// Use Market Finder style header
include 'includes/header-market-finder.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
    
    /* Global Reset - Same as Market Finder */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    /* CSS Variables - Same as Market Finder */
    :root {
        --bg-color: #ffffff;
        --text-color: #000000;
        --border-color: #000000;
        --box-bg: #ffffff;
    }
    
    /* Reservations Page - Market Finder Style */
    body.reservations-page {
        font-family: 'VT323', monospace !important;
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        padding: 2rem !important;
        line-height: 1.4 !important;
        margin: 0 !important;
        min-height: 100vh;
    }
    
    /* Header styles match Market Finder */
    body.reservations-page .header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin-bottom: 3rem !important;
        font-size: 1.25rem !important;
        letter-spacing: 0.05em !important;
    }
    
    /* Main Content */
    .reservations-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-title {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    
    .page-description {
        font-size: 1.2rem;
        margin-bottom: 2rem;
        color: #666;
    }
    
    /* Status Filter */
    .status-filter {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }
    
    .status-filter-btn {
        padding: 0.5rem 1.5rem;
        border: 2px solid var(--border-color);
        background-color: var(--bg-color);
        color: var(--text-color);
        text-decoration: none;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .status-filter-btn:hover,
    .status-filter-btn.active {
        background-color: var(--text-color);
        color: var(--bg-color);
    }
    
    /* Message */
    .message {
        padding: 1rem;
        margin-bottom: 2rem;
        border: 2px solid var(--border-color);
        font-size: 1.1rem;
    }
    
    .message.success {
        background-color: #d4edda;
        border-color: #28a745;
    }
    
    .message.error {
        background-color: #f8d7da;
        border-color: #dc3545;
    }
    
    /* Reservations List */
    .reservations-list {
        display: grid;
        gap: 1.5rem;
    }
    
    .reservation-card {
        border: 3px solid var(--border-color);
        padding: 1.5rem;
        background-color: var(--box-bg);
    }
    
    .reservation-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .reservation-product {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex: 1;
    }
    
    .reservation-product img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border: 2px solid var(--border-color);
    }
    
    .reservation-product-info h3 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .reservation-status {
        padding: 0.5rem 1rem;
        border: 2px solid var(--border-color);
        font-size: 1rem;
        text-transform: uppercase;
        font-weight: bold;
    }
    
    .reservation-status.pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .reservation-status.confirmed {
        background-color: #d4edda;
        color: #155724;
    }
    
    .reservation-status.paid {
        background-color: #d1ecf1;
        color: #0c5460;
    }

    /* (Old status) declined/accepted removed from new flow */
    
    .reservation-status.completed {
        background-color: #e2e3e5;
        color: #383d41;
    }
    
    .reservation-status.cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .reservation-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .reservation-detail-item {
        font-size: 1.1rem;
    }
    
    .reservation-detail-item strong {
        display: block;
        margin-bottom: 0.25rem;
        font-size: 0.9rem;
        color: #666;
    }
    
    .reservation-actions {
        margin-top: 1rem;
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .reservation-btn {
        padding: 0.5rem 1rem;
        border: 2px solid var(--border-color);
        background-color: var(--bg-color);
        color: var(--text-color);
        text-decoration: none;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .reservation-btn:hover {
        background-color: var(--text-color);
        color: var(--bg-color);
    }
    
    .reservation-btn.danger {
        border-color: #dc3545;
        color: #dc3545;
    }
    
    .reservation-btn.danger:hover {
        background-color: #dc3545;
        color: var(--bg-color);
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        border: 3px solid var(--border-color);
        font-size: 1.5rem;
    }
    
    @media (max-width: 768px) {
        body.reservations-page {
            padding: 1rem !important;
        }
        
        .reservations-container {
            max-width: 100%;
        }
        
        .page-title {
            font-size: 1.75rem;
        }
        
        .page-description {
            font-size: 1rem;
        }
        
        .status-filter {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .status-filter-btn {
            width: 100%;
            text-align: center;
            min-height: 44px; /* Touch-friendly */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .reservation-header {
            flex-direction: column;
            gap: 1rem;
        }
        
        .reservation-product {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .reservation-product img {
            width: 100%;
            max-width: 200px;
            height: auto;
        }
        
        .reservation-details {
            grid-template-columns: 1fr;
        }
        
        .reservation-actions {
            flex-direction: column;
        }
        
        .reservation-btn {
            width: 100%;
            text-align: center;
            min-height: 44px; /* Touch-friendly */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .confirm-modal {
            width: 95%;
            padding: 1.5rem;
            margin: 1rem;
        }
        
        .confirm-modal h2 {
            font-size: 1.5rem;
        }
        
        .confirm-modal p {
            font-size: 1rem;
        }
        
        .confirm-modal-buttons {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .confirm-modal-btn {
            width: 100%;
            min-height: 44px; /* Touch-friendly */
        }
    }
    
    /* Custom Confirmation Modal */
    .confirm-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        justify-content: center;
        align-items: center;
    }
    
    .confirm-modal-overlay.active {
        display: flex;
    }
    
    .confirm-modal {
        background-color: #ffffff;
        border: 4px solid #000000;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        text-align: center;
        font-family: 'VT323', monospace;
    }
    
    .confirm-modal h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
        text-transform: uppercase;
        color: #000000;
    }
    
    .confirm-modal p {
        font-size: 1.3rem;
        margin-bottom: 2rem;
        color: #000000;
    }
    
    .confirm-modal-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
    }
    
    .confirm-modal-btn {
        padding: 0.75rem 2rem;
        border: 3px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 1.2rem;
        cursor: pointer;
        text-transform: uppercase;
        transition: all 0.3s ease;
    }
    
    .confirm-modal-btn.cancel {
        background-color: #ffffff;
        color: #000000;
    }
    
    .confirm-modal-btn.cancel:hover {
        background-color: #f0f0f0;
    }
    
    .confirm-modal-btn.confirm {
        background-color: #000000;
        color: #ffffff;
    }
    
    .confirm-modal-btn.confirm:hover {
        background-color: #333333;
    }
</style>

<div class="reservations-container">
    <h1 class="page-title">MY RESERVATIONS</h1>
    <p class="page-description">View and manage your product reservations</p>
    
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Status Filter -->
    <div class="status-filter">
        <a href="my-reservations.php" class="status-filter-btn <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">ALL</a>
        <a href="my-reservations.php?status=pending" class="status-filter-btn <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : ''; ?>">PENDING</a>
        <a href="my-reservations.php?status=confirmed" class="status-filter-btn <?php echo (isset($_GET['status']) && $_GET['status'] === 'confirmed') ? 'active' : ''; ?>">CONFIRMED</a>
        <a href="my-reservations.php?status=paid" class="status-filter-btn <?php echo (isset($_GET['status']) && $_GET['status'] === 'paid') ? 'active' : ''; ?>">PAID</a>
        <a href="my-reservations.php?status=completed" class="status-filter-btn <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'active' : ''; ?>">COMPLETED</a>
        <a href="my-reservations.php?status=cancelled" class="status-filter-btn <?php echo (isset($_GET['status']) && $_GET['status'] === 'cancelled') ? 'active' : ''; ?>">CANCELLED</a>
    </div>
    
    <!-- Reservations List -->
    <div class="reservations-list">
        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                No reservations found.
            </div>
        <?php else: ?>
            <?php foreach ($reservations as $reservation): ?>
                <?php
                    $lines = !empty($reservation['line_items']) ? $reservation['line_items'] : [[
                        'product_name' => $reservation['product_name'] ?? 'Product',
                        'product_image' => $reservation['product_image'] ?? '',
                        'quantity' => $reservation['quantity'] ?? '',
                        'unit' => $reservation['unit'] ?? '',
                    ]];
                    $headImg = $lines[0]['product_image'] ?? ($reservation['product_image'] ?? '');
                    $headTitle = count($lines) > 1
                        ? (count($lines) . ' products')
                        : (string)($lines[0]['product_name'] ?? 'Product');
                ?>
                <div class="reservation-card">
                    <div class="reservation-header">
                        <div class="reservation-product">
                            <img src="<?php echo htmlspecialchars(assetUrl($headImg ?: 'assets/images/placeholder-product.svg')); ?>"
                                 alt="<?php echo htmlspecialchars($headTitle); ?>">
                            <div class="reservation-product-info">
                                <h3><?php echo htmlspecialchars($headTitle); ?></h3>
                                <p><?php echo htmlspecialchars($reservation['market_name']); ?></p>
                                <?php if (!empty($reservation['public_ref']) || !empty($reservation['chat_public_ref'])): ?>
                                    <p style="margin-top:0.5rem;font-size:0.95rem;opacity:0.85;">
                                        <?php if (!empty($reservation['public_ref'])): ?>
                                            <strong>Reservation ID:</strong> <?php echo htmlspecialchars((string)$reservation['public_ref']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($reservation['public_ref']) && !empty($reservation['chat_public_ref'])): ?> · <?php endif; ?>
                                        <?php if (!empty($reservation['chat_public_ref'])): ?>
                                            <strong>Chat ID:</strong> <?php echo htmlspecialchars((string)$reservation['chat_public_ref']); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (count($lines) > 1): ?>
                                    <ul style="margin:0.75rem 0 0;padding-left:1.1rem;font-size:1rem;line-height:1.45;">
                                        <?php foreach ($lines as $ln): ?>
                                            <li><?php echo htmlspecialchars(trim(($ln['quantity'] ?? '') . ' ' . ($ln['unit'] ?? '') . ' — ' . ($ln['product_name'] ?? ''))); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="reservation-status <?php echo htmlspecialchars($reservation['status']); ?>">
                            <?php echo strtoupper($reservation['status']); ?>
                        </div>
                    </div>
                    
                    <div class="reservation-details">
                        <div class="reservation-detail-item">
                            <strong>Quantity</strong>
                            <?php echo count($lines) > 1
                                ? htmlspecialchars(count($lines) . ' product lines')
                                : htmlspecialchars($reservation['quantity'] . ' ' . $reservation['unit']); ?>
                        </div>
                        <div class="reservation-detail-item">
                            <strong>Farmer</strong>
                            <?php echo htmlspecialchars($reservation['farmer_name']); ?>
                        </div>
                        <?php if ($reservation['preferred_pickup_date']): ?>
                            <div class="reservation-detail-item">
                                <strong>Pickup Date</strong>
                                <?php echo date('M d, Y', strtotime($reservation['preferred_pickup_date'])); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($reservation['preferred_pickup_time']): ?>
                            <div class="reservation-detail-item">
                                <strong>Pickup Time</strong>
                                <?php echo date('g:i A', strtotime($reservation['preferred_pickup_time'])); ?>
                            </div>
                        <?php endif; ?>
                        <div class="reservation-detail-item">
                            <strong>Reserved On</strong>
                            <?php echo date('M d, Y g:i A', strtotime($reservation['created_at'])); ?>
                        </div>
                        <?php if (!empty($reservation['payment_method'])): ?>
                            <div class="reservation-detail-item">
                                <strong>Payment</strong>
                                <?php echo strtoupper(htmlspecialchars($reservation['payment_method'])); ?>
                                <?php if (!empty($reservation['gcash_reference'])): ?>
                                    <div style="opacity:0.85; margin-top:0.25rem;">
                                        Ref: <?php echo htmlspecialchars($reservation['gcash_reference']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($reservation['notes']): ?>
                        <div class="reservation-detail-item" style="margin-top: 1rem;">
                            <strong>Your Notes</strong>
                            <?php echo htmlspecialchars($reservation['notes']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($reservation['status'], ['pending', 'confirmed'])): ?>
                        <div class="reservation-actions">
                            <button type="button" class="reservation-btn danger" onclick="openCancelModal(<?php echo $reservation['id']; ?>)">CANCEL RESERVATION</button>
                            <form method="POST" id="cancel-form-<?php echo $reservation['id']; ?>" style="display: none;">
                                <input type="hidden" name="action" value="cancel_reservation">
                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Custom Confirmation Modal -->
<div id="cancelConfirmModal" class="confirm-modal-overlay">
    <div class="confirm-modal">
        <h2>CONFIRM CANCELLATION</h2>
        <p>Are you sure you want to cancel this reservation?</p>
        <div class="confirm-modal-buttons">
            <button type="button" class="confirm-modal-btn cancel" onclick="closeCancelModal()">CANCEL</button>
            <button type="button" class="confirm-modal-btn confirm" id="confirmCancelBtn">YES, CANCEL</button>
        </div>
    </div>
</div>

<script>
let currentReservationId = null;

function openCancelModal(reservationId) {
    currentReservationId = reservationId;
    const modal = document.getElementById('cancelConfirmModal');
    modal.classList.add('active');
    
    // Set up the confirm button to submit the form
    const confirmBtn = document.getElementById('confirmCancelBtn');
    confirmBtn.onclick = function() {
        const form = document.getElementById('cancel-form-' + reservationId);
        if (form) {
            form.submit();
        }
    };
}

function closeCancelModal() {
    const modal = document.getElementById('cancelConfirmModal');
    modal.classList.remove('active');
    currentReservationId = null;
}

// Close modal when clicking outside
document.getElementById('cancelConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCancelModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

</body>
</html>

