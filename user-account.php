<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/fs_reservation_helpers.php';
requireLogin();

$page_title = 'My Account - FarmScout Online';
$page_description = 'Manage your FarmScout profile, reservations, notifications, and account security.';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'user';
$conn = getDB();

$success_message = '';
$error_message = '';
$login_success_message = '';

if (isset($_GET['message']) && !isset($_POST['action'])) {
    $login_success_message = sanitizeInput($_GET['message']);
}

$valid_sections = ['profile', 'password', 'reservations', 'price_alerts', 'notifications', 'delete'];
$current_section = isset($_GET['section']) ? sanitizeInput($_GET['section']) : 'profile';
if (!in_array($current_section, $valid_sections, true)) {
    $current_section = 'profile';
}

$user_email = '';
$user_full_name = '';
$user_phone = '';
$user_address = '';
$user_created_at = '';
$user_last_login = '';
$email_notifications_enabled = true;

if ($conn) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'email_notifications'");
        if ($check && $check->rowCount() === 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) DEFAULT 1");
        }

        $stmt = $conn->prepare("
            SELECT email, full_name, phone_number, address, created_at, last_login,
                   COALESCE(email_notifications, 1) AS email_notifications
            FROM users
            WHERE id = :user_id
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $user_email = (string)($user_data['email'] ?? '');
            $user_full_name = (string)($user_data['full_name'] ?? '');
            $user_phone = (string)($user_data['phone_number'] ?? '');
            $user_address = (string)($user_data['address'] ?? '');
            $user_created_at = (string)($user_data['created_at'] ?? '');
            $user_last_login = (string)($user_data['last_login'] ?? '');
            $email_notifications_enabled = (bool)($user_data['email_notifications'] ?? 1);
        }
    } catch (Exception $e) {
        error_log('user-account: failed loading user data: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);
    $needs_csrf = !in_array($action, ['update_profile', 'change_password'], true);

    if ($needs_csrf && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (!$conn) {
        $error_message = 'Database connection failed. Please try again.';
    } else {
        try {
            switch ($action) {
                case 'update_profile':
                    $full_name = sanitizeInput($_POST['full_name'] ?? '');
                    $phone_number = sanitizeInput($_POST['phone_number'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;

                    if ($full_name === '') {
                        $error_message = 'Full name is required.';
                        break;
                    }

                    $stmt = $conn->prepare("
                        UPDATE users
                        SET full_name = :full_name,
                            phone_number = :phone_number,
                            address = :address,
                            email_notifications = :email_notifications,
                            updated_at = NOW()
                        WHERE id = :user_id
                    ");
                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':phone_number' => $phone_number,
                        ':address' => $address,
                        ':email_notifications' => $email_notifications,
                        ':user_id' => $user_id,
                    ]);
                    $_SESSION['full_name'] = $full_name;
                    $user_full_name = $full_name;
                    $user_phone = $phone_number;
                    $user_address = $address;
                    $email_notifications_enabled = (bool)$email_notifications;
                    $success_message = 'Profile updated successfully.';
                    break;

                case 'change_password':
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
                        $error_message = 'All password fields are required.';
                        break;
                    }
                    if ($new_password !== $confirm_password) {
                        $error_message = 'New password and confirmation do not match.';
                        break;
                    }
                    if (strlen($new_password) < 8) {
                        $error_message = 'New password must be at least 8 characters long.';
                        break;
                    }

                    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :user_id LIMIT 1");
                    $stmt->execute([':user_id' => $user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user || !password_verify($current_password, $user['password_hash'])) {
                        $error_message = 'Current password is incorrect.';
                        break;
                    }

                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :user_id");
                    $stmt->execute([':hash' => $hash, ':user_id' => $user_id]);
                    $success_message = 'Password changed successfully.';
                    break;

                case 'add_price_alert':
                    $product_id = (int)($_POST['product_id'] ?? 0);
                    $alert_type = sanitizeInput($_POST['alert_type'] ?? 'below');
                    $target_price = (float)($_POST['target_price'] ?? 0);
                    $market_id = (int)($_POST['market_id'] ?? 0);

                    if ($product_id <= 0) {
                        $error_message = 'Please select a valid product.';
                        break;
                    }
                    if (!in_array($alert_type, ['below', 'above', 'change'], true)) {
                        $error_message = 'Invalid alert type.';
                        break;
                    }
                    if ($alert_type !== 'change' && $target_price <= 0) {
                        $error_message = 'Please enter a valid target price.';
                        break;
                    }

                    if (function_exists('createPriceAlert') && createPriceAlert($user_email, $product_id, $alert_type, $target_price)) {
                        $success_message = 'Price alert saved.';
                        header('Location: user-account.php?section=price_alerts' . ($market_id > 0 ? ('&market_id=' . $market_id) : ''));
                        exit;
                    }

                    $error_message = 'Failed to save price alert. Please try again.';
                    break;

                case 'remove_price_alert':
                    $alert_id = (int)($_POST['alert_id'] ?? 0);
                    $market_id = (int)($_POST['market_id'] ?? 0);
                    if ($alert_id <= 0) {
                        $error_message = 'Invalid alert.';
                        break;
                    }

                    if (function_exists('deletePriceAlert') && deletePriceAlert($alert_id, $user_email)) {
                        $success_message = 'Price alert removed.';
                        header('Location: user-account.php?section=price_alerts' . ($market_id > 0 ? ('&market_id=' . $market_id) : ''));
                        exit;
                    }

                    $error_message = 'Failed to remove price alert.';
                    break;

                case 'cancel_reservation':
                    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
                    if ($reservation_id <= 0) {
                        $error_message = 'Invalid reservation.';
                        break;
                    }

                    $root_id = function_exists('fs_reservation_root_id')
                        ? fs_reservation_root_id($conn, $reservation_id)
                        : $reservation_id;

                    $stmt = $conn->prepare("SELECT status FROM reservations WHERE id = :id AND user_id = :user_id LIMIT 1");
                    $stmt->execute([':id' => $root_id, ':user_id' => $user_id]);
                    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$reservation || !in_array($reservation['status'], ['pending', 'confirmed'], true)) {
                        $error_message = 'Reservation not found or cannot be cancelled.';
                        break;
                    }

                    if (fs_reservations_parent_column_exists($conn)) {
                        $stmt = $conn->prepare(
                            "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE (id = :id OR parent_reservation_id = :id2) AND user_id = :user_id"
                        );
                        $stmt->execute([':id' => $root_id, ':id2' => $root_id, ':user_id' => $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = :id AND user_id = :user_id");
                        $stmt->execute([':id' => $root_id, ':user_id' => $user_id]);
                    }
                    if (function_exists('createSystemMessage')) {
                        createSystemMessage($root_id, 'You cancelled this reservation');
                    }
                    $success_message = 'Reservation cancelled successfully.';
                    break;

                case 'archive_reservation':
                case 'unarchive_reservation':
                    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
                    if ($reservation_id <= 0) {
                        $error_message = 'Invalid reservation.';
                        break;
                    }

                    $check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'is_archived'");
                    if (!$check || $check->rowCount() === 0) {
                        $error_message = 'Archive feature is not available yet.';
                        break;
                    }

                    $is_archived = $action === 'archive_reservation' ? 1 : 0;
                    $root_id = function_exists('fs_reservation_root_id')
                        ? fs_reservation_root_id($conn, $reservation_id)
                        : $reservation_id;
                    if (fs_reservations_parent_column_exists($conn)) {
                        $stmt = $conn->prepare(
                            "UPDATE reservations SET is_archived = :archived, updated_at = NOW() WHERE (id = :id OR parent_reservation_id = :id2) AND user_id = :user_id"
                        );
                        $stmt->execute([':archived' => $is_archived, ':id' => $root_id, ':id2' => $root_id, ':user_id' => $user_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE reservations SET is_archived = :archived, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
                        $stmt->execute([':archived' => $is_archived, ':id' => $root_id, ':user_id' => $user_id]);
                    }
                    $success_message = $is_archived ? 'Reservation archived.' : 'Reservation unarchived.';
                    break;

                case 'mark_notification_read':
                    $notification_id = (int)($_POST['notification_id'] ?? 0);
                    if ($notification_id <= 0) {
                        $error_message = 'Invalid notification.';
                        break;
                    }
                    $table = $conn->query("SHOW TABLES LIKE 'notifications'");
                    if ($table && $table->rowCount() > 0) {
                        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :user_id");
                        $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
                        $success_message = 'Notification marked as read.';
                    }
                    break;

                case 'delete_account':
                    $confirm_username = sanitizeInput($_POST['confirm_username'] ?? '');
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if ($confirm_username !== $username) {
                        $error_message = 'Username confirmation does not match.';
                        break;
                    }
                    if ($confirm_password === '') {
                        $error_message = 'Please enter your password to confirm account deletion.';
                        break;
                    }

                    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :user_id LIMIT 1");
                    $stmt->execute([':user_id' => $user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$user || !password_verify($confirm_password, $user['password_hash'])) {
                        $error_message = 'Invalid password. Account deletion cancelled.';
                        break;
                    }

                    $conn->beginTransaction();
                    $stmt = $conn->prepare("DELETE FROM price_alerts WHERE user_email = :email");
                    $stmt->execute([':email' => $user_email]);
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
                    $stmt->execute([':user_id' => $user_id]);
                    $conn->commit();
                    session_destroy();
                    header('Location: index.php?deleted=1');
                    exit;
            }
        } catch (Exception $e) {
            if ($conn && $conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('user-account action error: ' . $e->getMessage());
            $error_message = 'Something went wrong. Please try again.';
        }
    }
}

$has_archive = false;
$reservations = [];
$notifications = [];
$unread_notifications = 0;
$price_alerts = [];
$price_alert_markets = [];
$price_alert_products = [];
$price_alert_market_id = (int)($_GET['market_id'] ?? 0);

if ($conn) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'is_archived'");
        $has_archive = $check && $check->rowCount() > 0;
    } catch (Exception $e) {
        $has_archive = false;
    }

    if ($current_section === 'reservations') {
        try {
            $status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
            $allowed_status = ['pending', 'confirmed', 'paid', 'completed', 'cancelled'];
            $status_condition = in_array($status_filter, $allowed_status, true) ? "AND r.status = :status" : "";
            $archive_condition = "";

            if ($status_filter === 'archived' && $has_archive) {
                $archive_condition = "AND r.is_archived = 1";
            } elseif ($has_archive) {
                $archive_condition = "AND (r.is_archived = 0 OR r.is_archived IS NULL)";
            }

            $root_only = '';
            if (function_exists('fs_reservations_parent_column_exists') && fs_reservations_parent_column_exists($conn)) {
                $root_only = ' AND r.parent_reservation_id IS NULL ';
            }

            $query = "
                SELECT r.*,
                       mp.product_name, mp.product_image,
                       u.full_name AS farmer_name, u.email AS farmer_email, u.phone_number AS farmer_phone,
                       m.market_name, m.address AS market_address
                FROM reservations r
                LEFT JOIN market_products mp ON r.product_id = mp.id
                LEFT JOIN users u ON r.farmer_id = u.id
                LEFT JOIN markets m ON r.market_id = m.id
                WHERE r.user_id = :user_id
                $root_only
                $status_condition
                $archive_condition
                ORDER BY r.created_at DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            if ($status_condition) {
                $stmt->bindValue(':status', $status_filter);
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
        } catch (Exception $e) {
            error_log('user-account reservations error: ' . $e->getMessage());
        }
    }

    if ($current_section === 'price_alerts') {
        try {
            $price_alert_markets = function_exists('getMarkets') ? (array)getMarkets() : [];
            if ($price_alert_market_id > 0) {
                $hasDeletedAt = false;
                try {
                    $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
                    $hasDeletedAt = $col_check->rowCount() > 0;
                } catch (Exception $e) {
                }
                $deletedAtCheck = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";

                $stmt = $conn->prepare("
                    SELECT
                        mp.id,
                        mp.product_name AS name,
                        mp.product_description AS description,
                        mp.category,
                        mp.price AS current_price,
                        mp.unit,
                        mp.product_image AS image_url,
                        mp.market_id,
                        m.market_name
                    FROM market_products mp
                    LEFT JOIN markets m ON mp.market_id = m.id
                    WHERE mp.market_id = :market_id
                      AND mp.is_available = 1
                      $deletedAtCheck
                    ORDER BY mp.product_name ASC
                ");
                $stmt->execute([':market_id' => $price_alert_market_id]);
                $price_alert_products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $price_alerts = function_exists('getPriceAlerts') ? (array)getPriceAlerts($user_email) : [];
        } catch (Exception $e) {
            error_log('user-account price_alerts: ' . $e->getMessage());
            $price_alerts = [];
            $price_alert_products = [];
        }
    }

    if ($current_section === 'notifications') {
        try {
            $table = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($table && $table->rowCount() > 0) {
                $stmt = $conn->prepare("
                    SELECT id, type, title, message, link, is_read, created_at, read_at
                    FROM notifications
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([':user_id' => $user_id]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0");
                $stmt->execute([':user_id' => $user_id]);
                $unread_notifications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['unread_count'] ?? 0);
            }
        } catch (Exception $e) {
            error_log('user-account notifications error: ' . $e->getMessage());
        }
    }
}

$csrf_token = getCSRFToken();

$display_name = trim($user_full_name ?: $username);
$initials = 'U';
if ($display_name !== '') {
    $parts = preg_split('/\s+/', $display_name) ?: [];
    $letters = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $letters .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($letters) >= 2) break;
    }
    $initials = $letters !== '' ? $letters : 'U';
}

function account_status_label(string $status): string {
    return strtoupper(str_replace('_', ' ', $status));
}

function account_status_class(string $status): string {
    return preg_replace('/[^a-z0-9_-]/', '', strtolower($status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php include 'includes/favicon.php'; ?>
</head>
<body class="fs-account-page">
<script>
  // Disable global PHP page transitions on this page.
  document.documentElement.classList.add('fs-no-page-transitions');
</script>
<?php include 'includes/header-market-finder.php'; ?>

<style>
    @font-face {
        font-family: "PoppinsLocal";
        src: url("assets/fonts/Poppins-Medium.otf") format("opentype");
        font-weight: 500;
        font-style: normal;
        font-display: swap;
    }

    :root{
        /* FarmScout brand cream (aligned with SPA --sf-bg) */
        --ua-bg: #f5f0e8;
        --ua-card: #ffffff;
        --ua-ink: #101010;
        --ua-muted: rgba(16,16,16,.55);
        --ua-line: rgba(16,16,16,.12);
        --ua-shadow: 0 22px 60px rgba(0,0,0,.10);
        --ua-radius: 18px;
        --ua-tab: rgba(16,16,16,.62);
        --ua-accent: #4A7C59;
    }

    * { box-sizing: border-box; }

    body.fs-account-page {
        margin: 0;
        min-height: 100vh;
        padding: 0;
        background: var(--ua-bg);
        color: var(--ua-ink);
        font-family: PoppinsLocal, Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
    }

    body.fs-account-page .header {
        max-width: none;
        margin: 0;
        padding: 18px clamp(18px, 3vw, 28px);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        background: rgba(245, 240, 232, 0.94);
        backdrop-filter: blur(12px) saturate(150%);
        -webkit-backdrop-filter: blur(12px) saturate(150%);
        border-bottom: 1px solid rgba(17, 17, 17, 0.075);
        box-shadow: 0 14px 36px rgba(17, 17, 17, 0.06);
        font-family: PoppinsLocal, Inter, system-ui, sans-serif !important;
    }
    body.fs-account-page .header .logo{
        font-family: PoppinsLocal, Inter, system-ui, sans-serif !important;
        font-size: clamp(15px, 1.35vw, 17px);
        font-weight: 900;
        letter-spacing: .07em;
        text-transform: uppercase;
        gap: 12px;
        align-items: center;
        color: #111;
        line-height: 1;
        text-decoration: none;
        -webkit-font-smoothing: antialiased;
    }
    body.fs-account-page .header .logo img{
        height: 26px;
        width: 26px;
        border-radius: 8px;
        object-fit: cover;
        background: #111;
        flex-shrink: 0;
    }

    /* Match SPA mobile hamburger button look */
    body.fs-account-page .hamburger-menu{
        border-radius: 10px;
        border-width: 1px;
        border-color: rgba(0,0,0,.16);
        width: 44px;
        height: 44px;
        min-width: 44px;
        min-height: 44px;
    }
    body.fs-account-page .hamburger-line{
        height: 2px;
        border-radius: 2px;
        background-color: #111;
    }
    body.fs-account-page .hamburger-menu:hover{
        background: rgba(17,17,17,.04);
    }
    body.fs-account-page .hamburger-menu:hover .hamburger-line{
        background-color: #111;
    }

    body.fs-account-page .nav {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    /* SPA-style pill controls: plain nav links (Home, role links, etc.) */
    body.fs-account-page .nav > a:not(.login-btn):not(.account-dropdown-toggle) {
        font-family: PoppinsLocal, Inter, system-ui, sans-serif !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 14px !important;
        border-radius: 6px;
        border: 1px solid rgba(17, 17, 17, 0.18) !important;
        background: rgba(255, 255, 255, 0.88);
        color: rgba(17, 17, 17, 0.82) !important;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: none;
        text-decoration: none !important;
        transform: none !important;
    }
    body.fs-account-page .nav > a:not(.login-btn):not(.account-dropdown-toggle):hover {
        color: #111 !important;
        border-color: rgba(17, 17, 17, 0.28) !important;
        background: #ffffff;
    }

    body.fs-account-page .nav .login-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 14px !important;
        border-radius: 6px;
        border: 1px solid rgba(17, 17, 17, 0.18);
        background: rgba(255, 255, 255, 0.88);
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-decoration: none !important;
        transform: none !important;
    }
    body.fs-account-page .nav .login-btn:hover {
        background: #111;
        color: #fafafa;
        border-color: #111;
    }

    /* ACCOUNT dropdown trigger — same chrome as Home pill */
    body.fs-account-page .account-dropdown-toggle {
        font-family: PoppinsLocal, Inter, system-ui, sans-serif !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 0 14px !important;
        border-radius: 6px !important;
        border: 1px solid rgba(17, 17, 17, 0.18) !important;
        background: rgba(255, 255, 255, 0.92) !important;
        color: rgba(17, 17, 17, 0.88) !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        letter-spacing: 0.02em !important;
        text-transform: none !important;
        cursor: pointer;
        transform: none !important;
    }
    body.fs-account-page .account-dropdown-toggle:hover,
    body.fs-account-page .account-dropdown-toggle:focus {
        color: #111 !important;
        border-color: rgba(17, 17, 17, 0.3) !important;
        background: #ffffff !important;
        opacity: 1 !important;
    }
    body.fs-account-page .nav .account-dropdown {
        display: inline-flex;
        align-items: center;
    }

    body.fs-account-page .notification-dropdown {
        display: inline-flex;
        align-items: center;
    }
    body.fs-account-page .notification-bell {
        position: relative;
        width: 40px;
        height: 40px;
        padding: 0 !important;
        margin: 0;
        border-radius: 10px;
        border: 1px solid rgba(17, 17, 17, 0.14) !important;
        background: rgba(255, 255, 255, 0.9) !important;
        color: #111 !important;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.65) inset;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transform: none !important;
        opacity: 1 !important;
    }
    body.fs-account-page .notification-bell:hover {
        border-color: rgba(17, 17, 17, 0.26) !important;
        background: #ffffff !important;
        opacity: 1 !important;
    }
    body.fs-account-page .notification-bell svg {
        width: 20px;
        height: 20px;
    }
    body.fs-account-page .notification-badge {
        border: 2px solid rgba(255, 255, 255, 0.95) !important;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
        font-family: PoppinsLocal, Inter, system-ui, sans-serif !important;
        font-weight: 800;
    }

    /* Remove header/nav hover transitions on Account page */
    body.fs-account-page .header *,
    body.fs-account-page .header *::before,
    body.fs-account-page .header *::after{
        transition: none !important;
        animation: none !important;
    }

    .ua-shell{
        width: 100%;
        margin: 0;
        padding: 74px 22px 44px; /* space for fixed header */
        min-width: 0;
    }

    .ua-banner{
        position: relative;
        width: 100%;
        min-height: 210px;
        border-radius: 0;
        background: radial-gradient(circle at 70% 20%, rgba(255,255,255,.18), transparent 55%),
                    linear-gradient(90deg, #0f2a1c 0%, #1e4a33 36%, #2f5c3a 66%, #143021 100%);
        overflow: hidden;
        box-shadow: var(--ua-shadow);
    }
    .ua-banner::after{
        content:"";
        position:absolute;
        inset:0;
        background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.06));
        pointer-events:none;
    }

    .ua-head{
        position: relative;
        margin-top: -88px;
        padding: 0 22px;
    }
    .ua-head-inner{
        width: min(1200px, 100%);
        margin: 0 auto;
        display:flex;
        gap: 16px;
        align-items: flex-end;
    }
    .ua-avatar{
        width: 106px;
        height: 106px;
        border-radius: 999px;
        background: #ffffff;
        border: 4px solid #ffffff;
        box-shadow: 0 0 0 4px rgba(74,124,89,.85), 0 18px 45px rgba(0,0,0,.18);
        display:grid;
        place-items:center;
        font-weight: 900;
        font-size: 28px;
        letter-spacing: .06em;
        color: #111;
        flex-shrink:0;
    }

    .ua-user{
        padding-bottom: 6px;
        min-width: 0;
        flex: 1;
    }
    .ua-user-top{
        display:flex;
        align-items:center;
        gap: 10px;
        flex-wrap: wrap;
    }
    /* Readable over dark green banner */
    .ua-name{
        margin: 0;
        font-size: clamp(26px, 4vw, 40px);
        letter-spacing: -.03em;
        line-height: 1.05;
        text-transform: none;
        color: #fafafa;
        text-shadow: 0 2px 14px rgba(0,0,0,.45), 0 1px 0 rgba(0,0,0,.2);
    }
    .ua-badge{
        display:inline-flex;
        align-items:center;
        height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        border: 1px solid rgba(255,255,255,.42);
        background: rgba(255,255,255,.18);
        backdrop-filter: blur(8px);
        color: rgba(255,255,255,.94);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .ua-tabs{
        width: min(1200px, 100%);
        margin: 18px auto 0;
        padding: 0 22px;
        max-width: 100%;
    }
    .ua-tabs-nav{
        display:flex;
        gap: 18px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--ua-line);
    }
    .ua-tab{
        position: relative;
        padding: 14px 2px;
        color: var(--ua-tab);
        text-decoration: none;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-size: 12px;
    }
    /* Long / short labels: desktop shows full text; narrow screens show compact (see @media) */
    .ua-tab__short{ display: none; }
    .ua-tab__long{ display: inline; }
    .ua-tab.active{
        color: #111;
    }
    .ua-tab.active::after{
        content:"";
        position:absolute;
        left:0;
        right:0;
        bottom:-1px;
        height: 3px;
        background: #111;
        border-radius: 999px;
    }

    .ua-content{
        width: min(1200px, 100%);
        margin: 18px auto 0;
        padding: 0 22px;
        max-width: 100%;
        min-width: 0;
    }

    .fs-account-main { min-width: 0; }

    .fs-account-card{
        border: 1px solid var(--ua-line);
        border-radius: var(--ua-radius);
        background: var(--ua-card);
        box-shadow: 0 12px 36px rgba(0,0,0,.06);
        padding: clamp(20px, 3vw, 34px);
        margin-bottom: 20px;
    }

    .fs-card-head {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        margin-bottom: 22px;
    }

    .fs-card-head h2 {
        margin: 0;
        font-size: clamp(20px, 3vw, 34px);
        line-height: 1;
        letter-spacing: -.035em;
        text-transform: uppercase;
    }

    .fs-card-head p,
    .fs-muted{
        margin: 8px 0 0;
        color: var(--ua-muted);
        font-size: 13px;
    }

    .fs-message{
        border-radius: 18px;
        padding: 14px 16px;
        margin-bottom: 16px;
        border: 1px solid var(--ua-line);
        background: #fff;
        font-size: 13px;
    }
    .fs-message.success { border-color: rgba(74,124,89,.35); color: #31553c; }
    .fs-message.error { border-color: rgba(170,43,43,.35); color: #8a2424; }

    .fs-info-grid,
    .fs-form-grid,
    .fs-reservation-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .fs-info-item,
    .fs-reservation-detail,
    .fs-notification{
        border: 1px solid var(--ua-line);
        border-radius: 18px;
        background: #fff;
        padding: 16px;
    }

    .fs-label{
        display: block;
        margin-bottom: 8px;
        color: rgba(16,16,16,.48);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .fs-value {
        font-size: 15px;
        font-weight: 800;
    }

    .fs-form-group {
        margin-bottom: 16px;
    }

    .fs-form-group.full {
        grid-column: 1 / -1;
    }

    .fs-form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .fs-input,
    .fs-textarea {
        width: 100%;
        border: 1px solid rgba(17,17,17,.18);
        border-radius: 14px;
        background: rgba(255,255,255,.84);
        color: #111;
        padding: 13px 14px;
        font: inherit;
        outline: none;
    }

    .fs-input:focus,
    .fs-textarea:focus {
        border-color: rgba(74,124,89,.58);
        box-shadow: 0 0 0 4px rgba(74,124,89,.10);
    }

    .fs-textarea {
        min-height: 110px;
        resize: vertical;
    }

    .fs-check {
        display: flex;
        align-items: center;
        gap: 12px;
        color: rgba(17,17,17,.72);
        font-size: 13px;
    }

    .fs-check input {
        width: 18px;
        height: 18px;
        accent-color: var(--fs-green);
    }

    .fs-btn,
    .fs-link-btn{
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        border: 1px solid #111;
        border-radius: 12px;
        background: #111;
        color: #fff;
        padding: 0 18px;
        text-decoration: none;
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        cursor: pointer;
    }

    .fs-btn:hover,
    .fs-link-btn:hover{
        background: var(--ua-accent);
        border-color: var(--ua-accent);
    }

    .fs-inline-link{
        color: var(--ua-accent);
        font-weight: 900;
        text-decoration: none;
        border-bottom: 1px solid rgba(74,124,89,.45);
        padding-bottom: 1px;
    }
    .fs-inline-link:hover{
        color: #111;
        border-bottom-color: rgba(17,17,17,.35);
    }

    .ua-alerts-grid{
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 18px;
        align-items: start;
    }
    @media (max-width: 980px){
        .ua-alerts-grid{ grid-template-columns: 1fr; }
    }
    .ua-alert-list{
        display: grid;
        gap: 12px;
        margin-top: 14px;
    }
    .ua-alert-item{
        border: 1px solid var(--ua-line);
        border-radius: 14px;
        background: rgba(255,255,255,.82);
        padding: 14px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }
    .ua-alert-item strong{ display:block; font-size: 13px; letter-spacing: -.01em; }
    .ua-alert-meta{ color: var(--ua-muted); font-size: 12px; margin-top: 3px; }
    .ua-alert-pill{
        display: inline-flex;
        align-items: center;
        height: 24px;
        padding: 0 10px;
        border-radius: 999px;
        border: 1px solid rgba(74,124,89,.35);
        background: rgba(74,124,89,.10);
        color: #123020;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .is-hidden{ display:none !important; }

    .fs-btn.secondary{
        background: #fff;
        color: #111;
        border-color: var(--ua-line);
    }

    .fs-btn.danger {
        background: #9f2f2f;
        border-color: #9f2f2f;
    }

    .fs-filter-row,
    .fs-actions-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .fs-filter-row {
        margin-bottom: 18px;
    }

    .fs-filter-row a {
        border: 1px solid var(--fs-line);
        border-radius: 999px;
        background: rgba(255,255,255,.62);
        color: rgba(17,17,17,.65);
        padding: 10px 13px;
        text-decoration: none;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .fs-filter-row a.active,
    .fs-filter-row a:hover {
        background: #111;
        color: #fff;
    }

    .fs-reservation-card {
        border: 1px solid var(--fs-line);
        border-radius: 24px;
        background: rgba(255,255,255,.62);
        padding: 18px;
        margin-bottom: 14px;
    }

    .fs-reservation-top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .fs-reservation-product {
        display: flex;
        gap: 14px;
        align-items: center;
        min-width: 0;
    }

    .fs-reservation-product img {
        width: 74px;
        height: 74px;
        border-radius: 18px;
        object-fit: cover;
        background: #ebe8df;
    }

    .fs-reservation-product h3 {
        margin: 0;
        font-size: 18px;
        letter-spacing: -.02em;
    }

    .fs-badge {
        display: inline-flex;
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .09em;
        text-transform: uppercase;
        background: rgba(17,17,17,.08);
        color: rgba(17,17,17,.72);
        white-space: nowrap;
    }

    .fs-badge.pending { background: rgba(245,178,71,.28); color: #80510f; }
    .fs-badge.confirmed { background: rgba(74,124,89,.18); color: #31553c; }
    .fs-badge.paid { background: rgba(28,140,157,.18); color: #156070; }
    .fs-badge.completed { background: rgba(17,17,17,.12); color: #111; }
    .fs-badge.cancelled { background: rgba(160,55,55,.15); color: #8a2424; }

    .fs-empty {
        border: 1px dashed rgba(17,17,17,.25);
        border-radius: 22px;
        padding: 42px 20px;
        text-align: center;
        color: var(--fs-muted);
        background: rgba(255,255,255,.45);
    }

    .fs-notification {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        margin-bottom: 12px;
    }

    .fs-notification.unread {
        border-color: rgba(74,124,89,.45);
        background: rgba(163,177,138,.17);
    }

    .fs-notification h3 {
        margin: 0 0 8px;
        font-size: 16px;
    }

    .fs-notification p {
        margin: 0;
        color: var(--fs-muted);
        font-size: 13px;
    }

    .fs-danger-zone {
        border-color: rgba(159,47,47,.26);
        background: rgba(159,47,47,.06);
    }

    /* Dashboard-style password form rows */
    .ua-form-rows{
        border: 1px solid var(--ua-line);
        border-radius: var(--ua-radius);
        overflow: hidden;
        background: #fff;
    }
    .ua-form-row{
        display:grid;
        grid-template-columns: 220px 1fr;
        gap: 16px;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 1px solid var(--ua-line);
    }
    .ua-form-row:last-child{ border-bottom: 0; }
    .ua-form-row .ua-row-label{
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(16,16,16,.78);
    }
    .ua-form-row .fs-input{ margin: 0; }
    .ua-form-actions{
        display:flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
    }

    @media (max-width: 900px){
        /* Keep top offset for fixed header (do not collapse to 0) */
        .ua-shell{ padding: 74px 14px 36px; }
        .ua-head{ padding: 0 14px; }
        .ua-tabs{ padding: 0 14px; }
        .ua-content{ padding: 0 14px; }
        .ua-form-row{ grid-template-columns: 1fr; }
        .fs-info-grid{ grid-template-columns: 1fr; }
        .fs-form-grid{ grid-template-columns: 1fr; }
        .fs-form-group.full{ grid-column: 1; }
    }

    @media (max-width: 720px){
        /* Compact tab wording on phones / small tablets */
        .ua-tab__short{ display: inline; }
        .ua-tab__long{ display: none; }
    }

    @media (max-width: 600px){
        /* Tabs: full-bleed horizontal strip so scroll isn’t clipped by shell padding */
        .ua-tabs{
            width: auto;
            max-width: none;
            margin-left: -14px;
            margin-right: -14px;
            margin-top: 14px;
            padding-left: 14px;
            padding-right: 14px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-padding-inline: 14px;
            scroll-snap-type: x proximity;
            overscroll-behavior-x: contain;
            touch-action: pan-x pan-y;
            box-shadow: inset -10px 0 10px -8px rgba(0,0,0,.06);
        }
        .ua-tabs-nav{
            flex-wrap: nowrap;
            gap: 6px;
            border-bottom: 1px solid rgba(0,0,0,.10);
            padding: 0 2px 0 0;
        }
        .ua-tab{
            flex: 0 0 auto;
            scroll-snap-align: start;
            padding: 14px 14px;
            font-size: 12px;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            -webkit-tap-highlight-color: transparent;
            border-radius: 12px 12px 0 0;
        }
        .ua-tab.active{
            background: rgba(74,124,89,.08);
        }
        /* Banner shorter on mobile */
        .ua-banner{ min-height: 118px; }
        /* Head: stack avatar + name nicely — pull up more into banner */
        .ua-head{ margin-top: -58px; }
        .ua-head-inner{ flex-direction: row; gap: 12px; align-items: flex-end; }
        .ua-avatar{ width: 78px; height: 78px; font-size: 20px; }
        .ua-name{ font-size: clamp(20px, 5.5vw, 28px); }
        /* Card padding */
        .fs-account-card{ padding: clamp(16px, 4vw, 24px); }
        /* Stat / alert row */
        .ua-alert-item{ flex-direction: column; align-items: flex-start; gap: 10px; }
        .ua-alert-item .fs-btn{ width: 100%; justify-content: center; }

        /* Section title + subtitle: less vertical dead space on phone */
        .fs-card-head{ margin-bottom: 14px; gap: 12px; }
        .fs-card-head h2{ font-size: clamp(17px, 5vw, 24px); line-height: 1.08; }
        .fs-card-head p{ margin-top: 4px; font-size: 12px; line-height: 1.4; }

        /* Status filters: full-bleed scroll row + tap targets */
        .fs-filter-row{
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            touch-action: pan-x pan-y;
            scroll-snap-type: x proximity;
            gap: 8px;
            margin-left: -16px;
            margin-right: -16px;
            margin-bottom: 12px;
            padding: 4px 16px 10px;
            scrollbar-width: thin;
            box-shadow: inset -10px 0 10px -8px rgba(0,0,0,.05);
        }
        .fs-filter-row a{
            flex: 0 0 auto;
            scroll-snap-align: start;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            padding: 12px 14px;
            font-size: 11px;
            -webkit-tap-highlight-color: transparent;
        }

        /* Reservations: reduce boxiness + better mobile grouping */
        .fs-reservation-card{
            padding: 14px;
            border-radius: 18px;
        }
        .fs-reservation-top{
            margin-bottom: 12px;
            gap: 12px;
            flex-direction: column;
            align-items: stretch;
        }
        .fs-reservation-top .fs-badge{
            align-self: flex-start;
            padding: 8px 10px;
            font-size: 10px;
        }
        .fs-reservation-product img{
            width: 64px;
            height: 64px;
            border-radius: 16px;
        }
        .fs-reservation-product h3{
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .fs-reservation-grid{
            grid-template-columns: 1fr 1fr;
            gap: 10px 14px;
        }
        .fs-reservation-grid .fs-reservation-detail{
            border: none;
            background: transparent;
            padding: 0;
        }
        .fs-reservation-grid .fs-label{
            margin-bottom: 6px;
            font-size: 10px;
            letter-spacing: .10em;
            color: rgba(16,16,16,.42);
        }
        .fs-reservation-grid .fs-muted{
            margin-top: 4px;
            font-size: 12px;
        }

        /* Force the exact 2-col grouping requested */
        .fs-reservation-detail--qty{ grid-column: 1; }
        .fs-reservation-detail--pay{ grid-column: 2; }
        .fs-reservation-detail--pdate{ grid-column: 1; }
        .fs-reservation-detail--ptime{ grid-column: 2; }
        .fs-reservation-detail--farmer{ grid-column: 1 / -1; }
        .fs-reservation-detail--reserved{ grid-column: 1 / -1; }

        /* Notes + actions closer, easier to tap */
        .fs-reservation-card > .fs-reservation-detail{ margin-top: 10px !important; }
        .fs-actions-row{
            margin-top: 12px !important;
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
        }
        .fs-actions-row form{
            width: 100%;
            display: block;
        }
        .fs-actions-row .fs-btn{
            width: 100%;
            justify-content: center;
            min-height: 48px;
            font-size: 13px;
        }

        /* Profile / password primary actions: full-width stack */
        .ua-form-actions{
            flex-direction: column;
            align-items: stretch;
        }
        .ua-form-actions .fs-btn,
        .ua-form-actions a.fs-btn{
            width: 100%;
            justify-content: center;
        }

        /* Confirm modal: easier thumb reach */
        .ua-cfm__actions{
            flex-direction: column-reverse;
            align-items: stretch;
        }
        .ua-cfm__actions .ua-cfm__btn{
            width: 100%;
            justify-content: center;
        }

        .fs-notification{
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 400px){
        .ua-shell{ padding: 0 10px 30px; }
        .ua-tabs{
            margin-left: -10px;
            margin-right: -10px;
            padding-left: 10px;
            padding-right: 10px;
            scroll-padding-inline: 10px;
        }
        .ua-tabs-nav{ gap: 4px; }
        .ua-tab{ padding: 12px 10px; font-size: 11px; min-height: 46px; }
        .fs-filter-row{
            margin-left: -12px;
            margin-right: -12px;
            padding-left: 12px;
            padding-right: 12px;
        }
        .fs-filter-row a{ padding: 10px 12px; font-size: 10px; }

        /* Very narrow: single column detail stack (easier to read than squeezed 2-col) */
        .fs-reservation-grid{
            grid-template-columns: 1fr;
        }
        .fs-reservation-detail--qty,
        .fs-reservation-detail--pay,
        .fs-reservation-detail--pdate,
        .fs-reservation-detail--ptime,
        .fs-reservation-detail--farmer,
        .fs-reservation-detail--reserved{
            grid-column: 1;
        }
    }

    /* In-app confirm modal (replaces browser confirm()) */
    .ua-cfm{
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        z-index: 9999;
    }
    .ua-cfm[aria-hidden="false"]{ display: flex; }
    .ua-cfm__backdrop{
        position:absolute;
        inset:0;
        background: rgba(0,0,0,.48);
        backdrop-filter: blur(6px);
    }
    .ua-cfm__panel{
        position: relative;
        width: min(520px, 100%);
        border: 1px solid rgba(255,255,255,.18);
        border-radius: 18px;
        background: #141414;
        color: #fff;
        box-shadow: 0 28px 90px rgba(0,0,0,.45);
        padding: 16px;
        transform: translateY(10px);
        opacity: 0;
        transition: transform 180ms ease, opacity 180ms ease;
    }
    .ua-cfm[aria-hidden="false"] .ua-cfm__panel{
        transform: translateY(0);
        opacity: 1;
    }
    .ua-cfm__title{
        margin: 2px 2px 6px;
        font-size: 16px;
        font-weight: 900;
        letter-spacing: -.01em;
    }
    .ua-cfm__msg{
        margin: 0 2px 14px;
        color: rgba(255,255,255,.78);
        font-size: 13px;
        line-height: 1.45;
    }
    .ua-cfm__actions{
        display:flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 12px;
    }
    .ua-cfm__btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height: 44px;
        padding: 0 16px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.16);
        background: rgba(255,255,255,.08);
        color: #fff;
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        cursor: pointer;
    }
    .ua-cfm__btn:hover{ background: rgba(255,255,255,.14); }
    .ua-cfm__btn--danger{
        background: #9f2f2f;
        border-color: #9f2f2f;
    }
    .ua-cfm__btn--danger:hover{
        background: #b23b3b;
        border-color: #b23b3b;
    }
</style>

<main class="ua-shell">
    <section class="ua-banner" aria-hidden="true"></section>

    <header class="ua-head" aria-label="Account header">
        <div class="ua-head-inner">
            <div class="ua-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
            <div class="ua-user">
                <div class="ua-user-top">
                    <h1 class="ua-name"><?php echo htmlspecialchars($display_name); ?></h1>
                    <span class="ua-badge"><?php echo htmlspecialchars(ucfirst($user_role)); ?></span>
                </div>
            </div>
        </div>
    </header>

    <section class="ua-tabs" aria-label="Account tabs">
        <nav class="ua-tabs-nav">
            <a class="ua-tab <?php echo $current_section === 'profile' ? 'active' : ''; ?>" href="user-account.php?section=profile"><span class="ua-tab__long">Profile</span><span class="ua-tab__short">Profile</span></a>
            <a class="ua-tab <?php echo $current_section === 'password' ? 'active' : ''; ?>" href="user-account.php?section=password"><span class="ua-tab__long">Password</span><span class="ua-tab__short">Password</span></a>
            <a class="ua-tab <?php echo $current_section === 'reservations' ? 'active' : ''; ?>" href="user-account.php?section=reservations" title="My Reservations"><span class="ua-tab__long">My Reservations</span><span class="ua-tab__short">Orders</span></a>
            <a class="ua-tab <?php echo $current_section === 'price_alerts' ? 'active' : ''; ?>" href="user-account.php?section=price_alerts" title="Price Alerts"><span class="ua-tab__long">Price Alerts</span><span class="ua-tab__short">Prices</span></a>
            <a class="ua-tab <?php echo $current_section === 'notifications' ? 'active' : ''; ?>" href="user-account.php?section=notifications" title="Notifications"><span class="ua-tab__long">Notifications</span><span class="ua-tab__short">Inbox</span></a>
            <a class="ua-tab <?php echo $current_section === 'delete' ? 'active' : ''; ?>" href="user-account.php?section=delete"><span class="ua-tab__long">Delete</span><span class="ua-tab__short">Delete</span></a>
        </nav>
    </section>

    <section class="ua-content">
        <div class="fs-account-main">
            <?php if ($login_success_message): ?>
                <div class="fs-message success"><?php echo htmlspecialchars($login_success_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="fs-message success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="fs-message error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($current_section === 'profile'): ?>
                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>Profile</h2>
                            <p>Keep your buyer information accurate for reservations and market pickup coordination.</p>
                        </div>
                    </div>

                    <div class="fs-info-grid">
                        <div class="fs-info-item"><span class="fs-label">Full Name</span><div class="fs-value"><?php echo htmlspecialchars($user_full_name ?: $username); ?></div></div>
                        <div class="fs-info-item"><span class="fs-label">Email</span><div class="fs-value"><?php echo htmlspecialchars($user_email); ?></div></div>
                        <div class="fs-info-item"><span class="fs-label">Phone</span><div class="fs-value">
                            <?php if (!empty($user_phone)): ?>
                                <?php echo htmlspecialchars($user_phone); ?>
                            <?php else: ?>
                                <a href="#phone_number" class="fs-inline-link">Add phone number</a>
                            <?php endif; ?>
                        </div></div>
                        <div class="fs-info-item"><span class="fs-label">Account Type</span><div class="fs-value"><?php echo htmlspecialchars(ucfirst($user_role)); ?></div></div>
                        <div class="fs-info-item"><span class="fs-label">Member Since</span><div class="fs-value"><?php echo $user_created_at ? date('M d, Y', strtotime($user_created_at)) : 'Not available'; ?></div></div>
                        <?php if (!empty($user_last_login)): ?>
                            <div class="fs-info-item"><span class="fs-label">Last Login</span><div class="fs-value"><?php echo date('M d, Y g:i A', strtotime($user_last_login)); ?></div></div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>Update Profile</h2>
                            <p>These fields are used in your reservations and notifications.</p>
                        </div>
                    </div>
                    <form method="POST" action="user-account.php?section=profile">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="fs-form-grid">
                            <div class="fs-form-group">
                                <label for="full_name">Full Name</label>
                                <input class="fs-input" type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user_full_name); ?>">
                            </div>
                            <div class="fs-form-group">
                                <label for="phone_number">Phone Number</label>
                                <input class="fs-input" type="tel" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user_phone); ?>" placeholder="+63 9XX XXX XXXX">
                            </div>
                            <div class="fs-form-group full">
                                <label for="address">Address</label>
                                <textarea class="fs-textarea" id="address" name="address" rows="4" placeholder="Enter your address"><?php echo htmlspecialchars($user_address); ?></textarea>
                            </div>
                            <div class="fs-form-group full">
                                <label class="fs-check">
                                    <input type="checkbox" name="email_notifications" value="1" <?php echo $email_notifications_enabled ? 'checked' : ''; ?>>
                                    Enable email notifications for updates and alerts
                                </label>
                            </div>
                        </div>
                        <button class="fs-btn" type="submit">Update Profile</button>
                    </form>
                </article>

            <?php elseif ($current_section === 'price_alerts'): ?>
                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>Price Alerts</h2>
                            <p>Alerts you added from product cards (notification bell).</p>
                        </div>
                    </div>
                    <div class="ua-alert-list">
                        <?php if (empty($price_alerts)): ?>
                            <div class="ua-alert-item">
                                <div>
                                    <strong>No alerts yet</strong>
                                    <div class="ua-alert-meta">Go to Products and click the bell icon on a product to add an alert.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($price_alerts as $a): ?>
                                <?php
                                    $atype = (string)($a['alert_type'] ?? 'change');
                                    $pill = $atype === 'change' ? 'CHANGE' : strtoupper($atype) . ' ₱' . number_format((float)($a['target_price'] ?? 0), 2);
                                ?>
                                <div class="ua-alert-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars((string)($a['name'] ?? 'Product')); ?></strong>
                                        <div class="ua-alert-meta">
                                            <?php echo htmlspecialchars((string)($a['market_name'] ?? '')); ?>
                                            <?php if (!empty($a['unit'])): ?> · <?php echo htmlspecialchars((string)$a['unit']); ?><?php endif; ?>
                                        </div>
                                        <div style="margin-top:8px;">
                                            <span class="ua-alert-pill"><?php echo htmlspecialchars($pill); ?></span>
                                        </div>
                                    </div>
                                    <form method="POST" action="user-account.php?section=price_alerts">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCSRFToken()); ?>">
                                        <input type="hidden" name="action" value="remove_price_alert">
                                        <input type="hidden" name="alert_id" value="<?php echo (int)($a['id'] ?? 0); ?>">
                                        <button class="fs-btn secondary" type="submit">Remove</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

            <?php elseif ($current_section === 'password'): ?>
                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>Password</h2>
                            <p>Change your password to keep your FarmScout account secure.</p>
                        </div>
                    </div>
                    <form method="POST" action="user-account.php?section=password">
                        <input type="hidden" name="action" value="change_password">
                        <div class="ua-form-rows" role="group" aria-label="Password form">
                            <div class="ua-form-row">
                                <div class="ua-row-label">Current Password</div>
                                <input class="fs-input" type="password" id="current_password" name="current_password" required>
                            </div>
                            <div class="ua-form-row">
                                <div class="ua-row-label">New Password</div>
                                <input class="fs-input" type="password" id="new_password" name="new_password" required minlength="8">
                            </div>
                            <div class="ua-form-row">
                                <div class="ua-row-label">Confirm New Password</div>
                                <input class="fs-input" type="password" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                        </div>
                        <div class="ua-form-actions">
                            <a class="fs-btn secondary" href="user-account.php?section=profile">Cancel</a>
                            <button class="fs-btn" type="submit">Update Password</button>
                        </div>
                    </form>
                </article>

            <?php elseif ($current_section === 'reservations'): ?>
                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>My Reservations</h2>
                            <p>Track pickup requests, payment status, and farmer responses.</p>
                        </div>
                    </div>

                    <?php $filter = $_GET['status'] ?? ''; ?>
                    <div class="fs-filter-row">
                        <a href="user-account.php?section=reservations" class="<?php echo $filter === '' ? 'active' : ''; ?>">All</a>
                        <a href="user-account.php?section=reservations&status=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="user-account.php?section=reservations&status=confirmed" class="<?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                        <a href="user-account.php?section=reservations&status=paid" class="<?php echo $filter === 'paid' ? 'active' : ''; ?>">Paid</a>
                        <a href="user-account.php?section=reservations&status=completed" class="<?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                        <a href="user-account.php?section=reservations&status=cancelled" class="<?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                        <?php if ($has_archive): ?>
                            <a href="user-account.php?section=reservations&status=archived" class="<?php echo $filter === 'archived' ? 'active' : ''; ?>">Archived</a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($reservations)): ?>
                        <div class="fs-empty">No reservations found.</div>
                    <?php else: ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php
                                $status = (string)($reservation['status'] ?? 'pending');
                                $is_archived = isset($reservation['is_archived']) && (int)$reservation['is_archived'] === 1;
                            ?>
                            <div class="fs-reservation-card">
                                <div class="fs-reservation-top">
                                    <div class="fs-reservation-product">
                                        <?php
                                            $lines = !empty($reservation['line_items']) ? $reservation['line_items'] : [[
                                                'product_name' => $reservation['product_name'] ?? 'Product',
                                                'product_image' => $reservation['product_image'] ?? '',
                                                'quantity' => $reservation['quantity'] ?? '',
                                                'unit' => $reservation['unit'] ?? '',
                                            ]];
                                            $headImg = $lines[0]['product_image'] ?? ($reservation['product_image'] ?? '');
                                            $headName = count($lines) > 1
                                                ? (count($lines) . ' items')
                                                : (string)($lines[0]['product_name'] ?? 'Product');
                                        ?>
                                        <img src="<?php echo htmlspecialchars(assetUrl($headImg ?: 'assets/images/placeholder-product.svg')); ?>" alt="">
                                        <div>
                                            <h3><?php echo htmlspecialchars($headName); ?></h3>
                                            <p class="fs-muted"><?php echo htmlspecialchars($reservation['market_name'] ?? 'Market'); ?></p>
                                            <?php if (!empty($reservation['public_ref']) || !empty($reservation['chat_public_ref'])): ?>
                                                <p class="fs-muted" style="margin-top:6px;font-size:12px;">
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
                                                <ul class="fs-res-line-items" style="margin:10px 0 0;padding-left:1.1rem;font-size:13px;line-height:1.45;">
                                                    <?php foreach ($lines as $ln): ?>
                                                        <li><?php echo htmlspecialchars(trim(($ln['quantity'] ?? '') . ' ' . ($ln['unit'] ?? '') . ' — ' . ($ln['product_name'] ?? ''))); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="fs-badge <?php echo account_status_class($status); ?>"><?php echo account_status_label($status); ?></span>
                                </div>

                                <div class="fs-reservation-grid">
                                    <div class="fs-reservation-detail fs-reservation-detail--qty"><span class="fs-label">Quantity</span><?php echo count($lines) > 1 ? htmlspecialchars(count($lines) . ' product lines') : htmlspecialchars(($reservation['quantity'] ?? '') . ' ' . ($reservation['unit'] ?? '')); ?></div>
                                    <div class="fs-reservation-detail fs-reservation-detail--farmer"><span class="fs-label">Farmer</span><?php echo htmlspecialchars($reservation['farmer_name'] ?? ''); ?></div>
                                    <div class="fs-reservation-detail fs-reservation-detail--pdate"><span class="fs-label">Pickup Date</span><?php echo !empty($reservation['preferred_pickup_date']) ? date('M d, Y', strtotime($reservation['preferred_pickup_date'])) : 'Not set'; ?></div>
                                    <div class="fs-reservation-detail fs-reservation-detail--ptime"><span class="fs-label">Pickup Time</span><?php echo !empty($reservation['preferred_pickup_time']) ? date('g:i A', strtotime($reservation['preferred_pickup_time'])) : 'Not set'; ?></div>
                                    <div class="fs-reservation-detail fs-reservation-detail--reserved"><span class="fs-label">Reserved On</span><?php echo !empty($reservation['created_at']) ? date('M d, Y g:i A', strtotime($reservation['created_at'])) : 'Not available'; ?></div>
                                    <div class="fs-reservation-detail fs-reservation-detail--pay"><span class="fs-label">Payment</span>
                                        <?php echo !empty($reservation['payment_method']) ? strtoupper(htmlspecialchars($reservation['payment_method'])) : 'Not selected'; ?>
                                        <?php if (!empty($reservation['gcash_reference'])): ?>
                                            <div class="fs-muted">Ref: <?php echo htmlspecialchars($reservation['gcash_reference']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($reservation['notes'])): ?>
                                    <div class="fs-reservation-detail" style="margin-top:14px;"><span class="fs-label">Your Notes</span><?php echo htmlspecialchars($reservation['notes']); ?></div>
                                <?php endif; ?>

                                <div class="fs-actions-row" style="margin-top:16px;">
                                    <?php if (in_array($status, ['pending', 'confirmed'], true)): ?>
                                        <form
                                            method="POST"
                                            data-ua-confirm
                                            data-ua-confirm-title="Cancel reservation"
                                            data-ua-confirm-msg="Cancel this reservation? This action cannot be undone."
                                        >
                                            <input type="hidden" name="action" value="cancel_reservation">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button class="fs-btn danger" type="submit">Cancel Reservation</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($has_archive): ?>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="<?php echo $is_archived ? 'unarchive_reservation' : 'archive_reservation'; ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                            <button class="fs-btn secondary" type="submit"><?php echo $is_archived ? 'Unarchive' : 'Archive'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>

            <?php elseif ($current_section === 'notifications'): ?>
                <article class="fs-account-card">
                    <div class="fs-card-head">
                        <div>
                            <h2>Notifications</h2>
                            <p>View reservation updates, price alerts, and system messages.</p>
                        </div>
                        <?php if ($unread_notifications > 0): ?>
                            <span class="fs-badge pending"><?php echo $unread_notifications; ?> unread</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="fs-empty">No notifications yet.</div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php $is_read = (bool)($notification['is_read'] ?? false); ?>
                            <div class="fs-notification <?php echo $is_read ? '' : 'unread'; ?>">
                                <div>
                                    <h3><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></h3>
                                    <p><?php echo htmlspecialchars($notification['message'] ?? ''); ?></p>
                                    <p class="fs-muted" style="margin-top:8px;"><?php echo !empty($notification['created_at']) ? getTimeAgo(strtotime($notification['created_at'])) : ''; ?></p>
                                    <?php if (!empty($notification['link'])): ?>
                                        <a class="fs-link-btn" style="margin-top:12px;" href="<?php echo htmlspecialchars($notification['link']); ?>">Open</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$is_read): ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="mark_notification_read">
                                        <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <button class="fs-btn secondary" type="submit">Mark Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </article>

            <?php elseif ($current_section === 'delete'): ?>
                <article class="fs-account-card fs-danger-zone">
                    <div class="fs-card-head">
                        <div>
                            <h2>Delete Account</h2>
                            <p>This permanently removes your account. Confirm your username and password to continue.</p>
                        </div>
                    </div>
                    <form
                        method="POST"
                        action="user-account.php?section=delete"
                        data-ua-confirm
                        data-ua-confirm-title="Delete account"
                        data-ua-confirm-msg="This permanently deletes your account. Continue?"
                        data-ua-confirm-danger="true"
                    >
                        <input type="hidden" name="action" value="delete_account">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <div class="fs-form-grid">
                            <div class="fs-form-group">
                                <label for="confirm_username">Type your username</label>
                                <input class="fs-input" type="text" id="confirm_username" name="confirm_username" placeholder="<?php echo htmlspecialchars($username); ?>" required>
                            </div>
                            <div class="fs-form-group">
                                <label for="confirm_password">Password</label>
                                <input class="fs-input" type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        <button class="fs-btn danger" type="submit">Delete Account</button>
                    </form>
                </article>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Reusable confirm modal (replaces window.confirm) -->
<div class="ua-cfm" id="uaConfirmModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="uaConfirmTitle">
    <div class="ua-cfm__backdrop" data-ua-cfm-close></div>
    <div class="ua-cfm__panel" role="document">
        <div class="ua-cfm__title" id="uaConfirmTitle">Confirm</div>
        <div class="ua-cfm__msg" id="uaConfirmMsg">Are you sure?</div>
        <div class="ua-cfm__actions">
            <button type="button" class="ua-cfm__btn" data-ua-cfm-cancel>Cancel</button>
            <button type="button" class="ua-cfm__btn ua-cfm__btn--danger" data-ua-cfm-ok>Confirm</button>
        </div>
    </div>
</div>

<script>
(() => {
  const modal = document.getElementById('uaConfirmModal');
  if (!modal) return;

  const titleEl = modal.querySelector('#uaConfirmTitle');
  const msgEl = modal.querySelector('#uaConfirmMsg');
  const btnCancel = modal.querySelector('[data-ua-cfm-cancel]');
  const btnOk = modal.querySelector('[data-ua-cfm-ok]');
  const clickClose = modal.querySelectorAll('[data-ua-cfm-close]');

  let pendingForm = null;
  let lastFocus = null;

  function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    pendingForm = null;
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function openModal({ title, msg, danger } = {}) {
    lastFocus = document.activeElement;
    if (titleEl) titleEl.textContent = title || 'Confirm';
    if (msgEl) msgEl.textContent = msg || 'Are you sure?';
    btnOk.classList.toggle('ua-cfm__btn--danger', !!danger);
    modal.setAttribute('aria-hidden', 'false');
    btnCancel && btnCancel.focus();
  }

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-ua-confirm')) return;

    e.preventDefault();
    pendingForm = form;
    openModal({
      title: form.getAttribute('data-ua-confirm-title') || 'Confirm',
      msg: form.getAttribute('data-ua-confirm-msg') || 'Are you sure?',
      danger: form.getAttribute('data-ua-confirm-danger') === 'true'
    });
  }, true);

  btnCancel?.addEventListener('click', closeModal);
  clickClose.forEach(el => el.addEventListener('click', closeModal));

  btnOk?.addEventListener('click', () => {
    const f = pendingForm;
    closeModal();
    if (f) f.submit();
  });

  document.addEventListener('keydown', (e) => {
    if (modal.getAttribute('aria-hidden') === 'true') return;
    if (e.key === 'Escape') {
      e.preventDefault();
      closeModal();
    }
  });
})();
</script>

<?php include 'includes/floating_chat.php'; ?>
</body>
</html>
