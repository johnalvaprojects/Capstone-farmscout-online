<?php
require_once 'includes/enhanced_functions.php';

if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. DTI access required.'));
    exit;
}

$logs_role_label = isSuperAdmin() ? 'Super Admin' : 'DTI';
$page_title = isSuperAdmin() ? 'Super Admin Logs - FarmScout Online' : 'DTI Logs - FarmScout Online';
$page_description = "View all {$logs_role_label} actions and system activity";
$logs_heading = isSuperAdmin() ? 'SUPER ADMIN ACTIVITY LOGS' : 'DTI ACTIVITY LOGS';
$logs_table_warning = isSuperAdmin() ? 'SUPER ADMIN LOGS TABLE NOT FOUND' : 'DTI LOGS TABLE NOT FOUND';

$admin_id = $_SESSION['user_id'];
$conn = getDB();

/**
 * Activity logs UI: set to false to show filters + full log table again.
 * Set true to show the maintenance screen instead (e.g. during upgrades).
 */
$fs_admin_logs_maintenance = true;

// Check if admin_logs table exists (try DESCRIBE - most reliable method)
$has_logs_table = false;
try {
    // Try to describe the table - this is the most reliable check
    $conn->query("DESCRIBE admin_logs");
    $has_logs_table = true;
} catch (PDOException $e) {
    // Table doesn't exist or can't be accessed
    $has_logs_table = false;
    // Only log if it's not a "table doesn't exist" error
    if (strpos($e->getMessage(), "doesn't exist") === false && strpos($e->getCode(), "42S02") === false) {
        error_log("Error checking admin_logs table: " . $e->getMessage());
    }
}

