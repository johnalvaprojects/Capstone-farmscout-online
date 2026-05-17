<?php
require_once 'includes/enhanced_functions.php';

if (!isLoggedIn() || !isAdminUser()) {
    header('Location: login.php?error=' . urlencode('Access denied. DTI access required.'));
    exit;
}

$monitoring_org = isSuperAdmin() ? 'Super Admin' : 'DTI';
$page_title = isSuperAdmin() ? 'Super Admin Price Monitoring - FarmScout Online' : 'DTI Price Monitoring - FarmScout Online';
$page_description = $monitoring_org . ' - Monitor product prices and detect overpricing';
$monitoring_heading = isSuperAdmin() ? 'SUPER ADMIN PRICE MONITORING & OVERPRICING DETECTION' : 'DTI PRICE MONITORING & OVERPRICING DETECTION';

$conn = getDB();

// Get filter parameters
$filter_market_id = isset($_GET['market_id']) ? intval($_GET['market_id']) : null;
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
$filter_category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : null;

// Get all markets for filter dropdown
$markets_query = "SELECT id, market_name FROM markets WHERE status = 'active' ORDER BY market_name ASC";
$markets_stmt = $conn->prepare($markets_query);
$markets_stmt->execute();
$all_markets = $markets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for filter dropdown
$categories_query = "SELECT DISTINCT category FROM market_products WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
$categories_stmt = $conn->prepare($categories_query);
$categories_stmt->execute();
$all_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get products with price monitoring data
$products = getProductsWithPriceMonitoring($filter_market_id, $filter_status);

// Apply category filter if specified
if ($filter_category) {
    $products = array_filter($products, function($p) use ($filter_category) {
        return strtolower(trim($p['category'])) === strtolower(trim($filter_category));
    });
}

// Calculate summary statistics
$total_products = count($products);
$normal_count = count(array_filter($products, fn($p) => $p['monitoring']['status'] === 'normal'));
$warning_count = count(array_filter($products, fn($p) => $p['monitoring']['status'] === 'warning'));
$critical_count = count(array_filter($products, fn($p) => $p['monitoring']['status'] === 'critical'));
$no_data_count = count(array_filter($products, fn($p) => $p['monitoring']['status'] === 'no_data'));

