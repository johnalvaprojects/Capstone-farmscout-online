<?php
require_once 'includes/enhanced_functions.php';

if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. DTI access required.'));
    exit;
}

$dashboard_heading = getAdminDashboardLabel();
$dashboard_org = isSuperAdmin() ? 'Super Admin' : 'DTI (Department of Trade and Industry)';
$page_title = isSuperAdmin() ? 'Super Admin Dashboard - FarmScout Online' : 'DTI Dashboard - FarmScout Online';
$page_description = $dashboard_org . ' - System management and platform oversight';

$admin_id = $_SESSION['user_id'];
$conn = getDB();

// Check if columns exist, if not, set defaults
try {
    $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
    $hasApprovalStatus = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $hasApprovalStatus = false;
}

try {
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_status'");
    $hasVerificationStatus = $col_check->rowCount() > 0;
} catch (Exception $e) {
    $hasVerificationStatus = false;
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'approve_product':
            $product_id = intval($_POST['product_id'] ?? 0);
            if ($product_id > 0) {
                try {
                    // Get product info for logging
                    $product_stmt = $conn->prepare("SELECT product_name, farmer_id FROM market_products WHERE id = ?");
                    $product_stmt->execute([$product_id]);
                    $product_info = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($hasApprovalStatus) {
                        $stmt = $conn->prepare("UPDATE market_products SET approval_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                        $stmt->execute([$admin_id, $product_id]);
                    } else {
                        // If column doesn't exist, just mark as available
                        $stmt = $conn->prepare("UPDATE market_products SET is_available = 1 WHERE id = ?");
                        $stmt->execute([$product_id]);
                    }
                    
                    // Log admin action
                    if ($product_info && function_exists('logAdminAction')) {
                        logAdminAction($conn, $admin_id, $_SESSION['username'] ?? 'admin', 'product_approved', [
                            'product_id' => $product_id,
                            'product_name' => $product_info['product_name'],
                            'farmer_id' => $product_info['farmer_id']
                        ]);
                    }
                    
                    $message = 'Product approved successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error approving product: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'reject_product':
            $product_id = intval($_POST['product_id'] ?? 0);
            if ($product_id > 0) {
                try {
                    // Get product info for logging
                    $product_stmt = $conn->prepare("SELECT product_name, farmer_id FROM market_products WHERE id = ?");
                    $product_stmt->execute([$product_id]);
                    $product_info = $product_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($hasApprovalStatus) {
                        $stmt = $conn->prepare("UPDATE market_products SET approval_status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
                        $stmt->execute([$admin_id, $product_id]);
                    } else {
                        $stmt = $conn->prepare("UPDATE market_products SET is_available = 0 WHERE id = ?");
                        $stmt->execute([$product_id]);
                    }
                    
                    // Log admin action
                    if ($product_info && function_exists('logAdminAction')) {
                        logAdminAction($conn, $admin_id, $_SESSION['username'] ?? 'admin', 'product_rejected', [
                            'product_id' => $product_id,
                            'product_name' => $product_info['product_name'],
                            'farmer_id' => $product_info['farmer_id']
                        ]);
                    }
                    
                    $message = 'Product rejected successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error rejecting product: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'verify_farmer':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    if ($hasVerificationStatus) {
                        $stmt = $conn->prepare("UPDATE users SET verification_status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?");
                        $stmt->execute([$admin_id, $user_id]);
                        
                        // Get farmer's email and username to send notification
                        $farmer_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                        $farmer_stmt->execute([$user_id]);
                        $farmer = $farmer_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($farmer) {
                            // Send verification email
                            $email_sent = sendFarmerVerificationEmail($farmer['email'], $farmer['username'], 'verified');
                            if ($email_sent) {
                                error_log("Verification email sent successfully to {$farmer['email']} for farmer {$farmer['username']}");
                            } else {
                                error_log("Failed to send verification email to {$farmer['email']} for farmer {$farmer['username']}");
                            }
                        }
                        
                        // Log admin action
                        if (function_exists('logAdminAction')) {
                            logAdminAction($conn, $admin_id, $_SESSION['username'] ?? 'admin', 'farmer_verified', [
                                'user_id' => $user_id,
                                'username' => $farmer['username'],
                                'email' => $farmer['email']
                            ]);
                        }
                    }
                    $message = 'Farmer verified successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error verifying farmer: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'reject_farmer':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    if ($hasVerificationStatus) {
                        $stmt = $conn->prepare("UPDATE users SET verification_status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?");
                        $stmt->execute([$admin_id, $user_id]);
                        
                        // Get farmer's email and username to send notification
                        $farmer_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                        $farmer_stmt->execute([$user_id]);
                        $farmer = $farmer_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($farmer) {
                            // Send rejection email
                            $email_sent = sendFarmerVerificationEmail($farmer['email'], $farmer['username'], 'rejected');
                            if ($email_sent) {
                                error_log("Rejection email sent successfully to {$farmer['email']} for farmer {$farmer['username']}");
                            } else {
                                error_log("Failed to send rejection email to {$farmer['email']} for farmer {$farmer['username']}");
                            }
                            
                            // Log admin action
                            if (function_exists('logAdminAction')) {
                                logAdminAction($conn, $admin_id, $_SESSION['username'] ?? 'admin', 'farmer_rejected', [
                                    'user_id' => $user_id,
                                    'username' => $farmer['username'],
                                    'email' => $farmer['email']
                                ]);
                            }
                        }
                    }
                    $message = 'Farmer verification rejected!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error rejecting farmer: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'remove_price_alert':
            $alert_id = intval($_POST['alert_id'] ?? 0);
            if ($alert_id > 0) {
                try {
                    $stmt = $conn->prepare("DELETE FROM price_alerts WHERE id = ?");
                    $stmt->execute([$alert_id]);
                    $message = 'Price alert removed successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error removing price alert: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
            
        case 'delete_user':
            $user_id = intval($_POST['user_id'] ?? 0);
            if ($user_id > 0) {
                try {
                    // Prevent admin from deleting themselves
                    if ($user_id == $admin_id) {
                        $message = 'You cannot delete your own account!';
                        $message_type = 'error';
                    } else {
                        // Get user info before deletion for logging
                        $user_stmt = $conn->prepare("SELECT username, email, user_role FROM users WHERE id = ?");
                        $user_stmt->execute([$user_id]);
                        $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user_info) {
                            if ($user_info['user_role'] === 'super_admin' && !isSuperAdmin()) {
                                $message = 'Only the Super Admin can delete a Super Admin account.';
                                $message_type = 'error';
                                break;
                            }
                            // Log admin action BEFORE deletion
                            if (function_exists('logAdminAction')) {
                                logAdminAction($conn, $admin_id, $_SESSION['username'] ?? 'admin', 'user_deleted', [
                                    'user_id' => $user_id,
                                    'username' => $user_info['username'],
                                    'email' => $user_info['email'],
                                    'user_role' => $user_info['user_role']
                                ]);
                            }
                            
                            // Delete user (cascade will handle related data if foreign keys are set)
                            // Note: This will also delete their products, alerts, etc. due to CASCADE
                            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                            $delete_stmt->execute([$user_id]);
                            
                            $message = 'User "' . htmlspecialchars($user_info['username']) . '" deleted successfully!';
                            $message_type = 'success';
                            
                            error_log("Admin {$admin_id} deleted user {$user_id} ({$user_info['username']}, {$user_info['email']}, role: {$user_info['user_role']})");
                        } else {
                            $message = 'User not found!';
                            $message_type = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $message = 'Error deleting user: ' . $e->getMessage();
                    $message_type = 'error';
                    error_log("Error deleting user {$user_id}: " . $e->getMessage());
                }
            }
            break;
            
        case 'deactivate_price_alert':
            $alert_id = intval($_POST['alert_id'] ?? 0);
            if ($alert_id > 0) {
                try {
                    $stmt = $conn->prepare("UPDATE price_alerts SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$alert_id]);
                    $message = 'Price alert deactivated successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $message = 'Error deactivating price alert: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
            break;
    }
}

// Defensive fetches: keep dashboard usable even if optional tables are missing.
$pending_products = [];
$pending_farmers = [];
$markets = [];
$users = [];
$dti_admins = [];
$dti_candidates = [];
$price_alerts = [];
$filter_market_id = isset($_GET['market_id']) ? intval($_GET['market_id']) : null;
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
$filter_category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : null;
$price_monitoring_products = [];
$price_monitoring_total = 0;
$price_monitoring_normal = 0;
$price_monitoring_warning = 0;
$price_monitoring_critical = 0;
$price_monitoring_no_data = 0;
$stats = [
    'total_users' => 0,
    'total_consumers' => 0,
    'total_farmers' => 0,
    'total_markets' => 0,
    'total_products' => 0,
    'active_alerts' => 0,
    'pending_products' => 0,
    'pending_farmers' => 0,
];
$recent_users = [];
$user_growth_data = [];
$reservation_trends_data = [];
$top_products = [];
$top_farmers = [];

try {
    // Fetch pending products (if approval_status column exists)
    if ($hasApprovalStatus) {
        $pending_query = "SELECT mp.*, m.market_name, u.username as farmer_name, u.email as farmer_email
                          FROM market_products mp
                          JOIN markets m ON mp.market_id = m.id
                          JOIN users u ON mp.farmer_id = u.id
                          WHERE mp.approval_status = 'pending'
                          ORDER BY mp.created_at DESC
                          LIMIT 50";
        $pending_stmt = $conn->prepare($pending_query);
        $pending_stmt->execute();
        $pending_products = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch pending farmer verifications
    if ($hasVerificationStatus) {
        $pending_farmers_query = "SELECT id, username, email, full_name, user_role, created_at, verification_status
                                  FROM users
                                  WHERE user_role = 'farmer' AND verification_status = 'pending'
                                  ORDER BY created_at DESC
                                  LIMIT 50";
        $pending_farmers_stmt = $conn->prepare($pending_farmers_query);
        $pending_farmers_stmt->execute();
        $pending_farmers = $pending_farmers_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch all markets
    $markets_query = "SELECT m.*, 
                      COUNT(DISTINCT mp.id) as product_count,
                      COUNT(DISTINCT mf.farmer_id) as farmer_count
                      FROM markets m
                      LEFT JOIN market_products mp ON m.id = mp.market_id" . ($hasApprovalStatus ? " AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "") . "
                      LEFT JOIN market_farmers mf ON m.id = mf.market_id AND mf.approval_status = 'approved'
                      GROUP BY m.id
                      ORDER BY m.market_name ASC";
    $markets_stmt = $conn->prepare($markets_query);
    $markets_stmt->execute();
    $markets = $markets_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all users
    $users_query = "SELECT id, username, email, full_name, user_role, created_at, is_active" . ($hasVerificationStatus ? ", verification_status" : "") . "
                    FROM users 
                    ORDER BY created_at DESC 
                    LIMIT 50";
    $users_stmt = $conn->prepare($users_query);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch DTI admin users and promotion candidates (Super Admin only)
    if (isSuperAdmin()) {
        $dti_admins_query = "SELECT id, username, email, full_name, user_role, is_active, last_login, created_at
                             FROM users
                             WHERE user_role = 'admin'
                             ORDER BY created_at DESC
                             LIMIT 100";
        $dti_admins_stmt = $conn->prepare($dti_admins_query);
        $dti_admins_stmt->execute();
        $dti_admins = $dti_admins_stmt->fetchAll(PDO::FETCH_ASSOC);

        $dti_candidates_query = "SELECT id, username, email, full_name, user_role, is_active
                                 FROM users
                                 WHERE user_role NOT IN ('admin', 'super_admin')
                                 ORDER BY created_at DESC
                                 LIMIT 200";
        $dti_candidates_stmt = $conn->prepare($dti_candidates_query);
        $dti_candidates_stmt->execute();
        $dti_candidates = $dti_candidates_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch price alerts
    $price_alerts_query = "SELECT pa.*, 
                                  mp.product_name,
                                  mp.product_name as filipino_name,
                                  mp.product_name as english_name,
                                  mp.price as current_price
                           FROM price_alerts pa
                           LEFT JOIN market_products mp ON pa.product_id = mp.id
                           ORDER BY pa.created_at DESC
                           LIMIT 50";
    $price_alerts_stmt = $conn->prepare($price_alerts_query);
    $price_alerts_stmt->execute();
    $price_alerts = $price_alerts_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch price monitoring data
    $price_monitoring_products = getProductsWithPriceMonitoring($filter_market_id, $filter_status);
    if ($filter_category) {
        $price_monitoring_products = array_filter($price_monitoring_products, function($p) use ($filter_category) {
            return strtolower(trim($p['category'])) === strtolower(trim($filter_category));
        });
    }
    $price_monitoring_total = count($price_monitoring_products);
    $price_monitoring_normal = count(array_filter($price_monitoring_products, fn($p) => $p['monitoring']['status'] === 'normal'));
    $price_monitoring_warning = count(array_filter($price_monitoring_products, fn($p) => $p['monitoring']['status'] === 'warning'));
    $price_monitoring_critical = count(array_filter($price_monitoring_products, fn($p) => $p['monitoring']['status'] === 'critical'));
    $price_monitoring_no_data = count(array_filter($price_monitoring_products, fn($p) => $p['monitoring']['status'] === 'no_data'));
    usort($price_monitoring_products, function($a, $b) {
        $priority = ['critical' => 1, 'warning' => 2, 'normal' => 3, 'no_data' => 4];
        $a_priority = $priority[$a['monitoring']['status']] ?? 5;
        $b_priority = $priority[$b['monitoring']['status']] ?? 5;
        if ($a_priority !== $b_priority) {
            return $a_priority <=> $b_priority;
        }
        return $b['monitoring']['deviation_percent'] <=> $a['monitoring']['deviation_percent'];
    });

    // Get statistics
    $approved_products_condition = $hasApprovalStatus ? "AND (approval_status = 'approved' OR approval_status IS NULL)" : "";
    $stats_query = "SELECT 
                    (SELECT COUNT(*) FROM users) as total_users,
                    (SELECT COUNT(*) FROM users WHERE user_role = 'consumer') as total_consumers,
                    (SELECT COUNT(*) FROM users WHERE user_role = 'farmer') as total_farmers,
                    (SELECT COUNT(*) FROM markets WHERE status = 'active' OR status IS NULL) as total_markets,
                    (SELECT COUNT(*) FROM market_products WHERE is_available = 1 $approved_products_condition) as total_products,
                    (SELECT COUNT(*) FROM price_alerts WHERE is_active = 1) as active_alerts" .
                    ($hasApprovalStatus ? ",
                    (SELECT COUNT(*) FROM market_products WHERE approval_status = 'pending') as pending_products" : "") .
                    ($hasVerificationStatus ? ",
                    (SELECT COUNT(*) FROM users WHERE user_role = 'farmer' AND verification_status = 'pending') as pending_farmers" : "");
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent registrations (last 7 days)
    $recent_users_query = "SELECT id, username, email, full_name, user_role, created_at" . ($hasVerificationStatus ? ", verification_status" : "") . "
                           FROM users 
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY created_at DESC 
                           LIMIT 10";
    $recent_users_stmt = $conn->prepare($recent_users_query);
    $recent_users_stmt->execute();
    $recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analytics Data for Charts
    // User growth (last 30 days, daily)
    $user_growth_query = "SELECT DATE(created_at) as date, COUNT(*) as count
                          FROM users
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                          GROUP BY DATE(created_at)
                          ORDER BY date ASC";
    $user_growth_stmt = $conn->prepare($user_growth_query);
    $user_growth_stmt->execute();
    $user_growth_data = $user_growth_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reservation count over time (last 30 days, daily)
    $reservation_trends_query = "SELECT DATE(created_at) as date, COUNT(*) as count
                                 FROM reservations
                                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                 GROUP BY DATE(created_at)
                                 ORDER BY date ASC";
    $reservation_trends_stmt = $conn->prepare($reservation_trends_query);
    $reservation_trends_stmt->execute();
    $reservation_trends_data = $reservation_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most reserved products (top 10)
    $top_products_query = "SELECT mp.product_name, COUNT(r.id) as reservation_count
                           FROM market_products mp
                           LEFT JOIN reservations r ON mp.id = r.product_id
                           GROUP BY mp.id, mp.product_name
                           ORDER BY reservation_count DESC
                           LIMIT 10";
    $top_products_stmt = $conn->prepare($top_products_query);
    $top_products_stmt->execute();
    $top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most active farmers (by reservation count)
    $top_farmers_query = "SELECT u.full_name, u.username, COUNT(r.id) as reservation_count
                          FROM users u
                          INNER JOIN reservations r ON u.id = r.farmer_id
                          WHERE u.user_role = 'farmer'
                          GROUP BY u.id, u.full_name, u.username
                          ORDER BY reservation_count DESC
                          LIMIT 10";
    $top_farmers_stmt = $conn->prepare($top_farmers_query);
    $top_farmers_stmt->execute();
    $top_farmers = $top_farmers_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Admin dashboard data fetch failed: ' . $e->getMessage());
    if ($message === '') {
        $message = 'Dashboard loaded with limited data because some database tables are missing.';
        $message_type = 'error';
    }
}

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
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    :root {
        --bg-color: #ffffff;
        --text-color: #000000;
        --border-color: #000000;
        --box-bg: #ffffff;
    }
    
    body.admin-dashboard-page {
        font-family: 'VT323', monospace !important;
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        padding: 1rem !important;
        line-height: 1.3 !important;
        margin: 0 !important;
        min-height: 100vh;
        width: 100%;
        box-sizing: border-box;
    }

    .admin-dashboard-wrapper {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 1.5rem 2rem 3rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
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
    
    .slide-notification .notification-icon {
        width: 32px;
        height: 32px;
        object-fit: cover;
        flex-shrink: 0;
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
    .admin-dashboard-hero {
        text-align: center;
        margin-bottom: 0.5rem;
    }
    
    .admin-dashboard-hero h1 {
        font-size: clamp(1.5rem, 4vw, 2.5rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.25rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    .admin-dashboard-hero p {
        font-size: clamp(0.8rem, 1.2vw, 1rem) !important;
        letter-spacing: 0.05em !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
        opacity: 0.8;
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }
    
    .stat-card {
        border: 3px solid #000000;
        padding: 1rem;
        background-color: #ffffff;
        transition: all 0.3s ease;
        border-radius: 8px;
        box-shadow: 4px 4px 0 #000000;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: #000000;
        margin-bottom: 0.5rem;
        font-family: 'VT323', monospace;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #000000;
        font-family: 'VT323', monospace;
    }
    
    /* Cards */
    .admin-card {
        border: 3px solid #000000;
        padding: 1rem;
        background-color: #ffffff;
        transition: all 0.3s ease;
        border-radius: 8px;
        box-shadow: 4px 4px 0 #000000;
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
    
    /* View Toggle Buttons - Modern Segmented Control Style */
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
    
    /* View Sections */
    .view-section {
        display: none;
    }
    
    .view-section.active {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-family: 'VT323', monospace;
    }
    
    .data-table th {
        text-align: left;
        padding: 0.75rem;
        border-bottom: 2px solid #000000;
        font-weight: bold;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .data-table td {
        padding: 0.75rem;
        border-bottom: 1px solid #000000;
        font-size: 0.9rem;
    }
    
    .data-table tr:hover {
        background-color: #f5f5f5;
    }
    
    /* Buttons */
    .admin-btn {
        font-family: 'VT323', monospace !important;
        font-size: 0.9rem;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.5rem 1rem;
        border: 2px solid var(--border-color);
        background-color: var(--text-color);
        color: var(--bg-color);
        cursor: pointer;
        transition: all 0.3s ease 0.1s;
        text-decoration: none;
        display: inline-block;
    }
    
    .admin-btn:hover {
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: all 0.3s ease 0s;
    }
    
    .admin-btn-secondary {
        background-color: var(--bg-color);
        color: var(--text-color);
    }
    
    .admin-btn-secondary:hover {
        background-color: var(--text-color);
        color: var(--bg-color);
    }
    
    .admin-btn-success {
        background-color: #ffffff;
        color: #22c55e;
        border-color: #22c55e;
    }
    
    .admin-btn-success:hover {
        background-color: #22c55e;
        color: #ffffff;
    }
    
    .admin-btn-danger {
        background-color: #ffffff;
        color: #dc2626;
        border-color: #dc2626;
    }
    
    .admin-btn-danger:hover {
        background-color: #dc2626;
        color: #ffffff;
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
    
    .status-badge.approved {
        background-color: #22c55e;
        color: #000000;
    }
    
    .status-badge.rejected {
        background-color: #dc2626;
        color: #ffffff;
    }
    
    .status-badge.verified {
        background-color: #22c55e;
        color: #000000;
    }
    
    /* Product/User Cards */
    .item-card {
        border: 2px solid #000000;
        padding: 1rem;
        margin-bottom: 0.75rem;
        background-color: #ffffff;
    }
    
    .item-card-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 0.5rem;
    }
    
    .item-card-title {
        font-size: 1.1rem;
        font-weight: bold;
        font-family: 'VT323', monospace;
    }
    
    .item-card-meta {
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 0.5rem;
    }
    
    .item-card-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.75rem;
        flex-wrap: wrap;
    }
    
    /* Custom Confirmation Modal */
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
        max-width: 400px;
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
</style>

<body class="admin-dashboard-page">
<div class="admin-dashboard-wrapper">

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
    <div class="admin-dashboard-hero">
        <h1><?php echo htmlspecialchars($dashboard_heading); ?></h1>
        <p><?php echo htmlspecialchars($dashboard_org); ?> - Platform management and oversight</p>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Consumers</div>
            <div class="stat-value"><?php echo number_format($stats['total_consumers'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Farmers</div>
            <div class="stat-value"><?php echo number_format($stats['total_farmers'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Markets</div>
            <div class="stat-value"><?php echo number_format($stats['total_markets'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Products</div>
            <div class="stat-value"><?php echo number_format($stats['total_products'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Alerts</div>
            <div class="stat-value"><?php echo number_format($stats['active_alerts'] ?? 0); ?></div>
        </div>
        <?php if ($hasApprovalStatus && isset($stats['pending_products'])): ?>
        <div class="stat-card" style="border-color: #fbbf24;">
            <div class="stat-label">Pending Products</div>
            <div class="stat-value" style="color: #fbbf24;"><?php echo number_format($stats['pending_products']); ?></div>
        </div>
        <?php endif; ?>
        <?php if ($hasVerificationStatus && isset($stats['pending_farmers'])): ?>
        <div class="stat-card" style="border-color: #fbbf24;">
            <div class="stat-label">Pending Farmers</div>
            <div class="stat-value" style="color: #fbbf24;"><?php echo number_format($stats['pending_farmers']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- View Toggle -->
    <div class="view-toggle">
        <button class="view-toggle-btn active" onclick="showView('overview')">OVERVIEW</button>
        <?php if ($hasApprovalStatus): ?>
        <button class="view-toggle-btn" onclick="showView('products')">PENDING PRODUCTS (<?php echo count($pending_products); ?>)</button>
        <?php endif; ?>
        <?php if ($hasVerificationStatus): ?>
        <button class="view-toggle-btn" onclick="showView('farmers')">PENDING FARMERS (<?php echo count($pending_farmers); ?>)</button>
        <?php endif; ?>
        <button class="view-toggle-btn" onclick="showView('markets')">MARKETS</button>
        <button class="view-toggle-btn" onclick="showView('users')">USERS</button>
        <?php if (isSuperAdmin()): ?>
        <button class="view-toggle-btn" onclick="showView('dti-admins')">DTI ADMINS</button>
        <?php endif; ?>
        <button class="view-toggle-btn" onclick="showView('alerts')">PRICE ALERTS</button>
    </div>

    <!-- Overview Section -->
    <div id="overview-view" class="view-section active">
        
        <!-- Analytics Charts Section -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- User Growth Chart -->
            <div class="admin-card">
                <h2>USER GROWTH (Last 30 Days)</h2>
                <canvas id="userGrowthChart" style="max-height: 300px;"></canvas>
            </div>
            
            <!-- Reservation Trends Chart -->
            <div class="admin-card">
                <h2>RESERVATION TRENDS (Last 30 Days)</h2>
                <canvas id="reservationTrendsChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
        
        <!-- Top Products and Farmers -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
            <!-- Most Reserved Products -->
            <div class="admin-card">
                <h2>MOST RESERVED PRODUCTS</h2>
                <?php if (empty($top_products)): ?>
                    <p>No reservations yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Reservations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo number_format($product['reservation_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Most Active Farmers -->
            <div class="admin-card">
                <h2>MOST ACTIVE FARMERS</h2>
                <?php if (empty($top_farmers)): ?>
                    <p>No farmer activity yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Reservations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_farmers as $farmer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($farmer['full_name'] ?: $farmer['username']); ?></td>
                                    <td><?php echo number_format($farmer['reservation_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="admin-card">
            <h2>RECENT REGISTRATIONS (Last 7 Days)</h2>
            <?php if (empty($recent_users)): ?>
                <p>No recent registrations.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <?php foreach ($recent_users as $user): ?>
                        <div class="item-card">
                            <div class="item-card-header">
                                <div>
                                    <div class="item-card-title"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                                    <div class="item-card-meta"><?php echo htmlspecialchars($user['email']); ?> • <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                </div>
                                <span class="status-badge"><?php echo htmlspecialchars(getUserRoleLabel($user['user_role'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Products Section -->
    <?php if ($hasApprovalStatus): ?>
    <div id="products-view" class="view-section">
        <div class="admin-card">
            <h2>PENDING PRODUCT APPROVAL</h2>
            <?php if (empty($pending_products)): ?>
                <p>No pending products. All products are approved!</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($pending_products as $product): ?>
                        <div class="item-card">
                            <div class="item-card-header">
                                <div>
                                    <div class="item-card-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="item-card-meta">
                                        Market: <?php echo htmlspecialchars($product['market_name']); ?><br>
                                        Farmer: <?php echo htmlspecialchars($product['farmer_name']); ?> (<?php echo htmlspecialchars($product['farmer_email']); ?>)<br>
                                        Category: <?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?><br>
                                        Created: <?php echo date('M d, Y H:i', strtotime($product['created_at'])); ?>
                                    </div>
                                    <?php if (!empty($product['product_description'])): ?>
                                        <p style="font-size: 0.85rem; margin-top: 0.5rem;"><?php echo htmlspecialchars($product['product_description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge pending">PENDING</span>
                            </div>
                            <?php if (!empty($product['product_image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="max-width: 200px; max-height: 200px; border: 2px solid #000000; margin-top: 0.5rem;">
                            <?php endif; ?>
                            <div class="item-card-actions">
                                <button type="button" class="admin-btn admin-btn-success" onclick="showConfirmModal('approve_product', <?php echo $product['id']; ?>, 'Approve this product?', 'approve')">APPROVE</button>
                                <button type="button" class="admin-btn admin-btn-danger" onclick="showConfirmModal('reject_product', <?php echo $product['id']; ?>, 'Reject this product?', 'reject')">REJECT</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Farmers Section -->
    <?php if ($hasVerificationStatus): ?>
    <div id="farmers-view" class="view-section">
        <div class="admin-card">
            <h2>PENDING FARMER VERIFICATION</h2>
            <?php if (empty($pending_farmers)): ?>
                <p>No pending farmer verifications. All farmers are verified!</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($pending_farmers as $farmer): ?>
                        <div class="item-card">
                            <div class="item-card-header">
                                <div>
                                    <div class="item-card-title"><?php echo htmlspecialchars($farmer['full_name'] ?? $farmer['username']); ?></div>
                                    <div class="item-card-meta">
                                        Email: <?php echo htmlspecialchars($farmer['email']); ?><br>
                                        Username: <?php echo htmlspecialchars($farmer['username']); ?><br>
                                        Registered: <?php echo date('M d, Y', strtotime($farmer['created_at'])); ?>
                                    </div>
                                </div>
                                <span class="status-badge pending">PENDING</span>
                            </div>
                            <div class="item-card-actions">
                                <button type="button" class="admin-btn admin-btn-success" onclick="showConfirmModal('verify_farmer', <?php echo $farmer['id']; ?>, 'Verify this farmer?', 'verify')">VERIFY</button>
                                <button type="button" class="admin-btn admin-btn-danger" onclick="showConfirmModal('reject_farmer', <?php echo $farmer['id']; ?>, 'Reject this farmer verification?', 'reject')">REJECT</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Markets Section -->
    <div id="markets-view" class="view-section">
        <div class="admin-card">
            <h2>MARKETS</h2>
            <?php if (empty($markets)): ?>
                <p>No markets found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Market Name</th>
                            <th>Location</th>
                            <th>Products</th>
                            <th>Farmers</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($markets as $market): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($market['market_name']); ?></td>
                                <td><?php echo htmlspecialchars($market['location'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($market['product_count'] ?? 0); ?></td>
                                <td><?php echo number_format($market['farmer_count'] ?? 0); ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($market['status'] ?? 'active') === 'active' ? 'approved' : 'pending'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($market['status'] ?? 'active')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users Section -->
    <div id="users-view" class="view-section">
        <div class="admin-card">
            <h2>USERS</h2>
            <?php if (empty($users)): ?>
                <p>No users found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <?php if ($hasVerificationStatus): ?>
                            <th>Verification</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="status-badge"><?php echo htmlspecialchars(getUserRoleLabel($user['user_role'])); ?></span>
                                </td>
                                <?php if ($hasVerificationStatus && $user['user_role'] === 'farmer'): ?>
                                <td>
                                    <?php if (isset($user['verification_status'])): ?>
                                        <span class="status-badge <?php echo $user['verification_status']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($user['verification_status'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <?php elseif ($hasVerificationStatus): ?>
                                <td>-</td>
                                <?php endif; ?>
                                <td>
                                    <span class="status-badge <?php echo $user['is_active'] ? 'approved' : 'rejected'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $admin_id): ?>
                                        <?php if ($user['user_role'] === 'super_admin' && !isSuperAdmin()): ?>
                                            <span style="color: #999; font-size: 14px;">Restricted</span>
                                        <?php else: ?>
                                            <button type="button" class="admin-btn admin-btn-danger" onclick="showConfirmModal('delete_user', <?php echo $user['id']; ?>, 'Delete user <?php echo htmlspecialchars(addslashes($user['username'])); ?>? This action cannot be undone.', 'delete')">DELETE</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 14px;">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- DTI Admins Section (Super Admin Only) -->
    <?php if (isSuperAdmin()): ?>
    <div id="dti-admins-view" class="view-section">
        <div class="admin-card">
            <h2>DTI ADMINS</h2>
            <?php if (empty($dti_admins)): ?>
                <p>No DTI admin accounts found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dti_admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $admin['is_active'] ? 'approved' : 'rejected'; ?>">
                                        <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $admin['last_login'] ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?>
                                </td>
                                <td style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($admin['is_active']): ?>
                                        <form method="POST" action="admin-actions.php">
                                            <input type="hidden" name="action" value="deactivate_user">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $admin['id']; ?>">
                                            <button type="submit" class="admin-btn admin-btn-secondary">DEACTIVATE</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="admin-actions.php">
                                            <input type="hidden" name="action" value="activate_user">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $admin['id']; ?>">
                                            <button type="submit" class="admin-btn admin-btn-success">ACTIVATE</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" action="admin-actions.php">
                                        <input type="hidden" name="action" value="demote_to_consumer">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $admin['id']; ?>">
                                        <button type="submit" class="admin-btn admin-btn-danger">DEMOTE</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="admin-card">
            <h2>CREATE NEW DTI ADMIN ACCOUNT</h2>
            <form id="createDtiAdminForm" method="POST" action="admin-actions.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Full Name *</label>
                    <input type="text" name="full_name" required style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;" placeholder="John Doe">
                </div>
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Username *</label>
                    <input type="text" name="username" required style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;" placeholder="johndoe">
                </div>
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Email *</label>
                    <input type="email" name="email" required style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;" placeholder="john@example.com">
                </div>
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Password * (min 8 chars)</label>
                    <input type="password" name="password" required minlength="8" style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;" placeholder="••••••••">
                </div>
                <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                    <input type="hidden" name="action" value="create_dti_admin">
                    <button type="submit" class="admin-btn admin-btn-success">CREATE DTI ADMIN</button>
                </div>
            </form>
        </div>

        <div class="admin-card">
            <h2>PROMOTE USER TO DTI ADMIN</h2>
            <?php if (empty($dti_candidates)): ?>
                <p>No eligible users available for promotion.</p>
            <?php else: ?>
                <form id="promoteUserForm" method="POST" action="admin-actions.php" style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: end;">
                    <div>
                        <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Select User</label>
                        <select name="user_id" required style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;">
                            <option value="">Choose a user</option>
                            <?php foreach ($dti_candidates as $candidate): ?>
                                <option value="<?php echo (int) $candidate['id']; ?>">
                                    <?php echo htmlspecialchars(($candidate['full_name'] ?? $candidate['username']) . ' (@' . $candidate['username'] . ') - ' . getUserRoleLabel($candidate['user_role'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <input type="hidden" name="action" value="promote_to_admin">
                        <button type="submit" class="admin-btn admin-btn-success">PROMOTE</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Price Monitoring Section -->
    <div id="price-monitoring-view" class="view-section">
        <div class="admin-card">
            <h2>PRICE MONITORING & OVERPRICING DETECTION</h2>
            <p style="margin-bottom: 1rem; opacity: 0.8;">Monitor product prices across all markets and detect potential overpricing</p>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid" style="margin-bottom: 1.5rem;">
            <div class="stat-card" style="border-left: 4px solid #22c55e;">
                <div class="stat-label">Normal Prices</div>
                <div class="stat-value" style="color: #22c55e;"><?php echo $price_monitoring_normal; ?></div>
                <div style="font-size: 0.9rem; color: #22c55e; margin-top: 0.5rem; font-weight: bold;">[OK]</div>
            </div>
            
            <div class="stat-card" style="border-left: 4px solid #fbbf24;">
                <div class="stat-label">Warning</div>
                <div class="stat-value" style="color: #fbbf24;"><?php echo $price_monitoring_warning; ?></div>
                <div style="font-size: 0.9rem; color: #fbbf24; margin-top: 0.5rem; font-weight: bold;">[!]</div>
            </div>
            
            <div class="stat-card" style="border-left: 4px solid #dc2626;">
                <div class="stat-label">Critical</div>
                <div class="stat-value" style="color: #dc2626;"><?php echo $price_monitoring_critical; ?></div>
                <div style="font-size: 0.9rem; color: #dc2626; margin-top: 0.5rem; font-weight: bold;">[!!]</div>
            </div>
            
            <div class="stat-card" style="border-left: 4px solid #9ca3af;">
                <div class="stat-label">No Data</div>
                <div class="stat-value" style="color: #9ca3af;"><?php echo $price_monitoring_no_data; ?></div>
                <div style="font-size: 0.9rem; color: #9ca3af; margin-top: 0.5rem; font-weight: bold;">[-]</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="admin-card">
            <h2>FILTERS</h2>
            <form method="GET" action="admin-dashboard.php#price-monitoring-view" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Filter by Market</label>
                    <select name="market_id" style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;">
                        <option value="">All Markets</option>
                        <?php foreach ($markets as $market): ?>
                            <option value="<?php echo $market['id']; ?>" <?php echo ($filter_market_id == $market['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($market['market_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Filter by Status</label>
                    <select name="status" style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;">
                        <option value="">All Status</option>
                        <option value="normal" <?php echo ($filter_status === 'normal') ? 'selected' : ''; ?>>Normal</option>
                        <option value="warning" <?php echo ($filter_status === 'warning') ? 'selected' : ''; ?>>Warning</option>
                        <option value="critical" <?php echo ($filter_status === 'critical') ? 'selected' : ''; ?>>Critical</option>
                        <option value="no_data" <?php echo ($filter_status === 'no_data') ? 'selected' : ''; ?>>No Data</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; font-size: 0.9rem; font-weight: bold; margin-bottom: 0.5rem; font-family: 'VT323', monospace; text-transform: uppercase; letter-spacing: 0.05em;">Filter by Category</label>
                    <?php
                    $categories_query = "SELECT DISTINCT category FROM market_products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
                    $categories_stmt = $conn->prepare($categories_query);
                    $categories_stmt->execute();
                    $all_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <select name="category" style="width: 100%; padding: 0.5rem; border: 2px solid #000000; background-color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem;">
                        <option value="">All Categories</option>
                        <?php foreach ($all_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_category === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; align-items: flex-end;">
                    <button type="submit" style="width: 100%; padding: 0.5rem 1rem; border: 2px solid #000000; background-color: #000000; color: #ffffff; font-family: 'VT323', monospace; font-size: 0.9rem; font-weight: bold; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em;">Apply Filters</button>
                </div>
            </form>
            
            <?php if ($filter_market_id || $filter_status || $filter_category): ?>
                <div style="margin-top: 1rem;">
                    <a href="admin-dashboard.php#price-monitoring-view" style="color: #000000; text-decoration: underline; font-size: 0.9rem;">Clear all filters</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info Box -->
        <div style="border: 3px solid #3b82f6; padding: 1rem; background-color: #dbeafe; margin-bottom: 1rem; font-family: 'VT323', monospace;">
            <h3 style="font-size: 1rem; font-weight: bold; margin-bottom: 0.5rem; color: #1e40af;">HOW OVERPRICING IS DETECTED</h3>
            <ul style="list-style: none; padding-left: 0;">
                <li style="margin-bottom: 0.25rem; font-size: 0.9rem;"><strong>Reference Price:</strong> Average price of the same product across all markets</li>
                <li style="margin-bottom: 0.25rem; font-size: 0.9rem;"><strong>Normal:</strong> Price ≤ 150% of reference price</li>
                <li style="margin-bottom: 0.25rem; font-size: 0.9rem;"><strong>Warning:</strong> Price 150-200% of reference, or >50% increase in 7 days</li>
                <li style="margin-bottom: 0.25rem; font-size: 0.9rem;"><strong>Critical:</strong> Price >200% of reference, or >100% increase in 30 days</li>
                <li style="margin-bottom: 0.25rem; font-size: 0.9rem;"><strong>No Data:</strong> Not enough price data to calculate reference</li>
            </ul>
        </div>

        <!-- Products Table -->
        <div class="admin-card">
            <h2>PRODUCTS PRICE MONITORING (<?php echo $price_monitoring_total; ?> products)</h2>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Product</th>
                            <th>Market</th>
                            <th>Current Price</th>
                            <th>Reference Price</th>
                            <th>Deviation</th>
                            <th>Farmer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($price_monitoring_products)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    No products found matching your filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($price_monitoring_products as $product): 
                                $monitoring = $product['monitoring'];
                                $badge_class = 'status-badge ' . $monitoring['status'];
                            ?>
                                <tr>
                                    <td>
                                        <span class="<?php echo $badge_class; ?>" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                            <?php 
                                            // Use text-based indicators
                                            $indicator = isset($monitoring['indicator']) ? $monitoring['indicator'] : '';
                                            $indicator_color = [
                                                'normal' => '#22c55e',
                                                'warning' => '#fbbf24',
                                                'critical' => '#dc2626',
                                                'no_data' => '#9ca3af'
                                            ][$monitoring['status']] ?? '#666';
                                            if ($indicator) {
                                                echo '<span style="color: ' . $indicator_color . '; font-weight: bold;">' . htmlspecialchars($indicator) . '</span>';
                                            }
                                            ?>
                                            <?php echo htmlspecialchars($monitoring['badge']); ?>
                                        </span>
                                        <?php if (isset($monitoring['reason'])): ?>
                                            <br><span style="font-size: 0.75rem; color: #666; margin-top: 0.25rem; display: block;"><?php echo htmlspecialchars($monitoring['reason']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: bold;">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </div>
                                        <?php if ($product['category']): ?>
                                            <div style="font-size: 0.75rem; color: #666;"><?php echo htmlspecialchars($product['category']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['market_name']); ?></td>
                                    <td>
                                        <div style="font-weight: bold;">
                                            ₱<?php echo number_format($product['current_price'], 2); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #666;"><?php echo htmlspecialchars($product['unit']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($product['reference_price']): ?>
                                            <div>
                                                ₱<?php echo number_format($product['reference_price'], 2); ?>
                                            </div>
                                            <?php if ($product['reference_data']): ?>
                                                <div style="font-size: 0.75rem; color: #666;">
                                                    (<?php echo $product['reference_data']['count']; ?> listings)
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['reference_price']): 
                                            $deviation = $monitoring['deviation_percent'];
                                            $deviation_color = $deviation > 0 ? '#dc2626' : ($deviation < 0 ? '#22c55e' : '#666');
                                        ?>
                                            <div style="font-weight: bold; color: <?php echo $deviation_color; ?>;">
                                                <?php echo $deviation > 0 ? '+' : ''; ?><?php echo number_format($deviation, 1); ?>%
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['spike_data']): 
                                            $spike = $product['spike_data'];
                                            if ($spike['percent_change_7days'] > 0 || $spike['percent_change_30days'] > 0):
                                        ?>
                                            <div style="font-size: 0.75rem; color: #f97316; margin-top: 0.25rem;">
                                                <?php if ($spike['percent_change_7days'] > 0): ?>
                                                    7d: +<?php echo number_format($spike['percent_change_7days'], 1); ?>%
                                                <?php endif; ?>
                                                <?php if ($spike['percent_change_30days'] > 0): ?>
                                                    | 30d: +<?php echo number_format($spike['percent_change_30days'], 1); ?>%
                                                <?php endif; ?>
                                            </div>
                                        <?php 
                                            endif;
                                        endif; 
                                        ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars($product['farmer_username'] ?? 'N/A'); ?>
                                        </div>
                                        <?php if ($product['farmer_name']): ?>
                                            <div style="font-size: 0.75rem; color: #666;"><?php echo htmlspecialchars($product['farmer_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Price Alerts Section -->
    <div id="alerts-view" class="view-section">
        <div class="admin-card">
            <h2>PRICE ALERTS</h2>
            <?php if (empty($price_alerts)): ?>
                <p>No price alerts found.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Product</th>
                            <th>Target Price</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($price_alerts as $alert): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alert['user_email']); ?></td>
                                <td><?php echo htmlspecialchars($alert['product_name'] ?? 'N/A'); ?></td>
                                <td>₱<?php echo number_format($alert['target_price'] ?? 0, 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($alert['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <?php if ($alert['is_active']): ?>
                                        <button type="button" class="admin-btn admin-btn-secondary" onclick="showConfirmModal('deactivate_price_alert', <?php echo $alert['id']; ?>, 'Deactivate this alert?', 'deactivate')">Deactivate</button>
                                        <?php endif; ?>
                                        <button type="button" class="admin-btn admin-btn-danger" onclick="showConfirmModal('remove_price_alert', <?php echo $alert['id']; ?>, 'Delete this alert?', 'delete')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-title" id="confirmModalTitle">CONFIRM ACTION</div>
        <div class="confirm-modal-message" id="confirmModalMessage"></div>
        <form id="confirmForm" method="POST" action="admin-dashboard.php" style="display: none;">
            <input type="hidden" name="action" id="confirmAction">
            <input type="hidden" name="product_id" id="confirmProductId">
            <input type="hidden" name="user_id" id="confirmUserId">
            <input type="hidden" name="alert_id" id="confirmAlertId">
        </form>
        <div class="confirm-modal-actions">
            <button type="button" class="confirm-modal-btn confirm-modal-btn-secondary" onclick="closeConfirmModal()">CANCEL</button>
            <button type="button" class="confirm-modal-btn" id="confirmModalSubmitBtn" onclick="submitConfirmForm()">CONFIRM</button>
        </div>
    </div>
</div>

<script>
    function showView(view) {
        // Check if it's a link click (for navigation to other pages)
        if (event && event.target.tagName === 'A') {
            // Let the link navigate normally
            return;
        }
        
        // Hide all views
        document.querySelectorAll('.view-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected view
        const targetView = document.getElementById(view + '-view');
        if (targetView) {
            targetView.classList.add('active');
        }
        
        // Add active class to clicked button
        if (event && event.target) {
            event.target.classList.add('active');
        }
    }
    
    // Handle hash navigation (e.g., admin-dashboard.php#products-view)
    window.addEventListener('DOMContentLoaded', function() {
        const hash = window.location.hash;
        if (hash) {
            const viewName = hash.replace('#', '').replace('-view', '');
            if (viewName) {
                // Remove active from all
                document.querySelectorAll('.view-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.querySelectorAll('.view-toggle-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Show the target view
                const targetView = document.getElementById(viewName + '-view');
                if (targetView) {
                    targetView.classList.add('active');
                }
                
                // Activate the corresponding button
                const targetBtn = document.querySelector(`[onclick*="${viewName}"]`);
                if (targetBtn) {
                    targetBtn.classList.add('active');
                }
            }
        }
    });
    
    function showConfirmModal(action, id, message, type) {
        const modal = document.getElementById('confirmModal');
        const title = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const form = document.getElementById('confirmForm');
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        
        // Set title and message
        title.textContent = type.toUpperCase() + ' CONFIRMATION';
        messageEl.textContent = message;
        
        // Clear all hidden inputs
        document.getElementById('confirmAction').value = action;
        document.getElementById('confirmProductId').value = '';
        document.getElementById('confirmUserId').value = '';
        document.getElementById('confirmAlertId').value = '';
        
        // Set the appropriate ID field
        if (action.includes('product')) {
            document.getElementById('confirmProductId').value = id;
        } else if (action.includes('farmer') || action.includes('user')) {
            document.getElementById('confirmUserId').value = id;
        } else if (action.includes('alert')) {
            document.getElementById('confirmAlertId').value = id;
        }
        
        // Style the submit button based on type
        submitBtn.className = 'confirm-modal-btn';
        if (type === 'delete' || type === 'reject') {
            submitBtn.classList.add('confirm-modal-btn-danger');
            submitBtn.textContent = type.toUpperCase();
        } else if (type === 'verify' || type === 'approve') {
            submitBtn.classList.add('confirm-modal-btn-primary');
            submitBtn.textContent = type.toUpperCase();
        } else {
            submitBtn.classList.add('confirm-modal-btn-primary');
            submitBtn.textContent = 'CONFIRM';
        }
        
        // Show modal
        modal.classList.add('active');
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        pendingForm = null;
    }
    
    function submitConfirmForm() {
        document.getElementById('confirmForm').submit();
    }
    
    // Store pending form submission
    let pendingForm = null;
    
    // Function to show form confirmation modal
    function showFormConfirmModal(form, message, title = 'CONFIRM ACTION') {
        pendingForm = form;
        const modal = document.getElementById('confirmModal');
        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const submitBtn = document.getElementById('confirmModalSubmitBtn');
        
        titleEl.textContent = title;
        messageEl.textContent = message;
        submitBtn.className = 'confirm-modal-btn confirm-modal-btn-primary';
        submitBtn.textContent = 'CONFIRM';
        submitBtn.onclick = submitPendingForm;
        
        modal.classList.add('active');
    }
    
    function submitPendingForm() {
        if (pendingForm) {
            pendingForm.submit();
        }
        closeConfirmModal();
        pendingForm = null;
    }
    
    // Handle DTI Admin form submissions
    document.addEventListener('DOMContentLoaded', function() {
        const createDtiAdminForm = document.getElementById('createDtiAdminForm');
        if (createDtiAdminForm) {
            createDtiAdminForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const username = this.querySelector('input[name="username"]').value;
                showFormConfirmModal(this, `Create new DTI Admin account for "${username}"?`, 'CREATE DTI ADMIN');
            });
        }
        
        const promoteUserForm = document.getElementById('promoteUserForm');
        if (promoteUserForm) {
            promoteUserForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const select = this.querySelector('select[name="user_id"]');
                const selectedOption = select.options[select.selectedIndex];
                const userName = selectedOption.text.split('(')[0].trim();
                showFormConfirmModal(this, `Promote "${userName}" to DTI Admin?`, 'PROMOTE TO DTI ADMIN');
            });
        }
        
        // Handle DTI admin action forms (deactivate, activate, demote)
        const dtiActionForms = document.querySelectorAll('form[action="admin-actions.php"]');
        dtiActionForms.forEach(form => {
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput && (actionInput.value === 'deactivate_user' || actionInput.value === 'activate_user' || actionInput.value === 'demote_to_consumer')) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const action = actionInput.value;
                    let message = '';
                    let title = 'CONFIRM ACTION';
                    
                    if (action === 'deactivate_user') {
                        message = 'Deactivate this DTI admin?';
                        title = 'DEACTIVATE DTI ADMIN';
                    } else if (action === 'activate_user') {
                        message = 'Activate this DTI admin?';
                        title = 'ACTIVATE DTI ADMIN';
                    } else if (action === 'demote_to_consumer') {
                        message = 'Demote this DTI admin to Consumer? This action cannot be undone.';
                        title = 'DEMOTE DTI ADMIN';
                    }
                    
                    showFormConfirmModal(this, message, title);
                });
            }
        });
    });
    
    // Close modal when clicking outside
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
            pendingForm = null;
        }
    });
    
    // Analytics Charts - Load Chart.js and initialize
    const chartScript = document.createElement('script');
    chartScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    chartScript.onload = function() {
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart');
        if (userGrowthCtx) {
            const userGrowthData = <?php echo json_encode($user_growth_data); ?>;
            const userLabels = userGrowthData.map(item => {
                const date = new Date(item.date);
                return (date.getMonth() + 1) + '/' + date.getDate();
            });
            const userCounts = userGrowthData.map(item => parseInt(item.count));
            
            new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: userLabels,
                    datasets: [{
                        label: 'New Users',
                        data: userCounts,
                        borderColor: '#000000',
                        backgroundColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 14
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 12
                                }
                            },
                            grid: {
                                color: '#e0e0e0'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 12
                                }
                            },
                            grid: {
                                color: '#e0e0e0'
                            }
                        }
                    }
                }
            });
        }
        
        // Reservation Trends Chart
        const reservationTrendsCtx = document.getElementById('reservationTrendsChart');
        if (reservationTrendsCtx) {
            const reservationTrendsData = <?php echo json_encode($reservation_trends_data); ?>;
            const reservationLabels = reservationTrendsData.map(item => {
                const date = new Date(item.date);
                return (date.getMonth() + 1) + '/' + date.getDate();
            });
            const reservationCounts = reservationTrendsData.map(item => parseInt(item.count));
            
            new Chart(reservationTrendsCtx, {
                type: 'line',
                data: {
                    labels: reservationLabels,
                    datasets: [{
                        label: 'Reservations',
                        data: reservationCounts,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 14
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 12
                                }
                            },
                            grid: {
                                color: '#e0e0e0'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    family: 'VT323, monospace',
                                    size: 12
                                }
                            },
                            grid: {
                                color: '#e0e0e0'
                            }
                        }
                    }
                }
            });
        }
    };
    document.head.appendChild(chartScript);
</script>

</body>
</html>