// Get filter parameters
$filter_action = $_GET['action_type'] ?? '';
$filter_admin = intval($_GET['admin_id'] ?? 0);
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query with filters
$logs = [];
if (!$fs_admin_logs_maintenance && $has_logs_table) {
    $logs_query = "SELECT al.*, u.username as admin_username_display, u.full_name as admin_full_name
                   FROM admin_logs al
                   LEFT JOIN users u ON al.admin_id = u.id
                   WHERE 1=1";
    
    $params = [];
    
    if (!empty($filter_action)) {
        $logs_query .= " AND al.action_type = ?";
        $params[] = $filter_action;
    }
    
    if ($filter_admin > 0) {
        $logs_query .= " AND al.admin_id = ?";
        $params[] = $filter_admin;
    }
    
    if (!empty($filter_date_from)) {
        $logs_query .= " AND DATE(al.created_at) >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $logs_query .= " AND DATE(al.created_at) <= ?";
        $params[] = $filter_date_to;
    }
    
    $logs_query .= " ORDER BY al.created_at DESC LIMIT 500";
    
    $logs_stmt = $conn->prepare($logs_query);
    $logs_stmt->execute($params);
    $logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all admins for filter dropdown
$admins = [];
$action_types = [];

if (!$fs_admin_logs_maintenance && $has_logs_table) {
    // Get admins who have performed actions
    $admins_query = "SELECT DISTINCT u.id, u.username, u.full_name 
                     FROM users u
                     JOIN admin_logs al ON u.id = al.admin_id
                     WHERE u.user_role IN ('admin', 'super_admin')
                     ORDER BY u.full_name, u.username";
    $admins_stmt = $conn->prepare($admins_query);
    $admins_stmt->execute();
    $admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique action types for filter
    $action_types_query = "SELECT DISTINCT action_type FROM admin_logs ORDER BY action_type";
    $action_types_stmt = $conn->prepare($action_types_query);
    $action_types_stmt->execute();
    $action_types = $action_types_stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // If table doesn't exist, just get all admins
    $admins_query = "SELECT id, username, full_name 
                     FROM users
                     WHERE user_role IN ('admin', 'super_admin')
                     ORDER BY full_name, username";
    $admins_stmt = $conn->prepare($admins_query);
    $admins_stmt->execute();
    $admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    body.admin-logs-page {
        font-family: 'VT323', monospace !important;
        background-color: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        line-height: 1.3 !important;
        margin: 0 !important;
        min-height: 100vh;
    }
    
    /* Hero Section */
    .admin-logs-hero {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .admin-logs-hero h1 {
        font-size: clamp(1.5rem, 4vw, 2.5rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.25rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    .admin-logs-hero p {
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
    
    /* Action Type Badges */
    .action-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border: 2px solid #000000;
        font-family: 'VT323', monospace;
        font-size: 0.8rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .action-badge.product {
        background-color: #3b82f6;
        color: #ffffff;
    }
    
    .action-badge.user {
        background-color: #22c55e;
        color: #000000;
    }
    
    .action-badge.reservation {
        background-color: #f59e0b;
        color: #000000;
    }
    
    .action-badge.price_alert {
        background-color: #8b5cf6;
        color: #ffffff;
    }
    
    .action-badge.unknown {
        background-color: #6b7280;
        color: #ffffff;
    }
    
    /* Details JSON */
    .details-json {
        font-size: 0.75rem;
        font-family: 'Courier New', monospace;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .details-json:hover {
        white-space: normal;
        word-break: break-all;
    }
    
    .warning-box {
        border: 3px solid #fbbf24;
        background-color: #fef3c7;
        padding: 1rem;
        margin-bottom: 1rem;
        font-family: 'VT323', monospace;
    }
    
    .warning-box h3 {
        color: #92400e;
        margin-bottom: 0.5rem;
    }
    
    .warning-box p {
        color: #78350f;
        margin-bottom: 0.5rem;
    }
    
    .fs-logs-maintenance-box {
        border: 3px solid #000000;
        background: #f0f0f0;
        padding: 2rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 4px 4px 0 #000000;
        text-align: center;
        max-width: 42rem;
        margin-left: auto;
        margin-right: auto;
    }
    .fs-logs-maintenance-box h2 {
        font-size: 1.35rem;
        margin-bottom: 0.75rem;
        letter-spacing: 0.06em;
    }
    .fs-logs-maintenance-box p {
        font-size: 1rem;
        opacity: 0.9;
        line-height: 1.45;
    }
    /* Full-page maintenance: disable top nav + logo links so nothing is clickable except the escape control */
    body.fs-logs-maintenance-mode .header {
        pointer-events: none;
        user-select: none;
        opacity: 0.85;
    }
    body.fs-logs-maintenance-mode .fs-logs-maintenance-wrap {
        min-height: calc(100vh - 8rem);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1rem 0 3rem;
    }
    body.fs-logs-maintenance-mode .fs-logs-maintenance-box {
        max-width: 36rem;
        width: 100%;
    }
    body.fs-logs-maintenance-mode .fs-logs-maintenance-back {
        display: inline-block;
        margin-top: 1.5rem;
        pointer-events: auto;
        cursor: pointer;
        padding: 0.65rem 1.25rem;
        border: 3px solid #000;
        background: #000;
        color: #fff;
        font-family: 'VT323', monospace;
        font-size: 1.1rem;
        text-decoration: none;
        letter-spacing: 0.05em;
    }
    body.fs-logs-maintenance-mode .fs-logs-maintenance-back:hover {
        background: #333;
    }
</style>

<body class="admin-logs-page<?php echo $fs_admin_logs_maintenance ? ' fs-logs-maintenance-mode' : ''; ?>">
<div class="<?php echo $fs_admin_logs_maintenance ? 'fs-logs-maintenance-wrap' : ''; ?>" style="width: 100%; max-width: 100%; margin: 0; padding: 2rem 1.5rem;">

    <!-- Hero Section -->
    <div class="admin-logs-hero">
        <h1><?php echo $fs_admin_logs_maintenance ? 'ACTIVITY LOGS' : htmlspecialchars($logs_heading); ?></h1>
        <p><?php echo $fs_admin_logs_maintenance ? 'Service temporarily unavailable' : 'Track all admin actions and system changes'; ?></p>
    </div>

    <?php if (!$fs_admin_logs_maintenance): ?>
    <!-- Navigation Tabs -->
    <div class="view-toggle" style="margin-bottom: 1.5rem;">
        <a href="admin-dashboard.php" class="view-toggle-btn">OVERVIEW</a>
        <a href="admin-dashboard.php#products-view" class="view-toggle-btn">PENDING PRODUCTS</a>
        <a href="admin-dashboard.php#farmers-view" class="view-toggle-btn">PENDING FARMERS</a>
        <a href="admin-dashboard.php#markets-view" class="view-toggle-btn">MARKETS</a>
        <a href="admin-dashboard.php#users-view" class="view-toggle-btn">USERS</a>
        <a href="admin-dashboard.php#alerts-view" class="view-toggle-btn">PRICE ALERTS</a>
        <a href="admin-reservations.php" class="view-toggle-btn">RESERVATIONS</a>
        <a href="admin-logs.php" class="view-toggle-btn active">LOGS</a>
    </div>
    <?php endif; ?>

    <?php if ($fs_admin_logs_maintenance): ?>
        <div class="fs-logs-maintenance-box">
            <h2>UNDER MAINTENANCE</h2>
            <p>The activity log viewer is temporarily unavailable while we complete system updates. Administrative activity continues to be recorded; the full audit trail will be accessible once service is restored.</p>
            <p style="margin-top: 1rem; font-size: 0.95rem;">Return to the DTI dashboard using the button below.</p>
            <a href="admin-dashboard.php" class="fs-logs-maintenance-back">← BACK TO DTI DASHBOARD</a>
        </div>
    <?php elseif (!$has_logs_table): ?>
        <div class="warning-box">
            <h3>⚠️ <?php echo htmlspecialchars($logs_table_warning); ?></h3>
            <p>The admin_logs table has not been created yet.</p>
            <p><strong>Quick Fix:</strong> <a href="create_admin_logs_table_direct.php" style="color: #000; text-decoration: underline; font-weight: bold;">Click here to create the table now (Direct Method)</a></p>
            <p>Or try: <a href="create_admin_logs_table.php" style="color: #000; text-decoration: underline;">Standard Method</a></p>
            <p>Or run the migration script: <code>php run_admin_logs_migration.php</code></p>
            <p>Or manually execute: <code>database/create_admin_logs_table.sql</code></p>
        </div>
    <?php else: ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <h2>FILTERS</h2>
        <form method="GET" action="admin-logs.php">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="action_type">Action Type</label>
                    <select name="action_type" id="action_type">
                        <option value="">All Actions</option>
                        <?php foreach ($action_types as $action_type): ?>
                            <option value="<?php echo htmlspecialchars($action_type); ?>" <?php echo $filter_action === $action_type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(str_replace('_', ' ', ucwords($action_type, '_'))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="admin_id">Admin</label>
                    <select name="admin_id" id="admin_id">
                        <option value="">All Admins</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>" <?php echo $filter_admin == $admin['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['full_name'] ?: $admin['username']); ?>
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
                <a href="admin-logs.php" class="admin-btn admin-btn-secondary">CLEAR FILTERS</a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="admin-card">
        <h2>ACTIVITY LOGS (<?php echo count($logs); ?>)</h2>
        <?php if (empty($logs)): ?>
            <p>No logs found.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['admin_full_name'] ?: $log['admin_username_display'] ?: $log['admin_username']); ?></td>
                            <td>
                                <span class="action-badge <?php echo $log['target_type']; ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucwords($log['action_type'], '_'))); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['target_id']): ?>
                                    <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?> #<?php echo $log['target_id']; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log['action_details']): ?>
                                    <div class="details-json" title="<?php echo htmlspecialchars($log['action_details']); ?>">
                                        <?php 
                                        $details = json_decode($log['action_details'], true);
                                        if ($details) {
                                            $summary = [];
                                            foreach ($details as $key => $value) {
                                                if (in_array($key, ['product_name', 'username', 'customer', 'farmer', 'old_status', 'new_status'])) {
                                                    $summary[] = $key . ': ' . (is_string($value) ? substr($value, 0, 30) : $value);
                                                }
                                            }
                                            echo htmlspecialchars(implode(', ', array_slice($summary, 0, 2)));
                                        } else {
                                            echo htmlspecialchars(substr($log['action_details'], 0, 50));
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php endif; ?>

</div>

</body>
</html>
