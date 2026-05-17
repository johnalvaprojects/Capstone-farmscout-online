<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/fs_reservation_helpers.php';

if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. DTI access required.'));
    exit;
}

$reservations_org = isSuperAdmin() ? 'Super Admin' : 'DTI';
$page_title = isSuperAdmin() ? 'Super Admin Reservations - FarmScout Online' : 'DTI Reservations - FarmScout Online';
$page_description = $reservations_org . ' - Manage all platform reservations';
$reservations_heading = isSuperAdmin() ? 'SUPER ADMIN RESERVATIONS MANAGEMENT' : 'DTI RESERVATIONS MANAGEMENT';

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? 'admin';
$conn = getDB();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        switch ($_POST['action']) {
            case 'update_status':
                $reservation_id = intval($_POST['reservation_id'] ?? 0);
                $new_status = sanitizeInput($_POST['new_status'] ?? '');
                $valid_statuses = ['pending', 'confirmed', 'paid', 'completed', 'cancelled'];
                
                if ($reservation_id > 0 && in_array($new_status, $valid_statuses)) {
                    try {
                        $root_id = function_exists('fs_reservation_root_id')
                            ? fs_reservation_root_id($conn, $reservation_id)
                            : $reservation_id;

                        $current_stmt = $conn->prepare("SELECT r.*, mp.product_name, u1.username as customer_name, u2.username as farmer_name 
                                                         FROM reservations r
                                                         JOIN market_products mp ON r.product_id = mp.id
                                                         JOIN users u1 ON r.user_id = u1.id
                                                         JOIN users u2 ON r.farmer_id = u2.id
                                                         WHERE r.id = ?");
                        $current_stmt->execute([$root_id]);
                        $current_reservation = $current_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($current_reservation) {
                            $old_status = $current_reservation['status'];
                            $group_sql = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
                                ? "UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ? OR parent_reservation_id = ?"
                                : "UPDATE reservations SET status = ?, updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($group_sql);
                            if (strpos($group_sql, 'parent_reservation_id') !== false) {
                                $update_stmt->execute([$new_status, $root_id, $root_id]);
                            } else {
                                $update_stmt->execute([$new_status, $root_id]);
                            }
                            
                            if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
                                $ts_sql = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
                                    ? "UPDATE reservations SET confirmed_at = NOW() WHERE id = ? OR parent_reservation_id = ?"
                                    : "UPDATE reservations SET confirmed_at = NOW() WHERE id = ?";
                                $timestamp_stmt = $conn->prepare($ts_sql);
                                $timestamp_stmt->execute(strpos($ts_sql, 'parent_reservation_id') !== false ? [$root_id, $root_id] : [$root_id]);
                            } elseif ($new_status === 'paid' && $old_status !== 'paid') {
                                $ts_sql = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
                                    ? "UPDATE reservations SET paid_at = NOW() WHERE id = ? OR parent_reservation_id = ?"
                                    : "UPDATE reservations SET paid_at = NOW() WHERE id = ?";
                                $timestamp_stmt = $conn->prepare($ts_sql);
                                $timestamp_stmt->execute(strpos($ts_sql, 'parent_reservation_id') !== false ? [$root_id, $root_id] : [$root_id]);
                            } elseif ($new_status === 'completed' && $old_status !== 'completed') {
                                $ts_sql = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
                                    ? "UPDATE reservations SET completed_at = NOW() WHERE id = ? OR parent_reservation_id = ?"
                                    : "UPDATE reservations SET completed_at = NOW() WHERE id = ?";
                                $timestamp_stmt = $conn->prepare($ts_sql);
                                $timestamp_stmt->execute(strpos($ts_sql, 'parent_reservation_id') !== false ? [$root_id, $root_id] : [$root_id]);
                            }
                            
                            $log_product = $current_reservation['product_name'];
                            if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
                                $pn = $conn->prepare(
                                    "SELECT GROUP_CONCAT(mp.product_name ORDER BY r.id SEPARATOR ', ') FROM reservations r INNER JOIN market_products mp ON r.product_id = mp.id WHERE r.id = ? OR r.parent_reservation_id = ?"
                                );
                                $pn->execute([$root_id, $root_id]);
                                $agg = $pn->fetchColumn();
                                if (is_string($agg) && $agg !== '') {
                                    $log_product = $agg;
                                }
                            }

                            logAdminAction($conn, $admin_id, $admin_username, 'reservation_status_update', [
                                'reservation_id' => $root_id,
                                'product_name' => $log_product,
                                'customer' => $current_reservation['customer_name'],
                                'farmer' => $current_reservation['farmer_name'],
                                'old_status' => $old_status,
                                'new_status' => $new_status
                            ]);
                            
                            $disp = !empty($current_reservation['public_ref']) ? $current_reservation['public_ref'] : ('#' . $root_id);
                            $message = "Reservation {$disp} status updated from '{$old_status}' to '{$new_status}' successfully!";
                            $message_type = 'success';
                        } else {
                            $message = 'Reservation not found!';
                            $message_type = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Error updating reservation: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Error updating reservation {$reservation_id}: " . $e->getMessage());
                    }
                } else {
                    $message = 'Invalid reservation ID or status!';
                    $message_type = 'error';
                }
                break;
                
            case 'cancel_reservation':
                $reservation_id = intval($_POST['reservation_id'] ?? 0);
                
                if ($reservation_id > 0) {
                    try {
                        $root_id = function_exists('fs_reservation_root_id')
                            ? fs_reservation_root_id($conn, $reservation_id)
                            : $reservation_id;

                        $current_stmt = $conn->prepare("SELECT r.*, mp.product_name, u1.username as customer_name, u2.username as farmer_name 
                                                         FROM reservations r
                                                         JOIN market_products mp ON r.product_id = mp.id
                                                         JOIN users u1 ON r.user_id = u1.id
                                                         JOIN users u2 ON r.farmer_id = u2.id
                                                         WHERE r.id = ?");
                        $current_stmt->execute([$root_id]);
                        $current_reservation = $current_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($current_reservation) {
                            $old_status = $current_reservation['status'];
                            $group_sql = (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn))
                                ? "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ? OR parent_reservation_id = ?"
                                : "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($group_sql);
                            if (strpos($group_sql, 'parent_reservation_id') !== false) {
                                $update_stmt->execute([$root_id, $root_id]);
                            } else {
                                $update_stmt->execute([$root_id]);
                            }
                            
                            $log_product = $current_reservation['product_name'];
                            if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
                                $pn = $conn->prepare(
                                    "SELECT GROUP_CONCAT(mp.product_name ORDER BY r.id SEPARATOR ', ') FROM reservations r INNER JOIN market_products mp ON r.product_id = mp.id WHERE r.id = ? OR r.parent_reservation_id = ?"
                                );
                                $pn->execute([$root_id, $root_id]);
                                $agg = $pn->fetchColumn();
                                if (is_string($agg) && $agg !== '') {
                                    $log_product = $agg;
                                }
                            }

                            logAdminAction($conn, $admin_id, $admin_username, 'reservation_cancelled', [
                                'reservation_id' => $root_id,
                                'product_name' => $log_product,
                                'customer' => $current_reservation['customer_name'],
                                'farmer' => $current_reservation['farmer_name'],
                                'old_status' => $old_status
                            ]);
                            
                            $disp = !empty($current_reservation['public_ref']) ? $current_reservation['public_ref'] : ('#' . $root_id);
                            $message = "Reservation {$disp} cancelled successfully!";
                            $message_type = 'success';
                        } else {
                            $message = 'Reservation not found!';
                            $message_type = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Error cancelling reservation: ' . $e->getMessage();
                        $message_type = 'error';
                        error_log("Error cancelling reservation {$reservation_id}: " . $e->getMessage());
                    }
                } else {
                    $message = 'Invalid reservation ID!';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_farmer = intval($_GET['farmer_id'] ?? 0);
$filter_customer = intval($_GET['customer_id'] ?? 0);
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query with filters
$reservations_query = "SELECT r.*, 
                       mp.product_name,
                       m.market_name,
                       u1.id as customer_id,
                       u1.username as customer_username,
                       u1.full_name as customer_name,
                       u2.id as farmer_id,
                       u2.username as farmer_username,
                       u2.full_name as farmer_name
                       FROM reservations r
                       JOIN market_products mp ON r.product_id = mp.id
                       JOIN markets m ON r.market_id = m.id
                       JOIN users u1 ON r.user_id = u1.id
                       JOIN users u2 ON r.farmer_id = u2.id
                       WHERE 1=1";

$params = [];

if (!empty($filter_status)) {
    $reservations_query .= " AND r.status = ?";
    $params[] = $filter_status;
}

if ($filter_farmer > 0) {
    $reservations_query .= " AND r.farmer_id = ?";
    $params[] = $filter_farmer;
}

if ($filter_customer > 0) {
    $reservations_query .= " AND r.user_id = ?";
    $params[] = $filter_customer;
}

if (!empty($filter_date_from)) {
    $reservations_query .= " AND DATE(r.created_at) >= ?";
    $params[] = $filter_date_from;
}

if (!empty($filter_date_to)) {
    $reservations_query .= " AND DATE(r.created_at) <= ?";
    $params[] = $filter_date_to;
}

if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
    $reservations_query .= " AND r.parent_reservation_id IS NULL ";
}

$reservations_query .= " ORDER BY r.created_at DESC LIMIT 500";

$reservations_stmt = $conn->prepare($reservations_query);
$reservations_stmt->execute($params);
$reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC);