// Sort products: Critical first, then Warning, then Normal, then No Data
usort($products, function($a, $b) {
    $priority = ['critical' => 1, 'warning' => 2, 'normal' => 3, 'no_data' => 4];
    $a_priority = $priority[$a['monitoring']['status']] ?? 5;
    $b_priority = $priority[$b['monitoring']['status']] ?? 5;
    if ($a_priority !== $b_priority) {
        return $a_priority <=> $b_priority;
    }
    // If same status, sort by deviation (highest first)
    return $b['monitoring']['deviation_percent'] <=> $a['monitoring']['deviation_percent'];
});
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
    
    body {
        font-family: 'VT323', monospace !important;
        background-color: #ffffff !important;
        color: #000000 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow-x: auto;
        overflow-y: auto;
    }
    
    /* Hero Section */
    .price-monitoring-hero {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .price-monitoring-hero h1 {
        font-size: clamp(1.5rem, 4vw, 2.5rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.25rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    .price-monitoring-hero p {
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
        margin-bottom: 2rem;
    }
    
    .stat-card {
        border: 3px solid #000000;
        border-radius: 8px;
        padding: 1rem;
        background-color: #ffffff;
        transition: all 0.3s ease;
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
        border-radius: 8px;
        padding: 1rem;
        background-color: #ffffff;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
        box-shadow: 4px 4px 0 #000000;
    }
    
    /* Table card - allow horizontal scroll */
    .admin-card.table-card {
        overflow-x: visible;
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
    
    /* Tables */
    .data-table {
        width: 100%;
        min-width: 1000px;
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

    .data-table th:nth-child(1),
    .data-table td:nth-child(1) {
        width: 12%;
    }

    .data-table th:nth-child(2),
    .data-table td:nth-child(2) {
        width: 20%;
    }

    .data-table th:nth-child(3),
    .data-table td:nth-child(3) {
        width: 16%;
    }

    .data-table th:nth-child(4),
    .data-table td:nth-child(4),
    .data-table th:nth-child(5),
    .data-table td:nth-child(5) {
        width: 14%;
    }

    .data-table th:nth-child(6),
    .data-table td:nth-child(6) {
        width: 10%;
        text-align: right;
    }

    .data-table th:nth-child(7),
    .data-table td:nth-child(7) {
        width: 14%;
    }

    .data-table th:nth-child(8),
    .data-table td:nth-child(8) {
        width: 10%;
        text-align: center;
    }
    
    .data-table tr:hover {
        background-color: #f5f5f5;
    }
    
    /* Form Elements */
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .filter-form label {
        display: block;
        font-size: 0.9rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        font-family: 'VT323', monospace;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .filter-form select,
    .filter-form input {
        width: 100%;
        padding: 0.5rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        font-family: 'VT323', monospace;
        font-size: 0.9rem;
    }
    
    .filter-form button {
        padding: 0.5rem 1rem;
        border: 2px solid #000000;
        background-color: #000000;
        color: #ffffff;
        font-family: 'VT323', monospace;
        font-size: 0.9rem;
        font-weight: bold;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.3s ease;
    }
    
    .filter-form button:hover {
        background-color: #ffffff;
        color: #000000;
    }
    
    /* Info Box */
    .info-box {
        border: 3px solid #3b82f6;
        padding: 1rem;
        background-color: #dbeafe;
        margin-bottom: 1rem;
        font-family: 'VT323', monospace;
    }
    
    .info-box h3 {
        font-size: 1rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        color: #1e40af;
    }
    
    .info-box ul {
        list-style: none;
        padding-left: 0;
    }
    
    .info-box li {
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
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
    
    .status-badge.normal {
        background-color: #22c55e;
        color: #000000 !important;
        border-color: #000000 !important;
    }
    
    .status-badge.warning {
        background-color: #fbbf24;
        color: #000000 !important;
        border-color: #000000 !important;
    }
    
    .status-badge.critical {
        background-color: #dc2626;
        color: #ffffff !important;
        border-color: #000000 !important;
    }
    
    .status-badge.no_data {
        background-color: #9ca3af;
        color: #000000 !important;
        border-color: #000000 !important;
    }
    
    /* Links */
    .back-link {
        display: inline-flex;
        align-items: center;
        color: #000000;
        text-decoration: none;
        font-family: 'VT323', monospace;
        font-size: 0.9rem;
        margin-top: 1rem;
        transition: all 0.3s ease;
    }
    
    .back-link:hover {
        opacity: 0.7;
    }
</style>

<div style="width: 100%; max-width: 100%; margin: 0; padding: 2rem 1.5rem; overflow-x: visible; box-sizing: border-box;">
    <!-- Page Header -->
    <div class="price-monitoring-hero">
        <h1><?php echo htmlspecialchars($monitoring_heading); ?></h1>
        <p>Monitor product prices across all markets and detect potential overpricing</p>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card" style="border-left: 4px solid #22c55e;">
            <div class="stat-label">Normal Prices</div>
            <div class="stat-value" style="color: #22c55e;"><?php echo $normal_count; ?></div>
            <div style="font-size: 0.9rem; color: #22c55e; margin-top: 0.5rem; font-weight: bold;">[OK]</div>
        </div>
        
        <div class="stat-card" style="border-left: 4px solid #fbbf24;">
            <div class="stat-label">Warning</div>
            <div class="stat-value" style="color: #fbbf24;"><?php echo $warning_count; ?></div>
            <div style="font-size: 0.9rem; color: #fbbf24; margin-top: 0.5rem; font-weight: bold;">[!]</div>
        </div>
        
        <div class="stat-card" style="border-left: 4px solid #dc2626;">
            <div class="stat-label">Critical</div>
            <div class="stat-value" style="color: #dc2626;"><?php echo $critical_count; ?></div>
            <div style="font-size: 0.9rem; color: #dc2626; margin-top: 0.5rem; font-weight: bold;">[!!]</div>
        </div>
        
        <div class="stat-card" style="border-left: 4px solid #9ca3af;">
            <div class="stat-label">No Data</div>
            <div class="stat-value" style="color: #9ca3af;"><?php echo $no_data_count; ?></div>
            <div style="font-size: 0.9rem; color: #9ca3af; margin-top: 0.5rem; font-weight: bold;">[-]</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="admin-card">
        <h2>FILTERS</h2>
        <form method="GET" action="admin-price-monitoring.php" class="filter-form">
            <div>
                <label>Filter by Market</label>
                <select name="market_id">
                    <option value="">All Markets</option>
                    <?php foreach ($all_markets as $market): ?>
                        <option value="<?php echo $market['id']; ?>" <?php echo ($filter_market_id == $market['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($market['market_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label>Filter by Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="normal" <?php echo ($filter_status === 'normal') ? 'selected' : ''; ?>>🟢 Normal</option>
                    <option value="warning" <?php echo ($filter_status === 'warning') ? 'selected' : ''; ?>>🟡 Warning</option>
                    <option value="critical" <?php echo ($filter_status === 'critical') ? 'selected' : ''; ?>>🔴 Critical</option>
                    <option value="no_data" <?php echo ($filter_status === 'no_data') ? 'selected' : ''; ?>>⚪ No Data</option>
                </select>
            </div>
            
            <div>
                <label>Filter by Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($all_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: flex; align-items: flex-end;">
                <button type="submit" style="width: 100%;">Apply Filters</button>
            </div>
        </form>
        
        <?php if ($filter_market_id || $filter_status || $filter_category): ?>
            <div style="margin-top: 1rem;">
                <a href="admin-price-monitoring.php" style="color: #000000; text-decoration: underline; font-size: 0.9rem;">Clear all filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Info Box -->
    <div class="info-box">
        <h3>HOW OVERPRICING IS DETECTED</h3>
        <ul>
            <li><strong>Reference Price:</strong> Average price of the same product across all markets</li>
            <li><strong>Normal:</strong> Price ≤ 150% of reference price</li>
            <li><strong>Warning:</strong> Price 150-200% of reference, or >50% increase in 7 days</li>
            <li><strong>Critical:</strong> Price >200% of reference, or >100% increase in 30 days</li>
            <li><strong>No Data:</strong> Not enough price data to calculate reference</li>
        </ul>
    </div>

    <!-- Products Table -->
    <div class="admin-card table-card">
        <h2>PRODUCTS PRICE MONITORING (<?php echo $total_products; ?> products)</h2>
        <div style="overflow-x: auto; overflow-y: visible; width: 100%; -webkit-overflow-scrolling: touch;">
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                No products found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $product): 
                            $monitoring = $product['monitoring'];
                            $badge_class = 'status-badge ' . $monitoring['status'];
                        ?>
                            <tr>
                                <td>
                                    <span class="<?php echo $badge_class; ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; color: inherit !important;">
                                        <?php 
                                        // Use text-based indicators - use black/white for contrast
                                        $indicator = isset($monitoring['indicator']) ? $monitoring['indicator'] : '';
                                        $indicator_color = [
                                            'normal' => '#000000',
                                            'warning' => '#000000',
                                            'critical' => '#ffffff',
                                            'no_data' => '#000000'
                                        ][$monitoring['status']] ?? '#000000';
                                        if ($indicator) {
                                            echo '<span style="color: ' . $indicator_color . ' !important; font-weight: bold; font-size: 1rem;">' . htmlspecialchars($indicator) . '</span>';
                                        }
                                        ?>
                                        <span style="color: inherit !important; font-weight: bold;"><?php echo htmlspecialchars($monitoring['badge']); ?></span>
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
                                    
                                    <?php 
                                    // Show price trend
                                    if (isset($product['spike_data']['trend']) && $product['spike_data']['trend'] !== 'stable'):
                                        $trend = $product['spike_data']['trend'];
                                        $trend_percent = $product['spike_data']['trend_percent'] ?? 0;
                                        $trend_color = $trend === 'increasing' ? '#dc2626' : '#22c55e';
                                        $trend_arrow = $trend === 'increasing' ? '↑' : '↓';
                                    ?>
                                        <div style="font-size: 0.75rem; color: <?php echo $trend_color; ?>; margin-top: 0.25rem; font-weight: bold;">
                                            <?php echo $trend_arrow; ?> <?php echo abs($trend_percent); ?>% (trend)
                                        </div>
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
                                <td>
                                    <a href="admin-dashboard.php?view=products&product_id=<?php echo $product['id']; ?>" 
                                       style="color: #2563eb; text-decoration: underline;">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Auto-refresh functionality
(function() {
    let autoRefreshEnabled = true;
    let refreshInterval = null;
    const REFRESH_INTERVAL_MS = 60000; // 60 seconds
    
    function setupAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        if (autoRefreshEnabled) {
            refreshInterval = setInterval(function() {
                // Reload the page to get fresh data
                window.location.reload();
            }, REFRESH_INTERVAL_MS);
        }
    }
    
    // Start auto-refresh on page load
    setupAutoRefresh();
    
    // Add visual indicator
    const refreshIndicator = document.createElement('div');
    refreshIndicator.style.cssText = 'position: fixed; bottom: 20px; right: 20px; padding: 0.5rem 1rem; background: #000; color: #fff; border: 2px solid #000; font-family: "VT323", monospace; font-size: 0.9rem; z-index: 1000; cursor: pointer;';
    refreshIndicator.innerHTML = '🔄 Auto-refresh: ON (60s)';
    refreshIndicator.title = 'Click to toggle auto-refresh';
    document.body.appendChild(refreshIndicator);
    
    // Toggle auto-refresh on click
    refreshIndicator.addEventListener('click', function() {
        autoRefreshEnabled = !autoRefreshEnabled;
        if (autoRefreshEnabled) {
            refreshIndicator.innerHTML = '🔄 Auto-refresh: ON (60s)';
            refreshIndicator.style.background = '#000';
            refreshIndicator.style.color = '#fff';
            setupAutoRefresh();
        } else {
            refreshIndicator.innerHTML = '⏸ Auto-refresh: OFF';
            refreshIndicator.style.background = '#666';
            refreshIndicator.style.color = '#fff';
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
    });
    
    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    });
})();
</script>

</body>
</html>
