<?php
require_once 'includes/enhanced_functions.php';

// Require admin authentication
requireAdmin();

$page_title = 'Analytics Dashboard - FarmScout Online';
$page_description = 'View usage analytics and market insights';

// Track page view
trackPageView('analytics');

// Get analytics data
$conn = getDB();
$analytics = [];

// Basic statistics
$analytics['total_products'] = getTotalProductsCount();
$analytics['total_categories'] = count(getCategories());
$analytics['market_status'] = getMarketStatus();

// User sessions (last 7 days)
$query = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as new_sessions,
            SUM(page_views) as total_page_views,
            SUM(search_queries) as total_searches
          FROM user_sessions 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          GROUP BY DATE(created_at)
          ORDER BY date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$analytics['daily_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Popular products (by views/featured)
$query = "SELECT 
            p.filipino_name,
            p.name,
            p.current_price,
            p.is_featured,
            COUNT(ph.id) as price_updates
          FROM products p
          LEFT JOIN price_history ph ON p.id = ph.product_id
          WHERE p.is_active = 1
          GROUP BY p.id
          ORDER BY p.is_featured DESC, price_updates DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$analytics['popular_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Price trends (last 7 days)
$query = "SELECT 
            DATE(recorded_at) as date,
            AVG(price) as avg_price,
            COUNT(*) as price_updates,
            COUNT(DISTINCT product_id) as products_updated
          FROM price_history 
          WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          GROUP BY DATE(recorded_at)
          ORDER BY date ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$analytics['price_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Category distribution
$query = "SELECT 
            c.filipino_name,
            c.name,
            COUNT(p.id) as product_count,
            AVG(p.current_price) as avg_price
          FROM categories c
          LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY product_count DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$analytics['category_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent price alerts
$query = "SELECT 
            pa.*,
            p.filipino_name,
            p.name,
            p.current_price
          FROM price_alerts pa
          LEFT JOIN products p ON pa.product_id = p.id
          WHERE pa.is_active = 1
          ORDER BY pa.created_at DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$analytics['recent_alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-primary mb-4">Analytics Dashboard</h1>
        <p class="text-text-secondary">Market insights and usage analytics for FarmScout Online</p>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-primary" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-text-secondary">Total Products</p>
                    <p class="text-2xl font-bold text-primary"><?php echo formatNumber($analytics['total_products']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-accent-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-accent" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-text-secondary">Categories</p>
                    <p class="text-2xl font-bold text-primary"><?php echo formatNumber($analytics['total_categories']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-success-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-success" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-text-secondary">Active Vendors</p>
                    <p class="text-2xl font-bold text-primary"><?php echo formatNumber($analytics['market_status']['active_vendors']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-card p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-warning-100 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-warning" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-text-secondary">Price Alerts</p>
                    <p class="text-2xl font-bold text-primary"><?php echo formatNumber(count($analytics['recent_alerts'])); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Daily Statistics Chart -->
        <div class="bg-white rounded-lg shadow-card p-6">
            <h2 class="text-xl font-semibold text-primary mb-4">Daily Activity (Last 7 Days)</h2>
            <div class="h-64">
                <canvas id="dailyStatsChart"></canvas>
            </div>
        </div>

        <!-- Category Distribution -->
        <div class="bg-white rounded-lg shadow-card p-6">
            <h2 class="text-xl font-semibold text-primary mb-4">Products by Category</h2>
            <div class="space-y-4">
                <?php foreach ($analytics['category_stats'] as $category): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 bg-primary rounded-full mr-3"></div>
                        <div>
                            <p class="font-semibold text-text-primary"><?php echo htmlspecialchars($category['filipino_name']); ?></p>
                            <p class="text-sm text-text-secondary"><?php echo htmlspecialchars($category['name']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-primary"><?php echo formatNumber($category['product_count']); ?></p>
                        <p class="text-sm text-text-secondary">products</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Popular Products -->
        <div class="bg-white rounded-lg shadow-card p-6">
            <h2 class="text-xl font-semibold text-primary mb-4">Popular Products</h2>
            <div class="space-y-4">
                <?php foreach ($analytics['popular_products'] as $product): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-surface-100 rounded-lg flex items-center justify-center mr-3">
                            <?php if ($product['is_featured']): ?>
                            <svg class="w-5 h-5 text-accent" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            <?php else: ?>
                            <svg class="w-5 h-5 text-text-muted" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="font-semibold text-text-primary"><?php echo htmlspecialchars($product['filipino_name']); ?></p>
                            <p class="text-sm text-text-secondary"><?php echo htmlspecialchars($product['name']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-primary"><?php echo formatCurrency($product['current_price']); ?></p>
                        <p class="text-sm text-text-secondary"><?php echo formatNumber($product['price_updates']); ?> updates</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Price Alerts -->
        <div class="bg-white rounded-lg shadow-card p-6">
            <h2 class="text-xl font-semibold text-primary mb-4">Recent Price Alerts</h2>
            <div class="space-y-4">
                <?php foreach ($analytics['recent_alerts'] as $alert): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-warning-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-warning" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-text-primary"><?php echo htmlspecialchars($alert['filipino_name']); ?></p>
                            <p class="text-sm text-text-secondary"><?php echo htmlspecialchars($alert['user_email']); ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-primary"><?php echo formatCurrency($alert['target_price']); ?></p>
                        <p class="text-sm text-text-secondary"><?php echo ucfirst($alert['alert_type']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Price Trends Chart -->
    <div class="bg-white rounded-lg shadow-card p-6">
        <h2 class="text-xl font-semibold text-primary mb-4">Price Trends (Last 7 Days)</h2>
        <div class="h-64">
            <canvas id="priceTrendsChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Statistics Chart
const dailyStatsCtx = document.getElementById('dailyStatsChart').getContext('2d');
const dailyStatsData = <?php echo json_encode($analytics['daily_stats']); ?>;

new Chart(dailyStatsCtx, {
    type: 'line',
    data: {
        labels: dailyStatsData.map(item => new Date(item.date).toLocaleDateString()),
        datasets: [
            {
                label: 'New Sessions',
                data: dailyStatsData.map(item => item.new_sessions),
                borderColor: '#2D5016',
                backgroundColor: 'rgba(45, 80, 22, 0.1)',
                tension: 0.4
            },
            {
                label: 'Page Views',
                data: dailyStatsData.map(item => item.total_page_views),
                borderColor: '#FF6B35',
                backgroundColor: 'rgba(255, 107, 53, 0.1)',
                tension: 0.4
            },
            {
                label: 'Searches',
                data: dailyStatsData.map(item => item.total_searches),
                borderColor: '#28A745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Price Trends Chart
const priceTrendsCtx = document.getElementById('priceTrendsChart').getContext('2d');
const priceTrendsData = <?php echo json_encode($analytics['price_trends']); ?>;

new Chart(priceTrendsCtx, {
    type: 'bar',
    data: {
        labels: priceTrendsData.map(item => new Date(item.date).toLocaleDateString()),
        datasets: [
            {
                label: 'Average Price (₱)',
                data: priceTrendsData.map(item => parseFloat(item.avg_price).toFixed(2)),
                backgroundColor: 'rgba(45, 80, 22, 0.8)',
                borderColor: '#2D5016',
                borderWidth: 1
            },
            {
                label: 'Price Updates',
                data: priceTrendsData.map(item => item.price_updates),
                backgroundColor: 'rgba(255, 107, 53, 0.8)',
                borderColor: '#FF6B35',
                borderWidth: 1,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