if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn) && $reservations) {
    $lineStmt = $conn->prepare(
        "SELECT r.id, r.quantity, r.unit, mp.product_name FROM reservations r
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

// Get all farmers and customers for filter dropdowns
$farmers_query = "SELECT DISTINCT u.id, u.username, u.full_name 
                  FROM users u
                  JOIN reservations r ON u.id = r.farmer_id
                  ORDER BY u.full_name, u.username";
$farmers_stmt = $conn->prepare($farmers_query);
$farmers_stmt->execute();
$farmers = $farmers_stmt->fetchAll(PDO::FETCH_ASSOC);

$customers_query = "SELECT DISTINCT u.id, u.username, u.full_name 
                    FROM users u
                    JOIN reservations r ON u.id = r.user_id
                    ORDER BY u.full_name, u.username";
$customers_stmt = $conn->prepare($customers_query);
$customers_stmt->execute();
$customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Set current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'includes/favicon.php'; ?>
</head>
<body>
<?php
include 'includes/header-market-finder.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body.admin-reservations-page {
        font-family: 'VT323', monospace !important;
        background-color: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
        margin: 0 !important;
        min-height: 100vh;
    }
    
    /* Notification Styles */
    .slide-notification {
        position: fixed;
        top: 20px;
        left: 20px;
        background: #000000;
        color: white;
        padding: 16px 20px;
        border: 3px solid #000000;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        max-width: 400px;
        animation: slideInFromLeft 0.3s ease-out forwards;
        font-family: 'VT323', monospace;
    }
    
    .slide-notification .notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .slide-notification .notification-text {
        flex: 1;
        color: #ffffff;
        font-weight: 500;
        font-size: 1rem;
        font-family: 'VT323', monospace;
    }
    
    .slide-notification .close-btn {
        margin-left: auto;
        background: none;
        border: 2px solid #ffffff;
        color: #ffffff;
        cursor: pointer;
        padding: 4px 8px;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-family: 'VT323', monospace;
        transition: all 0.3s ease;
    }
    
    .slide-notification .close-btn:hover {
        background: #ffffff;
        color: #000000;
    }
    
    @keyframes slideInFromLeft {
        0% {
            left: -400px;
            opacity: 0;
        }
        100% {
            left: 20px;
            opacity: 1;
        }
    }
    
    .slide-notification.auto-hide {
        animation: slideInFromLeft 0.3s ease-out forwards, slideOutToLeft 0.3s ease-in 4.7s forwards;
    }
    
    @keyframes slideOutToLeft {
        0% {
            left: 20px;
            opacity: 1;
        }
        100% {
            left: -400px;
            opacity: 0;
        }
    }
    
    /* Hero Section */
    .admin-reservations-hero {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .admin-reservations-hero h1 {
        font-size: clamp(1.5rem, 4vw, 2.5rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.25rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    .admin-reservations-hero p {
        font-size: clamp(0.8rem, 1.2vw, 1rem) !important;
        letter-spacing: 0.05em !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
        opacity: 0.8;
    }
    
    /* Filters Section */
    .filters-section {
        border: 3px solid #000000;
        border-radius: 8px;
        padding: 1.25rem;
        background-color: #ffffff;
        margin-bottom: 1.5rem;
        box-shadow: 4px 4px 0 #000000;
    }
    
    .filters-section h2 {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 1rem;
        font-family: 'VT323', monospace;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .filter-group label {
        font-size: 0.9rem;
        font-weight: bold;
        font-family: 'VT323', monospace;
        text-transform: uppercase;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 0.5rem;
        border: 2px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 1rem;
        background-color: #ffffff;
        color: #000000;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .admin-btn {
        font-family: 'VT323', monospace !important;
        font-size: 0.9rem;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.5rem 1rem;
        border: 2px solid #000000;
        background-color: #000000;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }
    
    .admin-btn:hover {
        background-color: #ffffff;
        color: #000000;
    }
    
    .admin-btn-secondary {
        background-color: #ffffff;
        color: #000000;
    }
    
    .admin-btn-secondary:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    /* View Toggle Navigation - Modern Segmented Control */
    .view-toggle {
        display: inline-flex;
        gap: 0;
        margin-bottom: 2rem;
        padding: 0.4rem;
        border: 3px solid #000000;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow-x: auto;
        width: 100%;
        max-width: 100%;
    }
    
    .view-toggle::-webkit-scrollbar {
        height: 6px;
    }
    
    .view-toggle::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .view-toggle::-webkit-scrollbar-thumb {
        background: #000000;
        border-radius: 3px;
    }
    
    .view-toggle-btn {
        padding: 0.75rem 1.25rem;
        border: none;
        background-color: transparent;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.95rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        text-decoration: none;
        display: inline-block;
        white-space: nowrap;
        position: relative;
        border-radius: 8px;
        flex-shrink: 0;
    }
    
    .view-toggle-btn:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 20%;
        height: 60%;
        width: 1px;
        background: #e0e0e0;
    }
    
    .view-toggle-btn:hover {
        background-color: #f5f5f5;
        color: #000000;
    }
    
    .view-toggle-btn.active {
        background-color: #000000;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .view-toggle-btn.active::after {
        display: none;
    }
    
    /* Table */
    .admin-card {
        border: 3px solid #000000;
        border-radius: 8px;
        padding: 1.25rem;
        background-color: #ffffff;
        margin-bottom: 1.5rem;
        overflow-x: hidden;
        box-shadow: 4px 4px 0 #000000;
        transition: all 0.3s ease;
    }
    
    .admin-card:hover {
        transform: translateY(-2px);
    }
    
    .admin-card h2 {
        font-size: clamp(1.2rem, 2.5vw, 1.5rem) !important;
        font-weight: bold !important;
        margin-bottom: 0.75rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
        letter-spacing: 0.05em;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'VT323', monospace;
        table-layout: fixed;
    }
    
    .data-table th {
        text-align: left;
        padding: 0.6rem;
        border-bottom: 2px solid #000000;
        font-weight: bold;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background-color: #f5f5f5;
        white-space: normal;
        word-break: break-word;
    }
    
    .data-table td {
        padding: 0.6rem;
        border-bottom: 1px solid #000000;
        font-size: 0.85rem;
        white-space: normal;
        word-break: break-word;
    }
    
    .data-table tr:hover {
        background-color: #f5f5f5;
    }
    
    /* Status Badges */
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border: 2px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 0.8rem;
        font-weight: bold;
    }
    
    .status-badge.pending {
        background-color: #fbbf24;
        color: #000000;
    }
    
    .status-badge.confirmed {
        background-color: #22c55e;
        color: #000000;
    }

    .status-badge.paid {
        background-color: #06b6d4;
        color: #000000;
    }
    
    .status-badge.completed {
        background-color: #3b82f6;
        color: #ffffff;
    }
    
    .status-badge.cancelled {
        background-color: #6b7280;
        color: #ffffff;
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .admin-btn-small {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }
    
    /* Confirmation Modal */
    .confirm-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    
    .confirm-modal.active {
        display: flex;
    }
    
    .confirm-modal-content {
        background: #ffffff;
        border: 3px solid #000000;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        font-family: 'VT323', monospace;
    }
    
    .confirm-modal-title {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .confirm-modal-message {
        font-size: 1rem;
        margin-bottom: 1.5rem;
        line-height: 1.4;
    }
    
    .confirm-modal-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }
    
    .confirm-modal-btn {
        padding: 0.5rem 1.5rem;
        border: 2px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 1rem;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .confirm-modal-btn-primary {
        background-color: #000000;
        color: #ffffff;
    }
    
    .confirm-modal-btn-primary:hover {
        background-color: #ffffff;
        color: #000000;
    }
    
    .confirm-modal-btn-secondary {
        background-color: #ffffff;
        color: #000000;
    }
    
    .confirm-modal-btn-secondary:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    .confirm-modal-btn-danger {
        background-color: #ffffff;
        color: #dc2626;
        border-color: #dc2626;
    }
    
    .confirm-modal-btn-danger:hover {
        background-color: #dc2626;
        color: #ffffff;
    }
    
    /* Status Select */
    .status-select {
        padding: 0.25rem 0.5rem;
        border: 2px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 0.8rem;
        background-color: #ffffff;
        color: #000000;
    }
</style>

<body class="admin-reservations-page">
<div style="width: 100%; max-width: 100%; margin: 0; padding: 2rem 1.5rem;">

    <!-- Success Notification -->
    <?php if ($message && $message_type === 'success'): ?>
    <div class="slide-notification auto-hide" id="notification">
        <div class="notification-content">
            <div class="notification-text"><?php echo htmlspecialchars($message); ?></div>
            <button class="close-btn" onclick="document.getElementById('notification').remove()">×</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Notification -->
    <?php if ($message && $message_type === 'error'): ?>
    <div class="slide-notification auto-hide" id="notification-error" style="background: #dc2626;">
        <div class="notification-content">
            <div class="notification-text"><?php echo htmlspecialchars($message); ?></div>
            <button class="close-btn" onclick="document.getElementById('notification-error').remove()">×</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="admin-reservations-hero">
        <h1><?php echo htmlspecialchars($reservations_heading); ?></h1>
        <p>Monitor and manage all platform reservations</p>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <h2>FILTERS</h2>
        <form method="GET" action="admin-reservations.php">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="farmer_id">Farmer</label>
                    <select name="farmer_id" id="farmer_id">
                        <option value="">All Farmers</option>
                        <?php foreach ($farmers as $farmer): ?>
                            <option value="<?php echo $farmer['id']; ?>" <?php echo $filter_farmer == $farmer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($farmer['full_name'] ?: $farmer['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="customer_id">Customer</label>
                    <select name="customer_id" id="customer_id">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" <?php echo $filter_customer == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['full_name'] ?: $customer['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="admin-btn">APPLY FILTERS</button>
                <a href="admin-reservations.php" class="admin-btn admin-btn-secondary">CLEAR FILTERS</a>
            </div>
        </form>
    </div>

    <!-- Reservations Table -->
    <div class="admin-card">
        <h2>ALL RESERVATIONS (<?php echo count($reservations); ?>)</h2>
        <?php if (empty($reservations)): ?>
            <p>No reservations found.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reservation</th>
                        <th>Product</th>
                        <th>Farmer</th>
                        <th>Customer</th>
                        <th>Quantity</th>
                        <th>Market</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <?php
                            $lines = !empty($reservation['line_items']) ? $reservation['line_items'] : [];
                            $product_label = $reservation['product_name'];
                            if (count($lines) > 1) {
                                $names = array_map(static function ($ln) {
                                    return (string)($ln['product_name'] ?? '');
                                }, $lines);
                                $product_label = implode(', ', array_filter($names));
                            }
                            $rid = (int)($reservation['id'] ?? 0);
                            $ref_disp = !empty($reservation['public_ref']) ? htmlspecialchars((string)$reservation['public_ref']) : ('#' . $rid);
                        ?>
                        <tr>
                            <td><?php echo $ref_disp; ?></td>
                            <td><?php echo htmlspecialchars($product_label); ?></td>
                            <td><?php echo htmlspecialchars($reservation['farmer_name'] ?: $reservation['farmer_username']); ?></td>
                            <td><?php echo htmlspecialchars($reservation['customer_name'] ?: $reservation['customer_username']); ?></td>
                            <td><?php echo count($lines) > 1 ? htmlspecialchars(count($lines) . ' lines') : (number_format($reservation['quantity'], 2) . ' ' . htmlspecialchars($reservation['unit'] ?? 'kg')); ?></td>
                            <td><?php echo htmlspecialchars($reservation['market_name']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $reservation['status']; ?>">
                                    <?php echo htmlspecialchars(ucfirst($reservation['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($reservation['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <select class="status-select" onchange="updateStatus(<?php echo $reservation['id']; ?>, this.value, '<?php echo htmlspecialchars($reservation['status']); ?>')">
                                        <option value="pending" <?php echo $reservation['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $reservation['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="paid" <?php echo $reservation['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="completed" <?php echo $reservation['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $reservation['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <?php if ($reservation['status'] !== 'cancelled'): ?>
                                        <button type="button" class="admin-btn admin-btn-small" onclick="cancelReservation(<?php echo (int)$reservation['id']; ?>, <?php echo json_encode($product_label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)">CANCEL</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-title" id="confirmModalTitle">CONFIRM ACTION</div>
        <div class="confirm-modal-message" id="confirmModalMessage"></div>
        <form id="confirmForm" method="POST" action="admin-reservations.php" style="display: none;">
            <input type="hidden" name="action" id="confirmAction">
            <input type="hidden" name="reservation_id" id="confirmReservationId">
            <input type="hidden" name="new_status" id="confirmNewStatus">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        </form>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-modal-btn confirm-modal-btn-secondary" onclick="closeConfirmModal()">CANCEL</button>
            <button type="button" class="confirm-modal-btn" id="confirmModalSubmitBtn" onclick="submitConfirmForm()">CONFIRM</button>
        </div>
    </div>
</div>

<script>
    function updateStatus(reservationId, newStatus, currentStatus) {
        if (newStatus === currentStatus) {
            // Reset select to current status
            event.target.value = currentStatus;
            return;
        }
        
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmModalTitle');
        const message = document.getElementById('confirmModalMessage');
        const form = document.getElementById('confirmForm');
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        
        title.textContent = 'UPDATE STATUS';
        message.textContent = `Change reservation ${reservationId} status from "${currentStatus}" to "${newStatus}"?`;
        
        document.getElementById('confirmAction').value = 'update_status';
        document.getElementById('confirmReservationId').value = reservationId;
        document.getElementById('confirmNewStatus').value = newStatus;
        
        submitBtn.className = 'confirm-modal-btn confirm-modal-btn-primary';
        submitBtn.textContent = 'UPDATE';
        
        modal.classList.add('active');
    }
    
    function cancelReservation(reservationId, productName) {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmModalTitle');
        const message = document.getElementById('confirmModalMessage');
        const form = document.getElementById('confirmForm');
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        
        title.textContent = 'CANCEL RESERVATION';
        message.textContent = `Cancel reservation #${reservationId} for "${productName}"? This action cannot be undone.`;
        
        document.getElementById('confirmAction').value = 'cancel_reservation';
        document.getElementById('confirmReservationId').value = reservationId;
        document.getElementById('confirmNewStatus').value = '';
        
        submitBtn.className = 'confirm-modal-btn confirm-modal-btn-danger';
        submitBtn.textContent = 'CANCEL RESERVATION';
        
        modal.classList.add('active');
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        // Reset select dropdowns
        document.querySelectorAll('.status-select').forEach(select => {
            select.value = select.getAttribute('data-current-status') || select.options[select.selectedIndex].value;
        });
    }
    
    function submitConfirmForm() {
        document.getElementById('confirmForm').submit();
    }
    
    // Close modal when clicking outside
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    
    // Store current status on page load for reset
    document.querySelectorAll('.status-select').forEach(select => {
        select.setAttribute('data-current-status', select.value);
    });
</script>

</body>
</html>
