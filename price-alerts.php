<?php
require_once 'includes/enhanced_functions.php';
require_once 'includes/security.php';

$page_title = 'Price Alerts - FarmScout Online';
$page_description = 'Set up price alerts for your favorite products';

// Track page view
trackPageView('price_alerts');

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_email = '';
if ($is_logged_in) {
    // Get user's email from database
    $conn = getDB();
    if ($conn) {
        try {
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user_data) {
                $user_email = $user_data['email'];
            }
        } catch (Exception $e) {
            error_log("Error fetching user email: " . $e->getMessage());
        }
    }
}

$message = '';
$message_type = '';
$price_alert_captcha_question = null;

// Check for success message from redirect
if (isset($_GET['removed']) && $_GET['removed'] == '1') {
    $message = 'Price alert removed successfully.';
    $message_type = 'success';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rateLimitKey = 'price_alert_' . ($action ?: 'general');
    $rateLimit = $action === 'remove_alert' ? 10 : 5;
    $rateWindow = 900; // 15 minutes
    
    if (!checkRateLimit($rateLimitKey, $rateLimit, $rateWindow)) {
        $message = 'Too many requests. Please wait a bit before trying again.';
        $message_type = 'error';
        logSecurityEvent('price_alert_rate_limit', [
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'error';
    } else {
        switch ($action) {
            case 'add_alert':
                // Use logged-in user's email if available, otherwise use form input
                $email = $is_logged_in ? $user_email : sanitizeInput($_POST['email'] ?? '');
                $product_id = intval($_POST['product_id'] ?? 0);
                $target_price = floatval($_POST['target_price'] ?? 0);
                $alert_type = sanitizeInput($_POST['alert_type'] ?? 'below');
                
                if (!$is_logged_in && !validateEmail($email)) {
                    $message = 'Please enter a valid email address.';
                    $message_type = 'error';
                } elseif (!$is_logged_in && !validateSimpleCaptcha('price_alert_form', $_POST['captcha_answer'] ?? '')) {
                    $message = 'Security check failed. Please try again.';
                    $message_type = 'error';
                } elseif ($product_id <= 0) {
                    $message = 'Please select a valid product.';
                    $message_type = 'error';
                } elseif ($alert_type !== 'change' && $target_price <= 0) {
                    $message = 'Please enter a valid target price.';
                    $message_type = 'error';
                } else {
                    if (createPriceAlert($email, $product_id, $alert_type, $target_price)) {
                        $message = 'Price alert set successfully! You will be notified when the price changes.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to set price alert. Please try again.';
                        $message_type = 'error';
                    }
                }
                break;
                
            case 'remove_alert':
                $alert_id = intval($_POST['alert_id'] ?? 0);
                $email = sanitizeInput($_POST['user_email'] ?? '');
                if ($alert_id > 0 && !empty($email)) {
                    if (deletePriceAlert($alert_id, $email)) {
                        // Redirect to prevent form resubmission and refresh the alerts list
                        header('Location: price-alerts.php?removed=1');
                        exit;
                    } else {
                        $message = 'Failed to remove price alert.';
                        $message_type = 'error';
                    }
                }
                break;
        }
    }
}

$price_alert_captcha_question = !$is_logged_in ? generateSimpleCaptcha('price_alert_form') : null;

// Get all active markets for the market selector
$markets = getMarkets();

// Get products only if a market is selected
$products = [];
$selected_market_id = isset($_POST['market_id']) ? intval($_POST['market_id']) : (isset($_GET['market_id']) ? intval($_GET['market_id']) : null);

if ($selected_market_id) {
    try {
        $conn = getDB();
        if ($conn) {
            // Check if deleted_at column exists
            $hasDeletedAt = false;
            try {
                $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
                $hasDeletedAt = $col_check->rowCount() > 0;
            } catch (Exception $e) {
                // Column doesn't exist, that's okay
            }
            $deletedAtCheck = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
            
            // Get products from the selected market only
            $query = "SELECT 
                        mp.id,
                        mp.product_name as name,
                        mp.product_name as filipino_name,
                        mp.product_description as description,
                        mp.category,
                        COALESCE(c.filipino_name, mp.category) as category_filipino,
                        mp.price as current_price,
                        mp.price as previous_price,
                        mp.unit,
                        mp.product_image as image_url,
                        mp.is_available as is_active,
                        0 as is_featured,
                        mp.market_id,
                        m.market_name
                      FROM market_products mp
                      LEFT JOIN categories c ON LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name))
                      LEFT JOIN markets m ON mp.market_id = m.id
                      WHERE mp.market_id = :market_id 
                      AND mp.is_available = 1 
                      $deletedAtCheck
                      ORDER BY mp.product_name ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':market_id', $selected_market_id, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching market products for price alerts: " . $e->getMessage());
        $products = [];
    }
}

$categories = getCategories();

// Set current page for navigation
$current_page = 'price-alerts.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>">
    
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="price-alerts-page">
<?php
// Use Market Finder style header
include 'includes/header-market-finder.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');

    @font-face {
        font-family: 'InterDisplay';
        src: url('assets/fonts/Inter-4.1/extras/otf/InterDisplay-Bold.otf') format('opentype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }
    
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
    
    /* Price Alerts Page - Market Finder Style - Compact for Full Screen */
    body.price-alerts-page {
        font-family: 'VT323', monospace !important;
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        padding: 2rem !important;
        line-height: 1.4 !important;
        margin: 0 !important;
        height: 100vh;
        overflow-y: auto;
        box-sizing: border-box;
    }
    
    /* Ensure header styles match Market Finder exactly - no overrides */
    body.price-alerts-page .header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin-bottom: 3rem !important;
        font-size: 1.25rem !important;
        letter-spacing: 0.05em !important;
    }
    
    body.price-alerts-page .logo {
        font-family: 'InterDisplay', system-ui, sans-serif !important;
        font-weight: 800 !important;
        font-size: 1.1rem !important;
        letter-spacing: -0.02em !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
    }
    
    body.price-alerts-page .logo img {
        height: 2rem !important;
        width: auto !important;
        object-fit: contain !important;
    }
    
    body.price-alerts-page .nav {
        display: flex !important;
        gap: 1.5rem !important;
        align-items: center !important;
    }
    
    /* Hide nav on mobile - override the above rule */
    @media (max-width: 768px) {
        body.price-alerts-page .nav {
            display: none !important;
        }
        
        body.price-alerts-page .hamburger-menu {
            display: flex !important;
        }
        
        /* Mobile menu: LOGIN link same style as other nav links (no box, aligned) */
        body.price-alerts-page .mobile-menu-nav .login-btn {
            padding: 0 !important;
            border: none !important;
            background-color: transparent !important;
            color: var(--text-color, #000000) !important;
            font-size: clamp(1.75rem, 6vw, 2.5rem) !important;
            text-align: left !important;
            min-height: auto !important;
        }
        body.price-alerts-page .mobile-menu-nav .login-btn:hover {
            background-color: transparent !important;
            color: var(--text-color, #000000) !important;
        }
    }
    
    @media (max-width: 480px) {
        body.price-alerts-page .nav {
            display: none !important;
        }
        
        body.price-alerts-page .hamburger-menu {
            display: flex !important;
        }
    }
    
    body.price-alerts-page .nav a {
        color: var(--text-color) !important;
        text-decoration: none !important;
        transition: color 0.3s ease !important;
        font-size: 1.1rem !important;
    }
    
    body.price-alerts-page .nav-icons {
        display: flex !important;
        gap: 0.5rem !important;
        margin-left: 1rem !important;
    }
    
    body.price-alerts-page .icon-box {
        width: 20px !important;
        height: 20px !important;
        border: 2px solid var(--border-color) !important;
        display: inline-block !important;
    }
    
    body.price-alerts-page .login-btn {
        padding: 0.2rem 0.6rem !important;
        border: 2px solid var(--border-color) !important;
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        text-decoration: none !important;
        font-size: 0.9rem !important;
        transition: all 0.3s ease 0.1s !important;
    }
    
    body.price-alerts-page .login-btn:hover {
        background-color: var(--text-color) !important;
        color: var(--bg-color) !important;
        transition: all 0.3s ease 0s !important;
    }
    
    /* Account Dropdown - Match Market Finder exactly - NO OVERRIDES */
    /* Let the header component handle all dropdown styles */
    
    /* Fix: Prevent navbar from turning black on dropdown hover */
    body.price-alerts-page .header,
    body.price-alerts-page .nav,
    body.price-alerts-page .account-dropdown {
        background-color: transparent !important;
    }
    
    body.price-alerts-page .account-dropdown:hover,
    body.price-alerts-page .account-dropdown.active {
        background-color: transparent !important;
    }
    
    body.price-alerts-page .account-dropdown-menu {
        background-color: var(--bg-color, #ffffff) !important;
    }
    
    body.price-alerts-page .account-dropdown-menu a:hover {
        background-color: var(--text-color, #000000) !important;
        color: var(--bg-color, #ffffff) !important;
    }
    
    /* Ensure only dropdown items change on hover, not the navbar */
    body.price-alerts-page .account-dropdown-toggle:hover {
        background-color: transparent !important;
        color: var(--text-color, #000000) !important;
    }
    
    /* Hero Section - Compact */
    .price-alerts-hero {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .price-alerts-hero h1 {
        font-size: clamp(2rem, 5vw, 3rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.5rem !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    .price-alerts-hero p {
        font-size: clamp(0.9rem, 1.5vw, 1.1rem) !important;
        letter-spacing: 0.05em !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
        opacity: 0.8;
    }
    
    /* Main Content Container */
    .price-alerts-content {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    /* Section Title */
    .section-title {
        font-size: clamp(1.5rem, 3vw, 2rem) !important;
        margin-bottom: 1rem !important;
        letter-spacing: 0.05em !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    /* Cards Grid - Compact */
    .alerts-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 1.5rem;
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .alerts-cards-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Card Styling - Compact */
    .alert-card {
        border: 3px solid #000000;
        padding: 2rem;
        background-color: #ffffff;
        transition: all 0.3s ease;
        width: 100%;
        min-height: 500px;
    }
    
    .alert-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .alert-card h2 {
        font-size: clamp(1.5rem, 3vw, 2rem) !important;
        font-weight: bold !important;
        margin-bottom: 1.5rem !important;
        letter-spacing: 0.05em !important;
        font-family: 'VT323', monospace !important;
        color: #000000 !important;
    }
    
    /* Form Styling - Compact */
    .alert-form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    
    .form-group label {
        font-size: 0.85rem;
        font-family: 'VT323', monospace;
        color: #000000;
        letter-spacing: 0.05em;
    }
    
    .form-group input,
    .form-group select {
        padding: 0.5rem 0.75rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.95rem;
        width: 100%;
        box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-width: 3px;
    }
    
    .form-group input[readonly],
    .form-group input[disabled] {
        background-color: #f5f5f5;
        cursor: not-allowed;
    }
    
    .form-group small {
        font-size: 0.75rem;
        opacity: 0.7;
        font-family: 'VT323', monospace;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
    }
    
    /* Button Styling - Compact */
    .alert-btn {
        padding: 0.5rem 1rem;
        border: 3px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.95rem;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        letter-spacing: 0.05em;
    }
    
    .alert-btn:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    .alert-btn-secondary {
        padding: 0.4rem 0.75rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .alert-btn-secondary:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    /* Info Box - Compact */
    .info-box {
        border: 2px solid #000000;
        padding: 0.75rem;
        background-color: #f9f9f9;
        margin-top: 0.5rem;
    }
    
    .info-box h3 {
        font-size: 0.9rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    .info-box ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .info-box li {
        font-size: 0.75rem;
        margin-bottom: 0.25rem;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    /* Message Box - Compact */
    .message-box {
        border: 3px solid #000000;
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        font-family: 'VT323', monospace;
        font-size: 0.9rem;
    }
    
    .message-box.success {
        background-color: #f0f9f0;
        color: #000000;
    }
    
    .message-box.error {
        background-color: #fff0f0;
        color: #000000;
    }
    
    /* Alerts Container - Compact */
    .alerts-container {
        min-height: 150px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .alert-item {
        border: 2px solid #000000;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background-color: #ffffff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    
    .alert-item-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
    }
    
    .alert-item-image {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border: 2px solid #000000;
        flex-shrink: 0;
    }
    
    .alert-item-placeholder {
        width: 40px;
        height: 40px;
        border: 2px solid #000000;
        background-color: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    
    .alert-item-info h3 {
        font-size: 0.9rem;
        font-weight: bold;
        margin-bottom: 0.15rem;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    .alert-item-info p {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-bottom: 0.15rem;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    .alert-item-info small {
        font-size: 0.7rem;
        opacity: 0.6;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    .alert-remove-btn {
        padding: 0.35rem 0.75rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .alert-remove-btn:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    /* Empty State - Compact */
    .empty-state {
        text-align: center;
        padding: 1.5rem 1rem;
        color: #000000;
        font-family: 'VT323', monospace;
    }
    
    .empty-state svg {
        width: 40px;
        height: 40px;
        margin: 0 auto 0.5rem;
        opacity: 0.5;
    }
    
    .empty-state p {
        font-size: 0.85rem;
        opacity: 0.7;
    }
    
    /* Loading State - Compact */
    .loading-state {
        text-align: center;
        padding: 1rem;
        font-family: 'VT323', monospace;
        color: #000000;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 30px;
        height: 30px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #000000;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 0.5rem;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        body.price-alerts-page {
            padding: 1rem;
        }
        
        .price-alerts-hero h1 {
            font-size: 2rem;
        }
        
        .price-alerts-hero p {
            font-size: 1rem;
        }
        
        .alerts-cards-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .alert-card {
            padding: 1rem;
        }
        
        .alert-card h2 {
            font-size: 1.2rem;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .alert-btn {
            min-height: 44px; /* Touch-friendly */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .alert-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert-item-content {
            width: 100%;
        }
        
        .alert-remove-btn {
            width: 100%;
            min-height: 44px; /* Touch-friendly */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-selector {
            flex-direction: column;
        }
        
        .product-selector select {
            width: 100%;
            min-height: 44px; /* Touch-friendly */
        }
    }
    
    @media (max-width: 480px) {
        body.price-alerts-page {
            padding: 0.75rem;
        }
        
        .price-alerts-hero {
            padding: 1.5rem 1rem;
        }
        
        .price-alerts-hero h1 {
            font-size: 1.75rem;
        }
        
        .alert-card {
            padding: 0.75rem;
        }
    }
    
    /* Ensure page fits viewport */
    body.price-alerts-page {
        overflow-x: hidden;
    }
    
    .price-alerts-content {
        padding-bottom: 1rem;
    }
    
    /* Price History Modal */
    .price-history-modal {
        display: none !important;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        overflow-y: auto;
    }
    
    .price-history-modal.active {
        display: flex !important;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    
    .price-history-modal-content {
        background-color: #ffffff;
        border: 3px solid #000000;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 1.5rem;
        font-family: 'VT323', monospace;
        position: relative;
    }
    
    .price-history-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #000000;
    }
    
    .price-history-modal-header h2 {
        font-size: 1.5rem;
        font-weight: bold;
        color: #000000;
        font-family: 'VT323', monospace;
    }
    
    .price-history-modal-close {
        background: none;
        border: 2px solid #000000;
        color: #000000;
        font-size: 1.5rem;
        width: 36px;
        height: 36px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'VT323', monospace;
        transition: all 0.3s ease;
    }
    
    .price-history-modal-close:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    .price-history-date-range {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .price-history-date-btn {
        padding: 0.4rem 0.8rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: 'VT323', monospace;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .price-history-date-btn:hover,
    .price-history-date-btn.active {
        background-color: #000000;
        color: #ffffff;
    }
    
    .price-history-chart-container {
        position: relative;
        height: 400px;
        margin-bottom: 1rem;
    }
    
    .price-history-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid #000000;
    }
    
    .price-history-stat {
        text-align: center;
    }
    
    .price-history-stat-label {
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
        font-family: 'VT323', monospace;
    }
    
    .price-history-stat-value {
        font-size: 1.2rem;
        font-weight: bold;
        color: #000000;
        font-family: 'VT323', monospace;
    }
    
    .price-history-loading {
        text-align: center;
        padding: 2rem;
        font-family: 'VT323', monospace;
    }
    
    .price-history-error {
        text-align: center;
        padding: 2rem;
        color: #dc2626;
        font-family: 'VT323', monospace;
    }
    
    @media (max-width: 768px) {
        .price-history-modal.active {
            padding: 1rem;
        }
        
        .price-history-modal-content {
            padding: 1rem;
        }
        
        .price-history-chart-container {
            height: 300px;
        }
    }
</style>

<body class="price-alerts-page">
    <!-- Main Content -->
    <div class="price-alerts-content" style="max-width: 95%; margin: 0 auto; padding: 2rem 1rem;">
        <?php if ($message): ?>
        <div class="message-box <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="alerts-cards-grid" style="grid-template-columns: 1fr; max-width: 100%;">
            <!-- Manage Existing Alerts -->
            <div class="alert-card" style="min-height: 600px;">
                <h2>MANAGE YOUR ALERTS</h2>
                
                <?php if ($is_logged_in): ?>
                    <!-- Auto-load alerts for logged-in users -->
                    <div style="margin-bottom: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.3rem;">
                            <label style="font-family: 'VT323', monospace; font-size: 0.85rem;">Your Price Alerts</label>
                            <button onclick="loadAlerts()" class="alert-btn-secondary">
                                REFRESH
                            </button>
                        </div>
                        <div style="font-size: 0.75rem; opacity: 0.7; font-family: 'VT323', monospace; margin-bottom: 0.5rem;">
                            Email: <strong><?php echo htmlspecialchars($user_email); ?></strong>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Login prompt for anonymous users -->
                    <div class="login-prompt" style="text-align: center; padding: 2rem 1rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 48px; height: 48px; margin: 0 auto 1rem; opacity: 0.5;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                        <p style="font-size: 1rem; margin-bottom: 1rem; font-family: 'VT323', monospace;">Please log in to manage your price alerts</p>
                        <a href="login.php" class="alert-btn" style="display: inline-block; text-decoration: none;">
                            LOG IN
                        </a>
                        <p style="font-size: 0.85rem; margin-top: 0.75rem; opacity: 0.7; font-family: 'VT323', monospace;">
                            Don't have an account? <a href="register.php" style="color: #000000; text-decoration: underline;">Register here</a>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div id="alerts-container" class="alerts-container">
                    <?php if ($is_logged_in): ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>
                        </svg>
                            <p>No active alerts found</p>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Create price alerts from the <a href="market-finder.php" style="color: #000000; text-decoration: underline;">Market Finder</a> or product pages!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

<!-- Price History Modal -->
<div class="price-history-modal" id="priceHistoryModal">
    <div class="price-history-modal-content">
        <div class="price-history-modal-header">
            <h2 id="priceHistoryTitle">PRICE HISTORY</h2>
            <button class="price-history-modal-close" id="priceHistoryModalClose">&times;</button>
        </div>
        <div id="priceHistoryContent">
            <div class="price-history-loading">
                <div class="loading-spinner"></div>
                <p>Loading price history...</p>
            </div>
        </div>
    </div>
    </div>

<script>
// Make CSRF token available to JavaScript
const csrfToken = '<?php echo getCSRFToken(); ?>';
const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
const userEmail = '<?php echo htmlspecialchars($user_email); ?>';

function loadAlerts() {
    // Only allow logged-in users to load alerts
    if (!isLoggedIn) {
            return;
    }
    
    const email = userEmail;
    const container = document.getElementById('alerts-container');
    container.innerHTML = '<div class="loading-state"><div class="loading-spinner"></div><p>Loading alerts...</p></div>';
    
    // Make AJAX call to get user alerts
    const formData = new FormData();
    formData.append('email', email);
    
    fetch('api/get_user_alerts.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>${data.error}</p>
                </div>
            `;
            return;
        }
        
        if (!data.alerts || data.alerts.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5-5-5h5v-5a7.5 7.5 0 0015 0v5z"/>
                    </svg>
                    <p>No active alerts found for ${email}</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem;">Set up your first price alert using the form on the left!</p>
                </div>
            `;
            return;
        }
        
        // Display alerts
        let alertsHTML = '';
        data.alerts.forEach(alert => {
            alertsHTML += `
                <div class="alert-item">
                    <div class="alert-item-content">
                        ${alert.image_url ? `<img src="${alert.image_url}" alt="Product" class="alert-item-image">` : '<div class="alert-item-placeholder">📦</div>'}
                        <div class="alert-item-info">
                            <h3>${alert.product_name}</h3>
                            <p>${alert.alert_text}</p>
                            <small>Current: ${alert.current_price} • Created: ${alert.created_at}</small>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="alert-remove-btn" onclick="showPriceHistory(${alert.product_id || 0}, '${(alert.product_name || '').replace(/'/g, "\\'")}')" style="background-color: #22c55e; color: #ffffff; border-color: #22c55e;">
                            VIEW HISTORY
                        </button>
                    <form method="POST" action="price-alerts.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                        <input type="hidden" name="action" value="remove_alert">
                        <input type="hidden" name="alert_id" value="${alert.id}">
                        <input type="hidden" name="user_email" value="${email}">
                            <button type="submit" class="alert-remove-btn"
                                onclick="return confirm('Remove this price alert?')">
                                REMOVE
                        </button>
                    </form>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = alertsHTML;
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = `
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p>Failed to load alerts. Please try again.</p>
            </div>
        `;
    });
}

// Price History Chart Functions
let priceHistoryChart = null;

function showPriceHistory(productId, productName) {
    // Validate product ID
    productId = parseInt(productId);
    if (!productId || productId <= 0) {
        console.error('Invalid product ID:', productId);
        alert('Error: Invalid product ID. Please refresh the page and try again.');
        return;
    }
    
    const modal = document.getElementById('priceHistoryModal');
    const title = document.getElementById('priceHistoryTitle');
    const content = document.getElementById('priceHistoryContent');
    
    if (!modal || !title || !content) {
        console.error('Modal elements not found');
        return;
    }
    
    // Extract market name from productName if it exists (format: "Product Name - Market Name")
    let displayName = productName || 'PRODUCT';
    let marketName = '';
    const marketMatch = displayName.match(/\s-\s(.+)$/);
    if (marketMatch) {
        marketName = ' - ' + marketMatch[1];
        displayName = displayName.replace(/\s-\s.+$/, ''); // Remove market name from product name
    }
    
    // Set initial title (will be updated with API response to avoid duplication)
    title.textContent = `PRICE HISTORY - ${displayName.toUpperCase()}${marketName}`;
    modal.classList.add('active');
    
    // Store market name for later use when API response comes back
    title.dataset.marketName = marketName;
    
    // Show loading state
    content.innerHTML = `
        <div class="price-history-loading">
            <div class="loading-spinner"></div>
            <p>Loading price history...</p>
        </div>
    `;
    
    // Load price history
    loadPriceHistory(productId, 30); // Default to 30 days
}

function closePriceHistoryModal() {
    const modal = document.getElementById('priceHistoryModal');
    modal.classList.remove('active');
    
    // Destroy chart if it exists
    if (priceHistoryChart) {
        priceHistoryChart.destroy();
        priceHistoryChart = null;
    }
}

// Close modal when clicking outside or on close button
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('priceHistoryModal');
    const closeBtn = document.getElementById('priceHistoryModalClose');
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePriceHistoryModal();
            }
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            closePriceHistoryModal();
        });
    }
});

async function loadPriceHistory(productId, days) {
    const content = document.getElementById('priceHistoryContent');
    
    if (!content) {
        console.error('Price history content element not found');
        return;
    }
    
    // Show loading state
    content.innerHTML = `
        <div class="price-history-loading">
            <div class="loading-spinner"></div>
            <p>Loading price history...</p>
        </div>
    `;
    
    try {
        console.log(`Fetching price history for product ${productId}, days: ${days}`);
        
        // Add timeout to prevent hanging
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
        
        const response = await fetch(`api/get_price_history.php?product_id=${productId}&days=${days}`, {
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        // Check if response is ok
        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error Response:', errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('API Response:', responseText);
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        // Check if data is valid
        if (!data || typeof data !== 'object') {
            throw new Error('Invalid response from server');
        }
        
        if (!data.success) {
            console.error('API returned error:', data.message);
            content.innerHTML = `
                <div class="price-history-error">
                    <p>${data.message || 'Failed to load price history'}</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem; opacity: 0.7;">Please check the console for more details.</p>
                </div>
            `;
            return;
        }
        
        // Handle case where API returns old format (price_history instead of price_data)
        const priceData = data.price_data || data.price_history || [];
        
        if (!priceData || priceData.length === 0) {
            content.innerHTML = `
                <div class="price-history-error">
                    <p>No price history available for this product yet.</p>
                    <p style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">Price history will appear once farmers update prices.</p>
                </div>
            `;
            return;
        }
        
        // Ensure data has price_data property
        if (!data.price_data) {
            data.price_data = priceData;
        }
        
        // Update title with product name from API (to avoid duplication)
        const title = document.getElementById('priceHistoryTitle');
        if (title && data.product_name) {
            // Get market name from title dataset (stored in showPriceHistory)
            const marketName = title.dataset.marketName || '';
            title.textContent = `PRICE HISTORY - ${data.product_name.toUpperCase()}${marketName}`;
        }
        
        // Render chart and stats
        renderPriceHistoryChart(data, days);
        
    } catch (error) {
        console.error('Error loading price history:', error);
        
        let errorMessage = 'Error loading price history.';
        if (error.name === 'AbortError') {
            errorMessage = 'Request timed out. Please try again.';
        } else if (error.message) {
            errorMessage = `Error: ${error.message}`;
        }
        
        content.innerHTML = `
            <div class="price-history-error">
                <p>${errorMessage}</p>
                <p style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.7;">Please check the console for more details.</p>
            </div>
        `;
    }
}

function renderPriceHistoryChart(data, selectedDays) {
    const content = document.getElementById('priceHistoryContent');
    const stats = data.stats || {};
    
    // Create HTML structure
    content.innerHTML = `
        <div class="price-history-date-range">
            <button class="price-history-date-btn ${selectedDays === 7 ? 'active' : ''}" onclick="loadPriceHistory(${data.product_id}, 7)">7 DAYS</button>
            <button class="price-history-date-btn ${selectedDays === 30 ? 'active' : ''}" onclick="loadPriceHistory(${data.product_id}, 30)">30 DAYS</button>
            <button class="price-history-date-btn ${selectedDays === 90 ? 'active' : ''}" onclick="loadPriceHistory(${data.product_id}, 90)">90 DAYS</button>
            <button class="price-history-date-btn ${selectedDays === 365 ? 'active' : ''}" onclick="loadPriceHistory(${data.product_id}, 365)">ALL TIME</button>
        </div>
        <div class="price-history-chart-container">
            <canvas id="priceHistoryChart"></canvas>
        </div>
        <div class="price-history-stats">
            <div class="price-history-stat">
                <div class="price-history-stat-label">MIN PRICE</div>
                <div class="price-history-stat-value">₱${stats.min_price?.toFixed(2) || '0.00'}</div>
            </div>
            <div class="price-history-stat">
                <div class="price-history-stat-label">MAX PRICE</div>
                <div class="price-history-stat-value">₱${stats.max_price?.toFixed(2) || '0.00'}</div>
            </div>
            <div class="price-history-stat">
                <div class="price-history-stat-label">AVG PRICE</div>
                <div class="price-history-stat-value">₱${stats.avg_price?.toFixed(2) || '0.00'}</div>
            </div>
            <div class="price-history-stat">
                <div class="price-history-stat-label">CURRENT PRICE</div>
                <div class="price-history-stat-value">₱${stats.current_price?.toFixed(2) || '0.00'}</div>
            </div>
        </div>
    `;
    
    // Destroy existing chart
    if (priceHistoryChart) {
        priceHistoryChart.destroy();
    }
    
    // Create new chart
    const ctx = document.getElementById('priceHistoryChart').getContext('2d');
    const labels = data.price_data.map(d => d.formatted_date);
    const prices = data.price_data.map(d => d.price);
    
    priceHistoryChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Price (₱)',
                data: prices,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#22c55e',
                pointBorderColor: '#000000',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#000000',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#22c55e',
                    borderWidth: 2,
                    padding: 12,
                    titleFont: {
                        family: 'VT323',
                        size: 16
                    },
                    bodyFont: {
                        family: 'VT323',
                        size: 14
                    },
                    callbacks: {
                        label: function(context) {
                            return '₱' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    ticks: {
                        font: {
                            family: 'VT323',
                            size: 12
                        },
                        color: '#000000',
                        callback: function(value) {
                            return '₱' + value.toFixed(2);
                        }
                    },
                    grid: {
                        color: '#e5e5e5',
                        lineWidth: 1
                    },
                    border: {
                        color: '#000000',
                        width: 2
                    }
                },
                x: {
                    ticks: {
                        font: {
                            family: 'VT323',
                            size: 11
                        },
                        color: '#000000',
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: {
                        color: '#e5e5e5',
                        lineWidth: 1
                    },
                    border: {
                        color: '#000000',
                        width: 2
                    }
                }
            }
        }
    });
}

// Handle alert type changes - show/hide target price field
if (document.getElementById('alert_type')) {
document.getElementById('alert_type').addEventListener('change', function() {
    const targetPriceContainer = document.getElementById('target_price_container');
    const targetPriceInput = document.getElementById('target_price');
    const label = targetPriceContainer.querySelector('label');
    
    if (this.value === 'change') {
        // For "any price change", make target price optional and hide it
        targetPriceContainer.style.opacity = '0.5';
        targetPriceInput.removeAttribute('required');
        targetPriceInput.value = '';
        targetPriceInput.placeholder = 'Not required for any price change';
        label.textContent = 'Target Price (₱) - Optional';
    } else {
        // For specific price thresholds, make target price required
        targetPriceContainer.style.opacity = '1';
        targetPriceInput.setAttribute('required', 'required');
        targetPriceInput.placeholder = '0.00';
        label.textContent = 'Target Price (₱)';
        
        // Auto-fill based on current product price if available
        const productSelect = document.getElementById('product_id');
        if (productSelect.value) {
            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const priceText = selectedOption.text.split(' - ')[1];
            if (priceText) {
                const currentPrice = parseFloat(priceText.replace('₱', ''));
                let targetPrice;
                if (this.value === 'below') {
                    targetPrice = (currentPrice * 0.9).toFixed(2); // 10% below current price
                } else if (this.value === 'above') {
                    targetPrice = (currentPrice * 1.1).toFixed(2); // 10% above current price
                }
                if (targetPrice) {
                    targetPriceInput.value = targetPrice;
                }
            }
        }
    }
});
}

// Load products when market is selected
document.getElementById('market_id').addEventListener('change', function() {
    const marketId = this.value;
    const productSelect = document.getElementById('product_id');
    
    // Clear existing products
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    productSelect.disabled = true;
    
    if (!marketId) {
        productSelect.innerHTML = '<option value="">Select a market first...</option>';
        return;
    }
    
    // Fetch products for selected market
    fetch(`api/get_market_products.php?market_id=${marketId}`)
        .then(response => response.json())
        .then(data => {
            productSelect.innerHTML = '<option value="">Select a product...</option>';
            
            if (data.error) {
                productSelect.innerHTML = `<option value="">Error: ${data.error}</option>`;
                return;
            }
            
            if (data.products && data.products.length > 0) {
                data.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = `${product.filipino_name} (${product.name}) - ${product.current_price}`;
                    productSelect.appendChild(option);
                });
                productSelect.disabled = false;
            } else {
                productSelect.innerHTML = '<option value="">No products available in this market</option>';
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
            productSelect.innerHTML = '<option value="">Error loading products. Please try again.</option>';
        });
});

// Auto-fill target price based on current price
document.getElementById('product_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const alertType = document.getElementById('alert_type').value;
    
    if (selectedOption.value && alertType !== 'change') {
        const priceText = selectedOption.text.split(' - ')[1];
        if (priceText) {
            const currentPrice = parseFloat(priceText.replace('₱', '').replace(',', ''));
            let targetPrice;
            if (alertType === 'below') {
                targetPrice = (currentPrice * 0.9).toFixed(2); // 10% below current price
            } else if (alertType === 'above') {
                targetPrice = (currentPrice * 1.1).toFixed(2); // 10% above current price
            } else {
                targetPrice = (currentPrice * 0.9).toFixed(2); // Default to below
            }
            document.getElementById('target_price').value = targetPrice;
        }
    }
});

// Initialize the form state on page load
document.addEventListener('DOMContentLoaded', function() {
    // Trigger alert type change to set initial state
    document.getElementById('alert_type').dispatchEvent(new Event('change'));
    
    // If a market is already selected (from form submission), load its products
    const marketSelect = document.getElementById('market_id');
    if (marketSelect.value) {
        // Trigger the change event to load products
        marketSelect.dispatchEvent(new Event('change'));
    }
    
    // Auto-load alerts for logged-in users
    if (isLoggedIn) {
        loadAlerts();
    }
});
</script>


</body>
</html>
