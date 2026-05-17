<?php
require_once 'includes/enhanced_functions.php';
require_once 'config/maps.php';

// Deep links (?market=) → Vite Market Finder SPA (avoid showing only the legacy tile hub)
if (!isset($_GET['legacy']) && isset($_GET['market']) && (int) $_GET['market'] > 0) {
    $q = ['market' => (int) $_GET['market']];
    if (isset($_GET['lat'], $_GET['lng'])) {
        $q['lat'] = $_GET['lat'];
        $q['lng'] = $_GET['lng'];
    }
    if (isset($_GET['category']) && (int) $_GET['category'] > 0) {
        $q['category'] = (int) $_GET['category'];
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    $prefix = ($dir === '/' || $dir === '\\' || $dir === '.') ? fs_web_base_path() : rtrim($dir, '/');
    $target = ($prefix === '' ? '' : $prefix) . '/app/markets?' . http_build_query($q);
    header('Location: ' . $target, true, 302);
    exit;
}

// Get market parameter
$selected_market_id = isset($_GET['market']) ? intval($_GET['market']) : null;
$selected_category_id = isset($_GET['category']) ? intval($_GET['category']) : null;

$page_title = 'Market Finder - FarmScout Online';
$page_description = 'Find the nearest markets and discover fresh products in La Union.';

// Get user email if logged in
$is_logged_in = isLoggedIn();
$user_email = '';
if ($is_logged_in) {
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

// Get all markets with coordinates
$conn = getDB();
$markets = fs_cache_remember('markets_with_stats', 300, function() use ($conn) {
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT m.*, 
                    COUNT(DISTINCT mp.id) as product_count
              FROM markets m 
              LEFT JOIN market_products mp ON m.id = mp.market_id
              WHERE m.status = 'active'
              GROUP BY m.id
              ORDER BY m.market_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
});

// Function to calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return round($distance, 2);
}

// Add distance to markets if user location is provided
$user_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$user_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$markets_with_distance = [];

if ($user_lat && $user_lng) {
    foreach ($markets as $market) {
        $distance = calculateDistance($user_lat, $user_lng, $market['latitude'], $market['longitude']);
        $market['distance'] = $distance;
        $markets_with_distance[] = $market;
    }
    // Sort by distance
    usort($markets_with_distance, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
} else {
    $markets_with_distance = $markets;
}

// Function to convert number to binary format (8 digits)
function formatBinaryId($number) {
    return str_pad(decbin($number), 8, '0', STR_PAD_LEFT);
}

function isMarketCurrentlyOpen($market) {
    // Check if market is manually set to closed
    if (isset($market['is_open']) && $market['is_open'] == 0) {
        return false;
    }
    
    // Check operating days
    if (isset($market['operating_days']) && !empty($market['operating_days'])) {
        $currentDay = date('D'); // e.g., "Mon", "Tue"
        $operatingDays = explode(',', $market['operating_days']);
        $operatingDays = array_map('trim', $operatingDays);
        
        // Convert day abbreviations to match database format
        $dayMap = [
            'Mon' => 'Mon', 'Tue' => 'Tue', 'Wed' => 'Wed', 
            'Thu' => 'Thu', 'Fri' => 'Fri', 'Sat' => 'Sat', 'Sun' => 'Sun'
        ];
        
        if (!in_array($currentDay, $operatingDays)) {
            return false;
        }
    }
    
    // Check if current time is within operating hours
    if (isset($market['opening_time']) && isset($market['closing_time'])) {
        $currentTime = date('H:i:s');
        $openingTime = $market['opening_time'];
        $closingTime = $market['closing_time'];
        
        // Handle case where closing time is next day (e.g., 22:00 - 02:00)
        if ($closingTime < $openingTime) {
            // Market closes next day
            return ($currentTime >= $openingTime || $currentTime <= $closingTime);
        } else {
            // Normal same-day hours
            return ($currentTime >= $openingTime && $currentTime <= $closingTime);
        }
    }
    
    // Default to open if no specific hours set
    return true;
}

// Get categories for selected market (if market is selected)
$selected_market_categories = [];
if ($selected_market_id) {
    $selected_market_categories = getProductsByCategories([$selected_market_id]);
}

// Get current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=VT323&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');

        @font-face {
            font-family: 'InterDisplay';
            src: url('assets/fonts/Inter-4.1/extras/otf/InterDisplay-Bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
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
        
        body {
            font-family: 'VT323', monospace;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            padding: 2rem;
            line-height: 1.4;
            max-width: 100%;
        }
        
        main {
            max-width: 100%;
        }
        
        /* Header - ensure nav/hamburger stay above hero on mobile */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3rem;
            font-size: 1.25rem;
            letter-spacing: 0.05em;
            position: relative;
            z-index: 1000;
        }
        
        .logo {
            font-family: 'InterDisplay', system-ui, sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .logo img {
            height: 2rem;
            width: auto;
            object-fit: contain;
        }
        
        .nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav a {
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            display: inline-block;
        }
        
        .nav a:not(.login-btn):hover {
            transform: translateY(-3px);
        }
        
        .nav-icons {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
        }
        
        .icon-box {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            display: inline-block;
        }
        
        .login-btn {
            padding: 0.2rem 0.6rem;
            border: 2px solid var(--border-color);
            background-color: var(--bg-color);
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease 0.1s;
        }
        
        .login-btn:hover {
            background-color: var(--text-color);
            color: var(--bg-color);
            transition: all 0.3s ease 0s;
        }
        
        /* Account Dropdown */
        .account-dropdown {
            position: relative;
        }
        
        .account-dropdown-toggle {
            cursor: pointer;
            position: relative;
        }
        
        .account-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background-color: var(--bg-color);
            border: 2px solid var(--border-color);
            min-width: 150px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .account-dropdown.active .account-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .account-dropdown-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            text-decoration: none;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border-color);
        }
        
        .account-dropdown-menu a:last-child {
            border-bottom: none;
        }
        
        .account-dropdown-menu a:hover {
            background-color: var(--text-color);
            color: var(--bg-color);
        }
        
        /* Layout */
        main {
            /* Ensure main area at least fills viewport minus header */
            min-height: calc(100vh - 72px);
        }

        /* Hero section fills the visible screen under the navbar */
        .hero-section {
            min-height: calc(100vh - 72px); /* 72px ≈ header height; keeps hero full screen */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 2rem;
        }

        /* Content Boxes */
        .content-box {
            border: 4px solid #000000;
            padding: 2rem;
            margin-bottom: 2rem;
            background-color: #ffffff;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            z-index: 1;
            text-decoration: none;
            color: inherit;
        }
        
        .content-box:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: inherit;
        }
        
        /* Remove hover pointer/transform for hero title box only */
        .hero-box {
            cursor: default;
        }
        
        .hero-box:hover {
            transform: none;
        }
        
        /* Ensure links inside content boxes don't have default link styling */
        .content-box,
        .content-box * {
            color: var(--text-color);
        }
        
        .content-box a {
            color: inherit;
            text-decoration: none;
        }
        
        /* When any box is hovered, dim all other boxes - subtle fade */
        body.box-hovered .content-box {
            background-color: #1a1a1a;
            border-color: #1a1a1a;
            opacity: 0.4;
            transition: all 0.3s ease;
        }
        
        /* Keep the hovered box white with black borders - fully visible */
        body.box-hovered .content-box:hover {
            background-color: #ffffff !important;
            border-color: #000000 !important;
            opacity: 1 !important;
        }
        
        /* Dim text in non-hovered boxes - subtle fade */
        body.box-hovered .content-box * {
            color: #666666 !important;
        }
        
        /* Keep text black and fully visible in hovered box */
        body.box-hovered .content-box:hover * {
            color: #000000 !important;
        }
        
        /* Keep tag squares black in hovered box */
        body.box-hovered .content-box:hover .tag-square {
            background-color: #000000 !important;
        }
        
        /* Dim tag squares in non-hovered boxes */
        body.box-hovered .content-box .tag-square {
            background-color: #666666 !important;
        }
        
        /* Box 1 - Large Hero Box */
        .hero-box {
            min-height: 360px; /* base height inside hero-section; overall hero height comes from .hero-section */
            padding-top: 2.5rem;
            padding-bottom: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-box-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            pointer-events: none;
        }
        
        .hero-box > div {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            height: 100%;
        }
        
        .box-title {
            font-family: 'Inter', sans-serif;
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 1rem;
            color: var(--text-color);
            transition: color 0.3s ease;
            position: relative;
        }
        
        .box-meta {
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        
        /* Hero box typography: use Ronzino and white text */
        .hero-box .box-title {
            font-family: 'Ronzino', 'AileronRegular', sans-serif;
            font-size: clamp(3rem, 5vw, 4.5rem);
            color: #ffffff;
        }
        
        .hero-box .box-meta {
            font-family: 'Ronzino', 'AileronRegular', sans-serif;
            font-size: clamp(1.1rem, 1.6vw, 1.4rem);
            color: #ffffff;
        }
        
        .box-tags {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .tag {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .tag-square {
            width: 12px;
            height: 12px;
            background-color: #000000;
            transition: background-color 0.3s ease;
        }
        
        /* Keep tag squares black inside boxes */
        .content-box .tag-square {
            background-color: #000000 !important;
        }
        
        /* Box 2 & 3 - Side by Side */
        .boxes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-top: -5vh; /* lift the two boxes ~5% of viewport height */
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .small-box {
            min-height: 380px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        
        /* Fullscreen adjustments - applied via JavaScript when in fullscreen */
        body.fullscreen-mode .small-box {
            height: calc(100vh - 28rem);
            min-height: 400px;
            max-height: 550px;
        }
        
        @media (min-height: 1100px) {
            body.fullscreen-mode .small-box {
                height: calc(100vh - 26rem);
                min-height: 450px;
                max-height: 600px;
            }
        }
        
        .small-box-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            pointer-events: none;
        }
        
        .small-box-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            z-index: 1;
            pointer-events: none;
        }
        
        .small-box > div {
            position: relative;
            z-index: 2;
        }
        
        /* White text for boxes with video backgrounds */
        .small-box .box-title,
        .small-box .box-meta,
        .small-box .tag span {
            color: #ffffff;
        }
        
        /* Keep text white on hover for small boxes */
        .small-box:hover .box-title,
        .small-box:hover .box-meta,
        .small-box:hover .tag span,
        body.box-hovered .small-box:hover .box-title,
        body.box-hovered .small-box:hover .box-meta,
        body.box-hovered .small-box:hover .tag span {
            color: #ffffff !important;
        }
        
        /* White tag squares for boxes with video backgrounds */
        .small-box .tag-square {
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }
        
        /* Keep tag squares white on hover */
        .small-box:hover .tag-square,
        body.box-hovered .small-box:hover .tag-square {
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }
        
        /* Markets section (hidden until Browse Markets is clicked) */
        .markets-section {
            display: none; /* do not take up layout space before reveal */
            padding: 3rem 2rem;
            opacity: 0;
            transform: translateY(40px);
            pointer-events: none;
            transition: opacity 0.6s ease, transform 0.6s ease;
            max-width: 1600px; /* Use more horizontal space, closer to test page */
            margin: 0 auto;
        }
        
        .markets-section.is-visible {
            display: block; /* now participates in layout */
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .markets-title {
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }
        
        .markets-subtitle {
            font-size: 1rem;
            opacity: 0.7;
            margin-bottom: 2.5rem;
        }
        
        /* Categories section (hidden until market is clicked) */
        .categories-section {
            display: none; /* Hidden by default, no layout space */
            padding: 3rem 2rem;
            opacity: 0;
            transform: translateY(40px);
            pointer-events: none;
            transition: opacity 0.6s ease, transform 0.6s ease;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .categories-section.is-visible {
            display: block; /* Now participates in layout */
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .categories-title {
            font-family: 'VT323', monospace;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }
        
        .categories-subtitle {
            font-size: 1rem;
            opacity: 0.7;
            margin-bottom: 2.5rem;
            color: var(--text-color);
        }
        
        /* Categories grid layout */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }
        
        .category-card {
            border: 4px solid #000000;
            padding: 2rem;
            background-color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
            position: relative;
            overflow: hidden;
        }
        
        .category-card-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .category-card:hover .category-card-video {
            opacity: 1;
        }
        
        .category-card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.15);
            z-index: 1;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .category-card:hover .category-card-overlay {
            opacity: 1;
        }
        
        .category-card > div:not(.category-card-video):not(.category-card-overlay) {
            position: relative;
            z-index: 2;
        }
        
        .categories-section.is-visible .category-card {
            animation: categoryCardFadeIn 0.5s ease forwards;
        }
        
        .categories-section.is-visible .category-card:nth-child(1) { animation-delay: 0.1s; }
        .categories-section.is-visible .category-card:nth-child(2) { animation-delay: 0.2s; }
        .categories-section.is-visible .category-card:nth-child(3) { animation-delay: 0.3s; }
        .categories-section.is-visible .category-card:nth-child(4) { animation-delay: 0.4s; }
        .categories-section.is-visible .category-card:nth-child(5) { animation-delay: 0.5s; }
        .categories-section.is-visible .category-card:nth-child(6) { animation-delay: 0.6s; }
        .categories-section.is-visible .category-card:nth-child(7) { animation-delay: 0.7s; }
        .categories-section.is-visible .category-card:nth-child(8) { animation-delay: 0.8s; }
        
        @keyframes categoryCardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .category-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .category-name {
            font-family: 'VT323', monospace;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-color);
            text-transform: uppercase;
        }
        
        .category-product-count {
            font-size: 1rem;
            opacity: 0.8;
            color: var(--text-color);
        }
        
        /* White text for category cards with video background - only on hover */
        .category-card.has-video:hover .category-name,
        .category-card.has-video:hover .category-product-count {
            color: #ffffff;
        }
        
        /* Modern browser support with :has() */
        .category-card:has(.category-card-video):hover .category-name,
        .category-card:has(.category-card-video):hover .category-product-count {
            color: #ffffff;
        }
        
        /* Products section (hidden until category is clicked) */
        .products-section {
            display: none; /* Hidden by default, no layout space */
            padding: 3rem 2rem;
            opacity: 0;
            transform: translateY(40px);
            pointer-events: none;
            transition: opacity 0.6s ease, transform 0.6s ease;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .products-section.is-visible {
            display: block; /* Now participates in layout */
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .products-title {
            font-family: 'VT323', monospace;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }
        
        .products-subtitle {
            font-size: 1rem;
            opacity: 0.7;
            margin-bottom: 2.5rem;
            color: var(--text-color);
        }

        .products-search {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .products-search input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #000000;
            background-color: #ffffff;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            color: #000000;
        }

        .products-search input:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.15);
        }

        .products-search-clear {
            padding: 0.75rem 1rem;
            border: 2px solid #000000;
            background-color: #ffffff;
            color: #000000;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .products-search-clear:hover {
            background-color: #000000;
            color: #ffffff;
        }

        /* Desktop: keep search bar from stretching too wide */
        @media (min-width: 769px) {
            .products-search {
                max-width: 640px;
                margin: 0 auto 1.5rem;
            }
        }
        
        /* Products grid layout */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 277px));
            gap: 1.5rem;
        }
        
        .product-card {
            border: 4px solid #000000;
            padding: 1.5rem;
            background-color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .products-section.is-visible .product-card {
            animation: productCardFadeIn 0.5s ease forwards;
        }
        
        .products-section.is-visible .product-card:nth-child(1) { animation-delay: 0.1s; }
        .products-section.is-visible .product-card:nth-child(2) { animation-delay: 0.15s; }
        .products-section.is-visible .product-card:nth-child(3) { animation-delay: 0.2s; }
        .products-section.is-visible .product-card:nth-child(4) { animation-delay: 0.25s; }
        .products-section.is-visible .product-card:nth-child(5) { animation-delay: 0.3s; }
        .products-section.is-visible .product-card:nth-child(6) { animation-delay: 0.35s; }
        .products-section.is-visible .product-card:nth-child(7) { animation-delay: 0.4s; }
        .products-section.is-visible .product-card:nth-child(8) { animation-delay: 0.45s; }
        .products-section.is-visible .product-card:nth-child(n+9) { animation-delay: 0.5s; }
        
        @keyframes productCardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border: 2px solid #000000;
            margin-bottom: 1rem;
            background-color: #f0f0f0;
        }
        
        .product-name {
            font-family: 'VT323', monospace;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            text-transform: uppercase;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }
        
        .product-unit {
            font-size: 0.9rem;
            opacity: 0.7;
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }
        
        .product-description {
            font-size: 0.85rem;
            opacity: 0.6;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-unit-options {
            margin-bottom: 1rem;
            padding-top: 0.5rem;
            border-top: 2px solid #000000;
        }
        
        .product-unit-options-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            opacity: 0.7;
            color: var(--text-color);
        }
        
        .product-unit-option {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem 0.25rem 0.25rem 0;
            border: 2px solid #000000;
            background-color: #000000;
            font-size: 0.75rem;
            color: #ffffff;
        }
        
        .product-unit-option.is-default {
            background-color: #000000;
            color: #ffffff;
        }
        
        .product-reserve-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 4px solid #000000;
            background-color: #ffffff;
            color: #000000;
            font-family: 'VT323', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        .product-reserve-btn:hover {
            background-color: #000000;
            color: #ffffff;
        }

        .product-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .product-actions .product-reserve-btn,
        .product-actions .product-history-btn {
            width: 100%;
            margin-top: 0;
        }
        
        /* Products section (hidden until category is clicked) */
        .products-section {
            display: none; /* Hidden by default, no layout space */
            padding: 3rem 2rem;
            opacity: 0;
            transform: translateY(40px);
            pointer-events: none;
            transition: opacity 0.6s ease, transform 0.6s ease;
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .products-section.is-visible {
            display: block; /* Now participates in layout */
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        
        .products-title {
            font-family: 'VT323', monospace;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }
        
        .products-subtitle {
            font-size: 1rem;
            opacity: 0.7;
            margin-bottom: 2.5rem;
            color: var(--text-color);
        }
        
        /* Products grid layout */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 277px));
            gap: 1.5rem;
        }
        
        .product-card {
            border: 4px solid #000000;
            padding: 1.5rem;
            background-color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .products-section.is-visible .product-card {
            animation: productCardFadeIn 0.5s ease forwards;
        }
        
        .products-section.is-visible .product-card:nth-child(1) { animation-delay: 0.1s; }
        .products-section.is-visible .product-card:nth-child(2) { animation-delay: 0.15s; }
        .products-section.is-visible .product-card:nth-child(3) { animation-delay: 0.2s; }
        .products-section.is-visible .product-card:nth-child(4) { animation-delay: 0.25s; }
        .products-section.is-visible .product-card:nth-child(5) { animation-delay: 0.3s; }
        .products-section.is-visible .product-card:nth-child(6) { animation-delay: 0.35s; }
        .products-section.is-visible .product-card:nth-child(7) { animation-delay: 0.4s; }
        .products-section.is-visible .product-card:nth-child(8) { animation-delay: 0.45s; }
        .products-section.is-visible .product-card:nth-child(n+9) { animation-delay: 0.5s; }
        
        @keyframes productCardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border: 2px solid #000000;
            margin-bottom: 1rem;
            background-color: #f0f0f0;
        }
        
        .product-name {
            font-family: 'VT323', monospace;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            text-transform: uppercase;
        }
        
        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }
        
        .product-unit {
            font-size: 0.9rem;
            opacity: 0.7;
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }
        
        .product-description {
            font-size: 0.85rem;
            opacity: 0.6;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .product-unit-options {
            margin-bottom: 1rem;
            padding-top: 0.5rem;
            border-top: 2px solid #000000;
        }
        
        .product-unit-options-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            opacity: 0.7;
            color: var(--text-color);
        }
        
        .product-unit-option {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem 0.25rem 0.25rem 0;
            border: 2px solid #000000;
            background-color: #000000;
            font-size: 0.75rem;
            color: #ffffff;
        }
        
        .product-unit-option.is-default {
            background-color: #000000;
            color: #ffffff;
        }
        
        .product-reserve-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 4px solid #000000;
            background-color: #ffffff;
            color: #000000;
            font-family: 'VT323', monospace;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        .product-reserve-btn:hover {
            background-color: #000000;
            color: #ffffff;
        }
        
        /* Notification Icon - Top Right Corner (Small & Subtle) */
        .product-card {
            position: relative;
        }
        
        .product-alert-btn {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 28px;
            height: 28px;
            background-color: #ffffff;
            border: 2px solid #000000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            opacity: 1;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .product-alert-btn:hover {
            transform: scale(1.1);
        }
        
        .product-alert-btn svg {
            width: 14px;
            height: 14px;
            color: #000000;
        }
        
        /* Tooltip for notification icon */
        .product-alert-btn::after {
            content: 'Set price alert - Get notified when price changes';
            position: absolute;
            bottom: 100%;
            right: 0;
            margin-bottom: 8px;
            padding: 0.5rem 0.75rem;
            background-color: #000000;
            color: #ffffff;
            font-family: 'VT323', monospace;
            font-size: 0.75rem;
            white-space: nowrap;
            border: 2px solid #000000;
            opacity: 0;
            pointer-events: none;
            transform: translateY(5px);
            transition: all 0.2s ease;
            z-index: 100;
        }
        
        .product-alert-btn::before {
            content: '';
            position: absolute;
            bottom: 100%;
            right: 12px;
            margin-bottom: 2px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #000000;
            opacity: 0;
            pointer-events: none;
            transform: translateY(5px);
            transition: all 0.2s ease;
            z-index: 101;
        }
        
        .product-alert-btn:hover::after,
        .product-alert-btn:hover::before {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Make bell icon clickable */
        .product-alert-btn {
            cursor: pointer;
        }
        
        /* Price Alert Modal */
        .price-alert-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            font-family: 'VT323', monospace;
        }
        
        .price-alert-modal.active {
            display: flex;
        }
        
        .price-alert-modal-content {
            background-color: #ffffff;
            border: 4px solid #000000;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .price-alert-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #000000;
        }
        
        .price-alert-modal-header h2 {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        
        .price-alert-modal-close {
            background: none;
            border: 2px solid #000000;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .price-alert-modal-close:hover {
            background-color: #000000;
            color: #ffffff;
        }
        
        .price-alert-product-info {
            background-color: #f5f5f5;
            border: 2px solid #000000;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .price-alert-product-info .product-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .price-alert-product-info .product-price {
            font-size: 1rem;
            color: #666;
        }
        
        .price-alert-form-group {
            margin-bottom: 1.5rem;
        }
        
        .price-alert-form-group label {
            display: block;
            font-size: 1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        
        .price-alert-form-group input,
        .price-alert-form-group select,
        .price-alert-form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #000000;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            background-color: #ffffff;
            box-sizing: border-box;
        }
        
        .price-alert-form-group textarea {
            resize: vertical;
            min-height: 80px;
            font-family: 'VT323', monospace;
        }
        
        .price-alert-form-group input:focus,
        .price-alert-form-group select:focus,
        .price-alert-form-group textarea:focus {
            outline: none;
            border-color: #000000;
        }
        
        .price-alert-form-group small {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: #666;
        }
        
        .price-alert-form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .price-alert-btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: 2px solid #000000;
            background-color: #ffffff;
            color: #000000;
            font-family: 'VT323', monospace;
            font-size: 1rem;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .price-alert-btn:hover {
            background-color: #000000;
            color: #ffffff;
        }
        
        .price-alert-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .price-alert-btn-primary {
            background-color: #000000;
            color: #ffffff;
        }
        
        .price-alert-btn-primary:hover {
            background-color: #333333;
        }
        
        .price-alert-message {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 2px solid #000000;
            font-size: 0.9rem;
        }
        
        .price-alert-message.success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .price-alert-message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Horizontal market card layout */
        .market-grid {
            display: grid;
            /* Match test page behaviour: cards grow to use space */
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .market-card {
            border: 4px solid #000000;
            padding: 2rem;
            background-color: #ffffff;
            /* Default resting state */
            transform: translateY(0) rotate(0deg);
            opacity: 1;
            transition: box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .market-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        /* Before reveal, keep cards hidden */
        .markets-section:not(.is-visible) .market-card {
            opacity: 0;
        }
        
        /* Dangling cardboard pop animation - uses CSS variables for randomness */
        @keyframes marketCardDangling {
            0% {
                opacity: 0;
                transform: translateY(var(--start-y, -20px)) rotate(var(--start-rotate, -2deg));
            }
            60% {
                opacity: 1;
                transform: translateY(var(--mid-y, 4px)) rotate(var(--mid-rotate, 1deg));
            }
            85% {
                transform: translateY(var(--bounce-y, -2px)) rotate(var(--bounce-rotate, -0.5deg));
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotate(0deg);
            }
        }
        
        /* Hover re-dangle animation - faster and more responsive */
        @keyframes marketCardDanglingHover {
            0% {
                transform: translateY(0) rotate(0deg);
            }
            40% {
                transform: translateY(4px) rotate(var(--hover-rotate-1, 1deg));
            }
            70% {
                transform: translateY(-2px) rotate(var(--hover-rotate-2, -0.5deg));
            }
            100% {
                transform: translateY(0) rotate(0deg);
            }
        }
        
        .markets-section.is-visible .market-card {
            animation: marketCardDangling 0.6s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
        }
        
        /* On hover, replay a light dangling animation - faster, no delay */
        .markets-section.is-visible .market-card:hover {
            animation: marketCardDanglingHover 0.3s cubic-bezier(0.22, 0.61, 0.36, 1) forwards;
            animation-delay: 0s !important;
        }
        
        .market-id {
            font-size: 1.2rem;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
        }
        
        .market-name {
            font-family: 'Inter', sans-serif;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .market-address {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 0.5rem;
        }
        
        .market-hours {
            font-size: 0.9rem;
            opacity: 0.7;
            margin-bottom: 1rem;
        }
       
        /* Distance pill + divider row, matching reference layout */
        .market-distance-row {
            margin-bottom: 1.25rem;
        }

        .market-distance-pill {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border: 2px solid #000000;
            background: transparent;
            font-family: 'VT323', monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            cursor: default;
        }

        .market-divider {
            border-bottom: 2px solid #000000;
            margin-bottom: 1.25rem;
        }
        
        .market-stats {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        /* Dim dark background on hover - only affects body background */
        body.theme-dim {
            --bg-color: #1a1a1a;
        }
        
        /* Dim header and footer when hovering over boxes - subtle fade */
        body.box-hovered .header,
        body.box-hovered .nav a,
        body.box-hovered .logo,
        body.box-hovered .icon-box {
            color: #666666;
            border-color: #666666;
            opacity: 0.5;
            transition: all 0.3s ease;
        }
        
        /* Tablet: 2 cards per row */
        @media (max-width: 1024px) and (min-width: 769px) {
            .market-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Mobile: 1 card per row */
        /* Hamburger Menu Styles - visible on mobile, hidden on desktop */
        .hamburger-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 0.5rem;
            background: none;
            border: 2px solid var(--border-color, #000000);
            min-width: 44px;
            min-height: 44px;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }
        /* Hide hamburger on desktop (show regular nav instead) */
        @media (min-width: 769px) {
            .hamburger-menu {
                display: none !important;
            }
        }
        
        .hamburger-menu:hover {
            background-color: var(--text-color, #000000);
        }
        
        .hamburger-menu:hover .hamburger-line {
            background-color: var(--bg-color, #ffffff);
        }
        
        .hamburger-line {
            width: 24px;
            height: 3px;
            background-color: var(--text-color, #000000);
            transition: all 0.3s ease;
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(7px, 7px);
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }
        
        /* Mobile Menu Overlay */
        .mobile-menu-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            z-index: 9998;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .mobile-menu-overlay.active {
            display: block;
            opacity: 1;
        }
        
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 320px;
            height: 100%;
            background-color: var(--bg-color, #ffffff);
            border-left: 3px solid var(--border-color, #000000);
            z-index: 9999;
            transition: right 0.3s ease;
            overflow-y: auto;
            padding: 2rem 1.5rem;
            font-family: 'VT323', monospace;
        }
        
        .mobile-menu.active {
            right: 0;
        }
        
        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color, #000000);
        }
        
        .mobile-menu-header .logo {
            font-family: 'InterDisplay', system-ui, sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
        }
        
        .mobile-menu-close {
            background: none;
            border: 2px solid var(--border-color, #000000);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-color, #000000);
            transition: all 0.3s ease;
        }
        
        .mobile-menu-close:hover {
            background-color: var(--text-color, #000000);
            color: var(--bg-color, #ffffff);
        }
        
        .mobile-menu-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .mobile-menu-nav a {
            display: block;
            padding: 1rem;
            border: 2px solid var(--border-color, #000000);
            color: var(--text-color, #000000);
            text-decoration: none;
            font-size: 1.1rem;
            text-transform: uppercase;
            transition: all 0.3s ease;
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        
        .mobile-menu-nav a:hover,
        .mobile-menu-nav a.active {
            background-color: var(--text-color, #000000);
            color: var(--bg-color, #ffffff);
        }
        
        .mobile-menu-nav .login-btn {
            margin-top: 1rem;
        }
        
        .mobile-menu-nav .account-dropdown {
            width: 100%;
        }
        
        .mobile-menu-nav .account-dropdown-toggle {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color, #000000);
            text-align: left;
        }
        
        .mobile-menu-nav .account-dropdown-menu {
            position: static;
            opacity: 1;
            visibility: visible;
            transform: none;
            box-shadow: none;
            border: none;
            border-top: 2px solid var(--border-color, #000000);
            margin-top: 0.5rem;
            display: block;
        }
        
        .mobile-menu-nav .account-dropdown-menu a {
            border: none;
            border-bottom: 1px solid var(--border-color, #000000);
            border-radius: 0;
        }
        
        .mobile-menu-footer {
            display: none;
        }
        
        /* Mobile: full-screen slide-down menu (same as home page) */
        @media (max-width: 768px) {
            .mobile-menu-overlay {
                display: none !important;
            }
            .mobile-menu {
                top: 0;
                left: 0;
                right: auto;
                width: 100%;
                max-width: none;
                height: 100%;
                transform: translateY(-100%);
                transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                border-left: none;
                padding: 0;
                display: flex;
                flex-direction: column;
                pointer-events: none;
            }
            .mobile-menu.active {
                transform: translateY(0);
                pointer-events: auto;
            }
            .mobile-menu-header {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                justify-content: flex-end;
                padding: 1.5rem 1.5rem 0;
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }
            .mobile-menu-header .logo {
                display: none;
            }
            .mobile-menu-nav {
                flex: 1;
                justify-content: center;
                align-items: flex-start;
                padding: 4rem 1.5rem 3rem;
                gap: 1.25rem;
            }
            .mobile-menu-nav a,
            .mobile-menu-nav .account-dropdown-toggle,
            .mobile-menu-nav .login-btn,
            .mobile-menu-nav .notification-bell {
                display: block;
                width: 100%;
                border: none;
                padding: 0;
                margin: 0;
                min-height: auto;
                font-size: clamp(1.75rem, 6vw, 2.5rem);
                line-height: 1.2;
                letter-spacing: -0.03em;
                text-transform: uppercase;
                text-align: left;
            }
            .mobile-menu-nav .notification-bell {
                background: transparent;
                appearance: none;
                -webkit-appearance: none;
                font-family: inherit;
                cursor: pointer;
                color: var(--text-color, #000000);
            }
            .mobile-menu-nav a:hover {
                background-color: transparent;
            }
            .mobile-menu-nav a.active {
                background-color: transparent;
                color: var(--text-color, #000000);
                text-decoration: underline;
                text-underline-offset: 0.25em;
            }
            .mobile-menu-nav .login-btn {
                margin-top: 0;
            }
            .mobile-menu-nav .account-dropdown-toggle {
                padding: 0;
                border: none;
            }
            .mobile-menu-nav .account-dropdown-menu {
                padding-top: 0.75rem;
            }
            .mobile-menu-nav .account-dropdown-menu a {
                font-size: clamp(1.75rem, 6vw, 2.5rem);
                line-height: 1.2;
                letter-spacing: -0.03em;
                padding: 0;
                border: none;
                min-height: auto;
            }
            .mobile-menu-footer {
                display: block;
                flex-shrink: 0;
                padding: 2rem 1.5rem 2.5rem;
            }
            .mobile-menu-footer-brand {
                font-size: clamp(1.5rem, 5vw, 2rem);
                font-weight: bold;
                text-transform: uppercase;
                letter-spacing: 0.02em;
                margin-bottom: 0.75rem;
            }
            .mobile-menu-footer-meta {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .mobile-menu-footer-meta a {
                text-decoration: none;
                color: inherit;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 1rem;
                gap: 1rem;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .logo img {
                height: 1.5rem;
            }
            
            /* Hide regular nav on mobile */
            .nav {
                display: none !important;
            }
            
            /* Show hamburger menu */
            .hamburger-menu {
                display: flex !important;
            }
            
            .login-btn {
                min-height: 44px; /* Touch-friendly */
                padding: 0.5rem 1rem;
            }
            
            .boxes-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .box-title {
                font-size: 2rem;
            }
            
            /* Hero boxes: scale text and padding for small viewports */
            .hero-box .box-title {
                font-size: clamp(1.5rem, 6vw, 2.5rem);
            }
            .hero-box .box-meta {
                font-size: clamp(0.9rem, 2.5vw, 1.1rem);
            }
            
            .market-grid {
                grid-template-columns: 1fr;
            }
            
            .product-card {
                padding: 1rem;
            }
            
            .product-card .product-name {
                font-size: 1.2rem;
            }
            
            .product-card .product-price {
                font-size: 1.1rem;
            }
            
            .filter-section {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filter-section select,
            .filter-section input {
                width: 100%;
                min-height: 44px; /* Touch-friendly */
            }
            
            .filter-section button {
                width: 100%;
                min-height: 44px; /* Touch-friendly */
            }

            /* Products: tighter and more readable on mobile */
            .products-section {
                padding: 2rem 1rem;
            }

            .products-title {
                font-size: clamp(1.5rem, 5vw, 2.25rem);
                margin-bottom: 0.25rem;
            }

            .products-subtitle {
                font-size: 0.95rem;
                margin-bottom: 1.5rem;
            }

            .products-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .product-image {
                height: 160px;
            }

            .products-search {
                flex-direction: column;
                align-items: stretch;
            }

            .products-search input,
            .products-search-clear {
                width: 100%;
                min-height: 44px;
            }

            .product-unit-options {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .product-unit-option {
                min-height: 36px;
                padding: 0.4rem 0.6rem;
            }

            .product-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
            }

            .product-actions .product-reserve-btn,
            .product-actions .product-history-btn {
                min-height: 44px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }
            
            .header {
                padding: 0.5rem 0.75rem;
            }
            
            /* Keep nav hidden on all mobile sizes */
            .nav {
                display: none !important;
            }
            
            /* Keep hamburger menu visible */
            .hamburger-menu {
                display: flex !important;
            }
            
            .box-title {
                font-size: 1.75rem;
            }
            
            /* Extra-small: tighter hero and touch targets */
            .hero-box {
                min-height: 280px;
                padding: 1.25rem;
            }
            .hero-box .box-title {
                font-size: clamp(1.25rem, 5vw, 1.75rem);
            }
            .boxes-grid {
                gap: 0.75rem;
            }

            /* Products: extra-small tweaks */
            .products-section {
                padding: 1.5rem 0.75rem;
            }

            .product-card {
                padding: 0.9rem;
                border-width: 3px;
            }

            .product-image {
                height: 140px;
            }

            .product-card .product-name {
                font-size: 1.1rem;
            }

            .product-card .product-price {
                font-size: 1rem;
            }

            .product-actions {
                grid-template-columns: 1fr;
            }

            .product-alert-btn {
                width: 32px;
                height: 32px;
            }
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
    }
    
    .price-history-stat {
        border: 2px solid #000000;
        padding: 0.75rem;
        background-color: #ffffff;
        text-align: center;
    }
    
    .price-history-stat-label {
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 0.25rem;
        font-family: 'VT323', monospace;
        text-transform: uppercase;
    }
    
    .price-history-stat-value {
        font-size: 1.25rem;
        font-weight: bold;
        color: #000000;
        font-family: 'VT323', monospace;
    }
    
    .price-history-loading {
        text-align: center;
        padding: 3rem;
    }
    
    .price-history-loading .loading-spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #000000;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .price-history-error {
        text-align: center;
        padding: 2rem;
        color: #dc2626;
    }
    </style>
    <link rel="stylesheet" href="css/category-carousel.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <img src="assets/images/gif-wazulafu-no-bg.gif" alt="FarmScout" class="logo-gif" onerror="this.style.display='none';">
            FARMSCOUT
        </div>
        
        <!-- Hamburger Menu Button (Mobile Only) -->
        <button class="hamburger-menu" id="hamburgerMenu" onclick="toggleMobileMenu()" aria-label="Toggle menu">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            </button>
        
        <nav class="nav">
            <?php if (isset($_SESSION['user_role'])): ?>
                <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                    <a href="farmer-dashboard.php" class="<?php echo ($current_page == 'farmer-dashboard.php') ? 'active' : ''; ?>">FARMER DASHBOARD</a>
                <?php elseif (isAdminUser()): ?>
                    <a href="admin-console.php" class="<?php echo ($current_page == 'admin-console.php') ? 'active' : ''; ?>"><?php echo getAdminDashboardLabel(); ?></a>
                <?php else: ?>
                    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">HOME</a>
                    <a href="market-finder.php" class="<?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>">MARKET FINDER</a>
                    <a href="price-alerts.php" class="<?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>">PRICE ALERTS</a>
                <?php endif; ?>
                <?php else: ?>
                <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">HOME</a>
                <a href="market-finder.php" class="<?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>">MARKET FINDER</a>
                <a href="price-alerts.php" class="<?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>">PRICE ALERTS</a>
                <?php endif; ?>
            
            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'farmer'): ?>
                <a href="manage-products.php" class="<?php echo ($current_page == 'manage-products.php') ? 'active' : ''; ?>">MANAGE PRODUCTS</a>
                        <?php endif; ?>
            
            <div class="nav-icons">
                <div class="icon-box"></div>
                <div class="icon-box"></div>
                <div class="icon-box"></div>
    </div>
    
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="account-dropdown">
                    <a href="user-account.php" class="account-dropdown-toggle" onclick="event.preventDefault(); toggleAccountDropdown(event);">ACCOUNT</a>
                    <div class="account-dropdown-menu">
                        <a href="user-account.php">MY ACCOUNT</a>
                        <a href="logout.php">LOGOUT</a>
        </div>
        </div>
    <?php else: ?>
                <a href="login.php" class="login-btn">LOGIN</a>
            <?php endif; ?>
        </nav>
    </header>
    
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

    <?php include __DIR__ . '/includes/mobile-menu-panel.php'; ?>
    
    <!-- Main Content -->
    <main>
        <!-- Hero Section (fills viewport) -->
        <section class="hero-section">
            <!-- Hero Box -->
            <div class="content-box hero-box" id="titleBox">
            <video class="hero-box-video" autoplay loop muted playsinline preload="auto">
                <source src="assets/video/farming_frogs.mp4" type="video/mp4">
                Your browser does not support the video tag.
            </video>
                        <div>
                <h1 class="box-title">MARKET FINDER</h1>
                <div class="box-meta hero-subtitle">Discover and explore public markets across La Union</div>
    </div>
                </div>
            
            <!-- Grid Boxes -->
            <div class="boxes-grid">
            <div class="content-box small-box" id="browseMarketsOption" data-theme="blue">
                <video class="small-box-video" autoplay loop muted playsinline preload="auto">
                    <source src="assets/video/browsemarket.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="small-box-overlay"></div>
        <div>
                    <h2 class="box-title">BROWSE MARKETS</h2>
                    <div class="box-meta">View all public markets in La Union</div>
                    <div class="box-tags">
                        <div class="tag">
                            <div class="tag-square"></div>
                            <span>LIST VIEW</span>
                </div>
                        <div class="tag">
                            <div class="tag-square"></div>
                            <span>FULL COVERAGE</span>
            </div>
        </div>
    </div>
    </div>
    
            <a href="market-map.php" class="content-box small-box" id="findNearestOption" data-theme="green">
                <video class="small-box-video" id="findNearestVideo" autoplay muted playsinline preload="auto">
                    <source src="assets/video/findnearestmarket.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="small-box-overlay"></div>
                        <div>
                    <h2 class="box-title">FIND NEAREST MARKET</h2>
                    <div class="box-meta">Locate the closest market to you using the interactive map</div>
                    <div class="box-tags">
                        <div class="tag">
                            <div class="tag-square"></div>
                            <span>MAP VIEW</span>
        </div>
                        <div class="tag">
                            <div class="tag-square"></div>
                            <span>GPS READY</span>
                            </div>
                        </div>
                        </div>
            </a>
                    </div>
</section>
    </main>
    
    <!-- Markets section (hidden until Browse Markets is clicked) -->
    <section class="markets-section" id="marketsSection">
        <h2 class="markets-title">SELECT A MARKET</h2>
        <p class="markets-subtitle">Choose a market to browse available products.</p>
        
        <div class="market-grid">
            <?php 
            $index = 1;
            foreach ($markets_with_distance as $market): 
                $binary_id = formatBinaryId($index);
                $is_open = isMarketCurrentlyOpen($market);
            ?>
            <div class="market-card" data-index="<?php echo $index; ?>" data-market-id="<?php echo $market['id']; ?>" data-market-name="<?php echo htmlspecialchars($market['market_name']); ?>">
                <div class="market-id"><?php echo $binary_id; ?></div>
                <h3 class="market-name"><?php echo htmlspecialchars($market['market_name']); ?></h3>
                            <p class="market-address"><?php echo htmlspecialchars($market['address']); ?></p>
                <p class="market-hours">
                    <?php echo htmlspecialchars($market['operating_hours'] ?? 'Monday-Sunday: 5:00 AM - 8:00 PM'); ?>
                    &nbsp;&mdash;&nbsp;<?php echo $is_open ? 'OPEN' : 'CLOSE'; ?>
                </p>
                        <?php if (isset($market['distance'])): ?>
                <div class="market-distance-row">
                    <button class="market-distance-pill">
                            <?php echo $market['distance']; ?> km away
                            </button>
                        </div>
                        <?php endif; ?>
                <div class="market-divider"></div>
                <div class="market-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $market['product_count'] ?? 0; ?></span>
                        <span class="stat-label">Products</span>
                    </div>
                </div>
        </div>
                            <?php 
                $index++;
            endforeach; 
            ?>
    </div>
</section>

    <!-- Categories section (hidden until a market is clicked) -->
    <section class="categories-section" id="categoriesSection">
        <h2 class="categories-title">PRODUCT CATEGORIES</h2>
        <p class="categories-subtitle">Browse products by category</p>
        
        <div class="categories-grid" id="categoriesGrid">
            <!-- Infinite gradient 3D category carousel injected via JavaScript -->
            </div>
</section>

    <!-- Products section (hidden until category is clicked) -->
    <section class="products-section" id="productsSection">
        <h2 class="products-title">PRODUCTS</h2>
        <p class="products-subtitle" id="productsSubtitle">Browse available products</p>

        <div class="products-search">
            <input type="text" id="productsSearchInput" placeholder="Search products..." aria-label="Search products">
            <button type="button" id="productsSearchClear" class="products-search-clear">Clear</button>
            </div>
            
        <div class="products-grid" id="productsGrid">
            <!-- Products will be loaded here via JavaScript -->
            </div>
</section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="js/category-gradient-carousel.js?v=<?php echo time(); ?>"></script>
    <script>
        // Detect fullscreen mode and add class to body
        function checkFullscreen() {
            const isFullscreen = document.fullscreenElement || 
                                 document.webkitFullscreenElement || 
                                 document.mozFullScreenElement || 
                                 document.msFullscreenElement ||
                                 (window.innerHeight >= window.screen.height * 0.95); // Fallback: if viewport is 95% of screen height
            
            if (isFullscreen) {
                document.body.classList.add('fullscreen-mode');
            } else {
                document.body.classList.remove('fullscreen-mode');
            }
        }
        
        // Check on load and resize
        checkFullscreen();
        window.addEventListener('resize', checkFullscreen);
        document.addEventListener('fullscreenchange', checkFullscreen);
        document.addEventListener('webkitfullscreenchange', checkFullscreen);
        document.addEventListener('mozfullscreenchange', checkFullscreen);
        document.addEventListener('MSFullscreenChange', checkFullscreen);
        
        // Get all content boxes EXCEPT the hero title box (no hover effects there)
        const boxes = document.querySelectorAll('.content-box:not(.hero-box)');
        const body = document.body;
        
        // Characters for random text effect
        const randomChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        // Function to generate random text
        function generateRandomText(length) {
            let result = '';
            for (let i = 0; i < length; i++) {
                result += randomChars.charAt(Math.floor(Math.random() * randomChars.length));
            }
            return result;
        }
        
        // Function to reveal text character by character
        function revealText(element, originalText, index = 0) {
            if (index < originalText.length) {
                const revealed = originalText.substring(0, index + 1);
                const random = generateRandomText(originalText.length - index - 1);
                element.textContent = revealed + random;
                
                // Speed up as we get closer to the end
                const delay = index < originalText.length * 0.7 ? 30 : 50;
                setTimeout(() => revealText(element, originalText, index + 1), delay);
            } else {
                element.textContent = originalText;
            }
        }
        
        // Store original text for each text element
        function storeOriginalTexts(box) {
            const textElements = box.querySelectorAll('.box-title, .box-meta, .tag span');
            textElements.forEach(el => {
                if (!el.dataset.originalText) {
                    el.dataset.originalText = el.textContent.trim();
                }
            });
        }
        
        // Function to start text reveal effect
        function startTextReveal(box) {
            const textElements = box.querySelectorAll('.box-title, .box-meta, .tag span');
            
            textElements.forEach((el, index) => {
                const originalText = el.dataset.originalText || el.textContent.trim();
                if (originalText) {
                    // Start with random text
                    el.textContent = generateRandomText(originalText.length);
                    
                    // Reveal after a short delay, staggered for each element
                    setTimeout(() => {
                        revealText(el, originalText);
                    }, index * 100);
                }
            });
        }
        
        // Add hover event listeners to each box
        boxes.forEach(box => {
            // Store original texts on first interaction
            storeOriginalTexts(box);
            
            let revealTimeout;
            
            box.addEventListener('mouseenter', function() {
                // Add class to body to trigger dimming of other boxes
                body.classList.add('box-hovered');
                // Also change body background to dim dark
                body.classList.add('theme-dim');
                
                // Start text reveal effect after a brief delay
                revealTimeout = setTimeout(() => {
                    startTextReveal(box);
                }, 100);
            });
            
            box.addEventListener('mouseleave', function() {
                // Remove classes when mouse leaves
                body.classList.remove('box-hovered');
                body.classList.remove('theme-dim');
                
                // Clear any pending reveal
                if (revealTimeout) {
                    clearTimeout(revealTimeout);
                }
                
                // Restore original text immediately
                const textElements = box.querySelectorAll('.box-title, .box-meta, .tag span');
                textElements.forEach(el => {
                    if (el.dataset.originalText) {
                        el.textContent = el.dataset.originalText;
                    }
                });
            });
        });
        
        // Market Finder functionality
        const browseOption = document.getElementById('browseMarketsOption');
        const marketsSection = document.getElementById('marketsSection');
        const marketCards = document.querySelectorAll('.market-card');
        
        // Prepare staggered animation delays and random dangling values for each market card
        marketCards.forEach((card, idx) => {
            // Staggered delay
            card.style.animationDelay = (idx * 120) + 'ms';
            
            // Generate random values for each card's dangling animation
            // Random starting position (slightly above)
            const startY = -15 - Math.random() * 10; // -15px to -25px
            const startRotate = -3 + Math.random() * 4; // -3deg to 1deg
            
            // Random mid-point (overshoot)
            const midY = 3 + Math.random() * 4; // 3px to 7px
            const midRotate = 0.5 + Math.random() * 2; // 0.5deg to 2.5deg
            
            // Random bounce back
            const bounceY = -1 - Math.random() * 2; // -1px to -3px
            const bounceRotate = -1 + Math.random() * 1; // -1deg to 0deg
            
            // Random hover values (smaller movement)
            const hoverRotate1 = 0.5 + Math.random() * 1.5; // 0.5deg to 2deg
            const hoverRotate2 = -0.3 - Math.random() * 0.7; // -0.3deg to -1deg
            
            // Apply as CSS custom properties
            card.style.setProperty('--start-y', startY + 'px');
            card.style.setProperty('--start-rotate', startRotate + 'deg');
            card.style.setProperty('--mid-y', midY + 'px');
            card.style.setProperty('--mid-rotate', midRotate + 'deg');
            card.style.setProperty('--bounce-y', bounceY + 'px');
            card.style.setProperty('--bounce-rotate', bounceRotate + 'deg');
            card.style.setProperty('--hover-rotate-1', hoverRotate1 + 'deg');
            card.style.setProperty('--hover-rotate-2', hoverRotate2 + 'deg');
        });
        
        // If user landed directly with #marketsSection in URL, reveal section immediately
        if (marketsSection && window.location.hash === '#marketsSection') {
            marketsSection.classList.add('is-visible');
        }

        function scrollToMarkets() {
            if (!marketsSection) return;
            marketsSection.classList.add('is-visible');

            // Wait a tick so layout/animation updates, then compute ideal scroll position
            setTimeout(() => {
                const header = document.querySelector('.header');
                const headerHeight = header ? header.offsetHeight : 0;
                const rect = marketsSection.getBoundingClientRect();
                const sectionHeight = rect.height;
                const viewportHeight = window.innerHeight;
                const verticalMargin = 24; // nice spacing above/below

                const availableHeight = viewportHeight - headerHeight - verticalMargin * 2;
                let top;

                // If the whole section can fit in the viewport, center it between navbar and bottom
                if (sectionHeight <= availableHeight) {
                    const extraSpace = availableHeight - sectionHeight;
                    const offset = headerHeight + verticalMargin + extraSpace / 2;
                    top = rect.top + window.pageYOffset - offset;
                } else {
                    // Otherwise, align the top just under the navbar
                    top = rect.top + window.pageYOffset - headerHeight - verticalMargin;
                }

                window.scrollTo({
                    top,
                    behavior: 'smooth'
                });
            }, 50);
        }

        if (browseOption && marketsSection) {
            browseOption.addEventListener('click', function () {
                const url = new URL(window.location.href);
                const hasLat = url.searchParams.has('lat');
                const hasLng = url.searchParams.has('lng');

                // If we already have lat/lng in URL, just reveal and scroll
                if (hasLat && hasLng) {
                    scrollToMarkets();
                    return;
                }

                // Try to get user's current location for accurate distances
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        position => {
                            const lat = position.coords.latitude.toFixed(6);
                            const lng = position.coords.longitude.toFixed(6);

                            const newUrl = new URL(window.location.href);
                            newUrl.searchParams.set('lat', lat);
                            newUrl.searchParams.set('lng', lng);
                            newUrl.hash = 'marketsSection';

                            // Reload page with user location so PHP can compute accurate distances
                            window.location.href = newUrl.toString();
                        },
                        () => {
                            // If user denies or error, fall back to simple scroll without distance
                            scrollToMarkets();
                        },
                        {
                            enableHighAccuracy: true,
                            timeout: 10000
                        }
                    );
                } else {
                    // Geolocation not supported – just show markets
                    scrollToMarkets();
                }
            });
        }
        
        // Market card click handler - show categories
        const categoriesSection = document.getElementById('categoriesSection');
        const categoriesGrid = document.getElementById('categoriesGrid');
        const productsSection = document.getElementById('productsSection');
        const productsGrid = document.getElementById('productsGrid');
        const productsSubtitle = document.getElementById('productsSubtitle');
        const productsSearchInput = document.getElementById('productsSearchInput');
        const productsSearchClear = document.getElementById('productsSearchClear');
        
        // Store selected market ID
        const deepLinkedMarketId = <?php echo json_encode($selected_market_id ? (string)$selected_market_id : ''); ?>;
        let hasAutoOpenedDeepLinkedMarket = false;
        let selectedMarketId = null;
        let selectedMarketName = null;
        let currentProducts = [];

        function getProductSearchText(product) {
            const parts = [
                product.filipino_name,
                product.name,
                product.description,
                product.unit,
                product.current_price,
                product.price
            ].filter(Boolean);
            return parts.join(' ').toLowerCase();
        }

        function renderProducts(products, emptyMessage) {
            if (!productsGrid) return;
            productsGrid.innerHTML = '';

            if (!products || products.length === 0) {
                const message = emptyMessage || 'No products found.';
                productsGrid.innerHTML = `<p style="grid-column: 1 / -1; text-align: center; padding: 2rem; opacity: 0.7;">${message}</p>`;
                return;
            }

            products.forEach((product) => {
                const productCard = document.createElement('div');
                productCard.className = 'product-card';

                // Set data attributes for price alert modal
                productCard.dataset.productId = product.id || product.product_id || '';
                productCard.dataset.productName = product.filipino_name || product.name || 'Product';
                // Extract numeric price from formatted string (remove currency symbols)
                let rawPrice = product.current_price || product.price || '0';
                if (typeof rawPrice === 'string') {
                    rawPrice = rawPrice.replace(/[₱P$,\s]/g, '').trim();
                }
                productCard.dataset.productPrice = parseFloat(rawPrice) || 0;

                const imageUrl = product.image_url || 'assets/images/placeholder-product.svg';

                // Build description HTML if available
                const descriptionHTML = product.description ?
                    `<div class="product-description">${product.description}</div>` : '';

                // Build unit options HTML if available
                let unitOptionsHTML = '';
                if (product.unit_options && product.unit_options.length > 0) {
                    unitOptionsHTML = '<div class="product-unit-options">';
                    unitOptionsHTML += '<div class="product-unit-options-title">Other Options:</div>';
                    product.unit_options.forEach(option => {
                        const isDefault = option.is_default ? ' is-default' : '';
                        const optionText = `${option.label || option.unit_label} - ₱${parseFloat(option.price).toFixed(2)}`;
                        unitOptionsHTML += `<span class="product-unit-option${isDefault}">${optionText}</span>`;
                    });
                    unitOptionsHTML += '</div>';
                }

                productCard.innerHTML = `
                    <div class="product-alert-btn" title="Price Alert Available">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </div>
                    <img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.src='assets/images/placeholder-product.svg'">
                    <div class="product-name">${product.filipino_name || product.name}</div>
                    ${descriptionHTML}
                    <div class="product-price">${product.current_price}</div>
                    <div class="product-unit">per ${product.unit}</div>
                    ${unitOptionsHTML}
                    <div class="product-actions">
                        <button class="product-reserve-btn">RESERVE</button>
                        <button class="product-history-btn" onclick="showPriceHistory(${product.id || product.product_id || 0}, '${(product.filipino_name || product.name || 'Product').replace(/'/g, "\\'")}')" style="padding: 0.75rem 1rem; border: 4px solid #000000; background-color: #ffffff; color: #000000; font-family: 'VT323', monospace; font-size: 1.1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; cursor: pointer; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#000000'; this.style.color='#ffffff'" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#000000'">HISTORY</button>
                        </div>
                `;

                productsGrid.appendChild(productCard);
            });
        }

        function applyProductSearch() {
            if (!productsSearchInput) return;
            const query = productsSearchInput.value.trim().toLowerCase();
            if (!query) {
                renderProducts(currentProducts, 'No products available in this category.');
                return;
            }
            const filtered = currentProducts.filter(product => getProductSearchText(product).includes(query));
            renderProducts(filtered, 'No matching products found.');
        }

        if (productsSearchInput) {
            productsSearchInput.addEventListener('input', applyProductSearch);
        }

        if (productsSearchClear) {
            productsSearchClear.addEventListener('click', () => {
                if (!productsSearchInput) return;
                productsSearchInput.value = '';
                applyProductSearch();
                productsSearchInput.focus();
            });
        }
        
        marketCards.forEach(card => {
            card.addEventListener('click', function() {
                const marketId = this.dataset.marketId;
                const marketName = this.dataset.marketName;
                
                if (!marketId) return;
                
                // Store selected market
                selectedMarketId = marketId;
                selectedMarketName = marketName;
                
                // Hide products section when a new market is selected
                if (productsSection) {
                    productsSection.classList.remove('is-visible');
                    productsGrid.innerHTML = '';
                    currentProducts = [];
                    if (productsSearchInput) {
                        productsSearchInput.value = '';
                    }
                }
                
                // Fetch categories for this market
                fetch(`api/get_market_categories.php?market_id=${marketId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.categories) {
                            if (typeof window.mfCategoryCarouselDestroy === 'function') {
                                window.mfCategoryCarouselDestroy();
                            }

                            if (!data.categories.length) {
                                categoriesGrid.innerHTML = '<p style="text-align:center;padding:2rem;font-family:VT323,monospace;">No product categories for this market yet.</p>';
                                if (categoriesSection) {
                                    categoriesSection.classList.add('is-visible');
                                }
                                return;
                            }

                            function mfCategoryCarouselImageUrl(category) {
                                const icon = (category.icon_path || '').trim();
                                if (icon) {
                                    return icon;
                                }
                                const n = (category.filipino_name || category.category_name || category.name || '').toLowerCase();
                                if (n.includes('prutas') || n.includes('fruit')) {
                                    return 'assets/images/hero images/annie-spratt-kr_88BakygA-unsplash.jpg';
                                }
                                if (n.includes('isda') || n.includes('fish') || n.includes('seafood')) {
                                    return 'assets/images/hero images/kyle-nieber-eE-ffApg7oI-unsplash.jpg';
                                }
                                if (n.includes('gulay') || n.includes('vegetable')) {
                                    return 'assets/images/hero images/mk-s-tHHFiw6GNEU-unsplash.jpg';
                                }
                                if (n.includes('processed') || n.includes('rice') || n.includes('bigas')) {
                                    return 'assets/images/hero images/tolga-ahmetler-HqbptzjszGs-unsplash.jpg';
                                }
                                if (n.includes('karne') || n.includes('meat')) {
                                    return 'assets/images/hero images/anne-preble-SAPvKo12dQE-unsplash.jpg';
                                }
                                return 'assets/images/hero images/ashley-winkler-cz4rC-IRfxw-unsplash.jpg';
                            }

                            const carouselHtml = `
                                <div class="mf-category-carousel-host" id="mfCategoryCarouselHost">
                                    <p class="mf-cat-hint">Drag or scroll the carousel — tap the center card to view products.</p>
                                    <div class="mf-cat-stage stage" id="mfCategoryStage">
                                        <div id="mfCategoryLoader" class="loader">
                                            <div class="loader__content">
                                                <div class="loader__ring" aria-hidden="true"></div>
                                            </div>
                                        </div>
                                        <canvas id="mfCategoryBg" aria-hidden="true"></canvas>
                                        <section id="mfCategoryCards" class="cards" aria-label="Product categories"></section>
                                    </div>
                                </div>`;

                            categoriesGrid.innerHTML = carouselHtml;

                            const imageUrls = data.categories.map(mfCategoryCarouselImageUrl);
                            const metaList = data.categories.map(function (c) {
                                return {
                                    category_id: c.category_id,
                                    category_name: c.category_name,
                                    filipino_name: c.filipino_name,
                                    name: c.name,
                                    product_count: c.product_count,
                                    icon_path: c.icon_path
                                };
                            });

                            function loadProductsForCategory(category) {
                                const categoryName = category.category_name || category.name || '';
                                const categoryFilipino = category.filipino_name || categoryName;
                                if (!selectedMarketId || (!categoryName && !categoryFilipino)) {
                                    return;
                                }
                                const categoryParam = encodeURIComponent(categoryFilipino || categoryName);
                                fetch(`api/get_market_products_by_category.php?market_id=${selectedMarketId}&category=${categoryParam}`)
                                    .then(response => response.json())
                                    .then(function (pdata) {
                                        if (pdata.success && pdata.products) {
                                            if (productsSubtitle) {
                                                productsSubtitle.textContent = (categoryFilipino || categoryName) + ' - ' + (selectedMarketName || 'Market');
                                            }
                                            currentProducts = pdata.products;
                                            if (productsSearchInput) {
                                                productsSearchInput.value = '';
                                            }
                                            renderProducts(currentProducts, 'No products available in this category.');
                                            if (productsSection) {
                                                productsSection.classList.add('is-visible');
                                                setTimeout(function () {
                                                    const header = document.querySelector('.header');
                                                    const headerHeight = header ? header.offsetHeight : 0;
                                                    const rect = productsSection.getBoundingClientRect();
                                                    const sectionHeight = rect.height;
                                                    const viewportHeight = window.innerHeight;
                                                    const verticalMargin = 24;
                                                    const availableHeight = viewportHeight - headerHeight - verticalMargin * 2;
                                                    var top;
                                                    if (sectionHeight <= availableHeight) {
                                                        var extraSpace = availableHeight - sectionHeight;
                                                        var offset = headerHeight + verticalMargin + extraSpace / 2;
                                                        top = rect.top + window.pageYOffset - offset;
                                                    } else {
                                                        top = rect.top + window.pageYOffset - headerHeight - verticalMargin;
                                                    }
                                                    window.scrollTo({ top: top, behavior: 'smooth' });
                                                }, 50);
                                            }
                                        }
                                    })
                                    .catch(function (error) {
                                        console.error('Error fetching products:', error);
                                    });
                            }

                            (async function () {
                                try {
                                    if (typeof window.mfCategoryCarouselStart === 'function') {
                                        await window.mfCategoryCarouselStart(imageUrls, metaList, {
                                            onSelect: loadProductsForCategory
                                        });
                                    }
                                    if (!hasAutoOpenedDeepLinkedMarket && deepLinkedMarketId && String(selectedMarketId) === String(deepLinkedMarketId) && metaList.length) {
                                        hasAutoOpenedDeepLinkedMarket = true;
                                        loadProductsForCategory(metaList[0]);
                                    }
                                } catch (e) {
                                    console.error('Category carousel failed:', e);
                                }
                            })();

                            // Show categories section and scroll to it
                            if (categoriesSection) {
                                categoriesSection.classList.add('is-visible');
                                
                                // Scroll to categories section
                                setTimeout(() => {
                                    const header = document.querySelector('.header');
                                    const headerHeight = header ? header.offsetHeight : 0;
                                    const rect = categoriesSection.getBoundingClientRect();
                                    const sectionHeight = rect.height;
                                    const viewportHeight = window.innerHeight;
                                    const verticalMargin = 24;
                                    
                                    const availableHeight = viewportHeight - headerHeight - verticalMargin * 2;
                                    let top;
                                    
                                    if (sectionHeight <= availableHeight) {
                                        const extraSpace = availableHeight - sectionHeight;
                                        const offset = headerHeight + verticalMargin + extraSpace / 2;
                                        top = rect.top + window.pageYOffset - offset;
                                    } else {
                                        top = rect.top + window.pageYOffset - headerHeight - verticalMargin;
                                    }
                                    
                                    window.scrollTo({
                                        top,
                                        behavior: 'smooth'
                                    });
                                }, 50);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching categories:', error);
                    });
            });
        });

        if (deepLinkedMarketId && marketsSection) {
            marketsSection.classList.add('is-visible');
            const deepLinkedCard = document.querySelector(`.market-card[data-market-id="${deepLinkedMarketId}"]`);
            if (deepLinkedCard) {
                setTimeout(() => {
                    deepLinkedCard.click();
                }, 120);
            }
        }
        
        // Loop find nearest market video at 56 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const findNearestVideo = document.getElementById('findNearestVideo');
            if (findNearestVideo) {
                findNearestVideo.addEventListener('timeupdate', function() {
                    if (this.currentTime >= 56) {
                        this.currentTime = 0;
                        this.play();
                    }
                });
            }
        });
        
    </script>
    
    <script>
    function toggleAccountDropdown(event) {
        event.stopPropagation();
        const dropdown = event.currentTarget.closest('.account-dropdown');
        const isActive = dropdown.classList.contains('active');
        
        // Close all dropdowns
        document.querySelectorAll('.account-dropdown').forEach(d => d.classList.remove('active'));
        
        // Toggle this dropdown
        if (!isActive) {
            dropdown.classList.add('active');
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.account-dropdown')) {
            document.querySelectorAll('.account-dropdown').forEach(d => d.classList.remove('active'));
        }
    });
    </script>
    
    <!-- Price Alert Modal -->
    <div id="priceAlertModal" class="price-alert-modal">
        <div class="price-alert-modal-content">
            <div class="price-alert-modal-header">
                <h2>SET PRICE ALERT</h2>
                <button class="price-alert-modal-close" onclick="closePriceAlertModal()">&times;</button>
                    </div>
            <div id="priceAlertMessage"></div>
            <div id="priceAlertProductInfo" class="price-alert-product-info"></div>
            <form id="priceAlertForm">
                <input type="hidden" id="priceAlertProductId" name="product_id">
                <input type="hidden" id="priceAlertCsrfToken" name="csrf_token">
                
                <div class="price-alert-form-group" id="priceAlertEmailGroup">
                    <label for="priceAlertEmail">Email Address</label>
                    <input type="email" id="priceAlertEmail" name="email" placeholder="your@email.com">
            </div>
                
                <div class="price-alert-form-group" id="priceAlertCaptchaGroup">
                    <label id="priceAlertCaptchaLabel">Security Check</label>
                    <input type="text" id="priceAlertCaptchaAnswer" name="captcha_answer" placeholder="Enter answer">
                    <small id="priceAlertCaptchaHint"></small>
        </div>
                
                <div class="price-alert-form-group">
                    <label for="priceAlertType">Alert When</label>
                    <select id="priceAlertType" name="alert_type">
                        <option value="below">Price drops below</option>
                        <option value="above">Price rises above</option>
                        <option value="change">Any price change</option>
                    </select>
                </div>
                
                <div class="price-alert-form-group" id="priceAlertTargetPriceGroup">
                    <label for="priceAlertTargetPrice">Target Price (₱)</label>
                    <input type="number" id="priceAlertTargetPrice" name="target_price" step="0.01" min="0" placeholder="0.00">
            </div>
                
                <div class="price-alert-form-actions">
                    <button type="button" class="price-alert-btn" onclick="closePriceAlertModal()">CANCEL</button>
                    <button type="submit" id="priceAlertSubmitBtn" class="price-alert-btn price-alert-btn-primary">SET ALERT</button>
                                    </div>
            </form>
                                    </div>
                                    </div>

<!-- Reservation Modal -->
<div id="reservationModal" class="price-alert-modal">
    <div class="price-alert-modal-content">
        <div class="price-alert-modal-header">
            <h2>RESERVE PRODUCT</h2>
            <button class="price-alert-modal-close" onclick="closeReservationModal()">&times;</button>
                                </div>
        <div id="reservationMessage"></div>
        <div id="reservationProductInfo" class="price-alert-product-info"></div>
        <form id="reservationForm">
            <input type="hidden" id="reservationProductId" name="product_id">
            <input type="hidden" id="reservationCsrfToken" name="csrf_token">
            
            <div class="price-alert-form-group">
                <label for="reservationQuantity">Quantity</label>
                <input type="number" id="reservationQuantity" name="quantity" step="0.01" min="0.01" value="1" placeholder="1" required>
                            </div>
            
            <div class="price-alert-form-group">
                <label for="reservationUnit">Unit</label>
                <select id="reservationUnit" name="unit" required>
                    <option value="">Select unit...</option>
                </select>
                        </div>
                        
            <div class="price-alert-form-group">
                <label for="reservationPickupDate">Preferred Pickup Date</label>
                <input type="date" id="reservationPickupDate" name="preferred_pickup_date" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                <small>You can reserve up to 30 days in advance</small>
                        </div>
            
            <div class="price-alert-form-group">
                <label for="reservationPickupTime">Preferred Pickup Time</label>
                <input type="time" id="reservationPickupTime" name="preferred_pickup_time">
                    </div>
                    
            <div class="price-alert-form-group">
                <label for="reservationNotes">Notes (Optional)</label>
                <textarea id="reservationNotes" name="notes" rows="4" placeholder="Any special requests or instructions..."></textarea>
                    </div>
                    
            <div class="price-alert-form-actions">
                <button type="button" class="price-alert-btn" onclick="closeReservationModal()">CANCEL</button>
                <button type="submit" id="reservationSubmitBtn" class="price-alert-btn price-alert-btn-primary">SUBMIT RESERVATION</button>
                        </div>
        </form>
                            </div>
                    </div>
                    
<script>
        // Price Alert Modal Functions
        const priceAlertModal = document.getElementById('priceAlertModal');
        const priceAlertForm = document.getElementById('priceAlertForm');
        const priceAlertMessage = document.getElementById('priceAlertMessage');
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const userEmail = '<?php echo htmlspecialchars($user_email, ENT_QUOTES); ?>';
        const csrfToken = '<?php echo getCSRFToken(); ?>';
        
        // Update target price requirement based on alert type
        document.getElementById('priceAlertType').addEventListener('change', function() {
            const targetPriceGroup = document.getElementById('priceAlertTargetPriceGroup');
            const targetPriceInput = document.getElementById('priceAlertTargetPrice');
            
            if (this.value === 'change') {
                targetPriceGroup.style.display = 'none';
                targetPriceInput.removeAttribute('required');
                targetPriceInput.value = '0';
            } else {
                targetPriceGroup.style.display = 'block';
                targetPriceInput.setAttribute('required', 'required');
            }
        });
        
        // Handle bell icon clicks
        document.addEventListener('click', function(event) {
            const alertBtn = event.target.closest('.product-alert-btn');
            if (alertBtn) {
                const productCard = alertBtn.closest('.product-card');
                if (productCard) {
                    const productId = productCard.dataset.productId;
                    const productName = productCard.dataset.productName || 'Product';
                    const productPrice = productCard.dataset.productPrice || '0';
                    
                    openPriceAlertModal(productId, productName, productPrice);
                }
            }
        });
        
        function openPriceAlertModal(productId, productName, productPrice) {
            console.log('Opening modal for product:', productId, productName, productPrice);
            
            // Set product info
            document.getElementById('priceAlertProductId').value = productId;
            document.getElementById('priceAlertProductInfo').innerHTML = `
                <div class="product-name">${productName}</div>
                <div class="product-price">Current Price: ${productPrice}</div>
            `;
            
            // Set CSRF token
            document.getElementById('priceAlertCsrfToken').value = csrfToken;
            
            // Handle logged-in vs anonymous users
            const emailGroup = document.getElementById('priceAlertEmailGroup');
            const captchaGroup = document.getElementById('priceAlertCaptchaGroup');
            const targetPriceGroup = document.getElementById('priceAlertTargetPriceGroup');
            const targetPriceInput = document.getElementById('priceAlertTargetPrice');
            const alertTypeSelect = document.getElementById('priceAlertType');
            
            if (isLoggedIn) {
                emailGroup.style.display = 'none';
                captchaGroup.style.display = 'none';
                // Remove required attributes for hidden fields
                document.getElementById('priceAlertEmail').removeAttribute('required');
                document.getElementById('priceAlertCaptchaAnswer').removeAttribute('required');
            } else {
                emailGroup.style.display = 'block';
                captchaGroup.style.display = 'block';
                // Add required attributes for visible fields
                document.getElementById('priceAlertEmail').setAttribute('required', 'required');
                document.getElementById('priceAlertCaptchaAnswer').setAttribute('required', 'required');
                // Fetch captcha question
                fetch('api/get_captcha_question.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('priceAlertCaptchaLabel').textContent = data.question;
                            document.getElementById('priceAlertCaptchaHint').textContent = 'Quick math challenge to keep bots away.';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching captcha:', error);
                    });
            }
            
            // Reset form
            priceAlertForm.reset();
            priceAlertMessage.innerHTML = '';
            
            // Reset alert type to default and show target price field
            alertTypeSelect.value = 'below';
            targetPriceGroup.style.display = 'block';
            targetPriceInput.setAttribute('required', 'required');
            targetPriceInput.value = '';
            
            // Ensure form is ready
            console.log('Modal opened, form ready');
            
            // Show modal
            priceAlertModal.classList.add('active');
        }
        
        function closePriceAlertModal() {
            priceAlertModal.classList.remove('active');
            priceAlertMessage.innerHTML = '';
            priceAlertForm.reset();
        }
        
        // Close modal when clicking outside
        priceAlertModal.addEventListener('click', function(event) {
            if (event.target === priceAlertModal) {
                closePriceAlertModal();
            }
        });
        
        // Handle form submission - both form submit and button click
        function submitPriceAlert() {
            console.log('submitPriceAlert called');
            
            // Clear previous messages
            priceAlertMessage.innerHTML = '';
            
            const submitBtn = document.getElementById('priceAlertSubmitBtn');
            if (!submitBtn) {
                console.error('Submit button not found');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'SETTING...';
            
            // Get form values
            const productId = document.getElementById('priceAlertProductId').value;
            const alertType = document.getElementById('priceAlertType').value;
            let targetPrice = document.getElementById('priceAlertTargetPrice').value || '0';
            
            // If alert type is "change", set target price to 0
            if (alertType === 'change') {
                targetPrice = '0';
            }
            
            const formData = {
                product_id: productId,
                alert_type: alertType,
                target_price: targetPrice,
                csrf_token: csrfToken
            };
            
            if (!isLoggedIn) {
                const email = document.getElementById('priceAlertEmail').value;
                const captchaAnswer = document.getElementById('priceAlertCaptchaAnswer').value;
                
                if (!email) {
                    priceAlertMessage.innerHTML = `<div class="price-alert-message error">Please enter your email address.</div>`;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'SET ALERT';
                    return;
                }
                
                if (!captchaAnswer) {
                    priceAlertMessage.innerHTML = `<div class="price-alert-message error">Please complete the security check.</div>`;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'SET ALERT';
                    return;
                }
                
                formData.email = email;
                formData.captcha_answer = captchaAnswer;
            }
            
            if (alertType !== 'change' && parseFloat(targetPrice) <= 0) {
                priceAlertMessage.innerHTML = `<div class="price-alert-message error">Please enter a valid target price.</div>`;
                submitBtn.disabled = false;
                submitBtn.textContent = 'SET ALERT';
                return;
            }
            
            console.log('Sending request:', formData);
            
            fetch('api/create_price_alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    priceAlertMessage.innerHTML = `<div class="price-alert-message success">${data.message}</div>`;
                    setTimeout(() => {
                        closePriceAlertModal();
                    }, 2000);
                } else {
                    priceAlertMessage.innerHTML = `<div class="price-alert-message error">${data.message}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'SET ALERT';
                    
                    // Refresh captcha if error
                    if (!isLoggedIn) {
                        fetch('api/get_captcha_question.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('priceAlertCaptchaLabel').textContent = data.question;
                                    document.getElementById('priceAlertCaptchaAnswer').value = '';
                                }
                            });
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                priceAlertMessage.innerHTML = `<div class="price-alert-message error">An error occurred. Please try again.</div>`;
                submitBtn.disabled = false;
                submitBtn.textContent = 'SET ALERT';
            });
        }
        
        // Handle form submission
        if (priceAlertForm) {
            priceAlertForm.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                console.log('Form submission started');
                submitPriceAlert();
            });
            
            // Also add click handler to submit button as fallback
            const submitBtn = document.getElementById('priceAlertSubmitBtn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    console.log('Submit button clicked');
                    submitPriceAlert();
                });
            }
        } else {
            console.error('Price alert form not found');
        }
        
        // Update product cards to include data attributes
        document.addEventListener('DOMContentLoaded', function() {
            // This will be handled when products are dynamically loaded
            // We'll update the product card creation code
        });
        
        // Reservation Modal Functions
        const reservationModal = document.getElementById('reservationModal');
        const reservationForm = document.getElementById('reservationForm');
        const reservationMessage = document.getElementById('reservationMessage');
        const reservationProductInfo = document.getElementById('reservationProductInfo');
        
        // Add event listener for RESERVE buttons (delegated)
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('product-reserve-btn') || event.target.closest('.product-reserve-btn')) {
                event.preventDefault();
                event.stopPropagation();
                
                const btn = event.target.classList.contains('product-reserve-btn') ? event.target : event.target.closest('.product-reserve-btn');
                const productCard = btn.closest('.product-card');
                
                if (!productCard) return;
                
                // Check if user is logged in
                if (!isLoggedIn) {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search) + '&message=' + encodeURIComponent('Please log in to reserve products.');
                    return;
                }
                
                const productId = productCard.dataset.productId;
                const productName = productCard.dataset.productName || 'Product';
                const productPrice = productCard.dataset.productPrice || '0';
                
                openReservationModal(productId, productName, productPrice, productCard);
            }
        });
        
        function openReservationModal(productId, productName, productPrice, productCard) {
            // Extract numeric value from price string (remove currency symbols and parse)
            let numericPrice = 0;
            if (productPrice) {
                // Remove currency symbols (₱, P, $) and any non-numeric characters except decimal point
                const cleanedPrice = String(productPrice).replace(/[₱P$,\s]/g, '').trim();
                numericPrice = parseFloat(cleanedPrice) || 0;
            }
            
            // Set product info
            reservationProductInfo.innerHTML = `
                <div class="price-alert-product-name">${productName}</div>
                <div class="price-alert-product-price">₱${numericPrice.toFixed(2)}</div>
            `;
            
            // Set hidden fields
            document.getElementById('reservationProductId').value = productId;
            document.getElementById('reservationCsrfToken').value = csrfToken;
            
            // Get unit options from product card
            const unitOptions = [];
            const unitOptionsEl = productCard.querySelector('.product-unit-options');
            if (unitOptionsEl) {
                unitOptionsEl.querySelectorAll('.product-unit-option').forEach(option => {
                    const text = option.textContent.trim();
                    const match = text.match(/^(.+?)\s*-\s*₱([\d.]+)$/);
                    if (match) {
                        unitOptions.push({
                            label: match[1].trim(),
                            price: parseFloat(match[2])
                        });
                    }
                });
            }
            
            // Get default unit from product card
            const defaultUnit = productCard.querySelector('.product-unit')?.textContent.replace('per ', '').trim() || 'kg';
            if (unitOptions.length === 0) {
                // Extract numeric price for unit option
                let numericPrice = 0;
                if (productPrice) {
                    const cleanedPrice = String(productPrice).replace(/[₱P$,\s]/g, '').trim();
                    numericPrice = parseFloat(cleanedPrice) || 0;
                }
                unitOptions.push({
                    label: defaultUnit,
                    price: numericPrice
                });
            }
            
            // Populate unit dropdown
            const unitSelect = document.getElementById('reservationUnit');
            unitSelect.innerHTML = '<option value="">Select unit...</option>';
            unitOptions.forEach(option => {
                const optionEl = document.createElement('option');
                optionEl.value = option.label;
                optionEl.textContent = `${option.label} - ₱${option.price.toFixed(2)}`;
                unitSelect.appendChild(optionEl);
            });
            
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            // Set min date (today) and max date (30 days from today)
            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + 30);
            const maxDateStr = maxDate.toISOString().split('T')[0];
            
            document.getElementById('reservationPickupDate').setAttribute('min', today);
            document.getElementById('reservationPickupDate').setAttribute('max', maxDateStr);
            
            // Clear form
            reservationForm.reset();
            document.getElementById('reservationQuantity').value = '1';
            reservationMessage.innerHTML = '';
            document.getElementById('reservationProductId').value = productId;
            document.getElementById('reservationCsrfToken').value = csrfToken;
            
            // Show modal
            reservationModal.classList.add('active');
        }
        
        function closeReservationModal() {
            reservationModal.classList.remove('active');
            reservationMessage.innerHTML = '';
            reservationForm.reset();
        }
        
        // Close modal when clicking outside
        reservationModal.addEventListener('click', function(event) {
            if (event.target === reservationModal) {
                closeReservationModal();
            }
        });
        
        // Handle form submission
        if (reservationForm) {
            reservationForm.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();
                submitReservation();
            });
            
            // Also add click handler to submit button as fallback
            const submitBtn = document.getElementById('reservationSubmitBtn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    submitReservation();
                });
            }
        }
        
        function submitReservation() {
            reservationMessage.innerHTML = '';
            
            const submitBtn = document.getElementById('reservationSubmitBtn');
            if (!submitBtn) {
                console.error('Submit button not found');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'SUBMITTING...';
            
            // Get form values
            const formData = {
                product_id: document.getElementById('reservationProductId').value,
                quantity: document.getElementById('reservationQuantity').value,
                unit: document.getElementById('reservationUnit').value,
                preferred_pickup_date: document.getElementById('reservationPickupDate').value || '',
                preferred_pickup_time: document.getElementById('reservationPickupTime').value || '',
                notes: document.getElementById('reservationNotes').value || '',
                csrf_token: csrfToken
            };
            
            // Validation
            if (!formData.quantity || parseFloat(formData.quantity) <= 0) {
                reservationMessage.innerHTML = `<div class="price-alert-message error">Please enter a valid quantity.</div>`;
                submitBtn.disabled = false;
                submitBtn.textContent = 'SUBMIT RESERVATION';
                return;
            }
            
            if (!formData.unit) {
                reservationMessage.innerHTML = `<div class="price-alert-message error">Please select a unit.</div>`;
                submitBtn.disabled = false;
                submitBtn.textContent = 'SUBMIT RESERVATION';
                return;
            }
            
            // Send request
            fetch('api/create_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                // Get response text first to see what we're dealing with
                return response.text().then(text => {
                    try {
                        const data = JSON.parse(text);
                        return { ok: response.ok, data: data, status: response.status };
                    } catch (e) {
                        // If JSON parsing fails, return the text as error
                        console.error('JSON parse error:', text);
                        return { ok: false, data: { success: false, message: 'Server returned invalid response: ' + text.substring(0, 200) }, status: response.status };
                    }
                });
            })
            .then(result => {
                if (!result.ok) {
                    throw new Error(result.data.message || `Server error (${result.status})`);
                }
                
                if (result.data.success) {
                    reservationMessage.innerHTML = `<div class="price-alert-message success">${result.data.message}</div>`;
                    setTimeout(() => {
                        closeReservationModal();
                        // Optionally redirect to my-reservations page
                        // window.location.href = 'my-reservations.php';
                    }, 2000);
                } else {
                    reservationMessage.innerHTML = `<div class="price-alert-message error">${result.data.message || 'An error occurred. Please try again.'}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'SUBMIT RESERVATION';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                reservationMessage.innerHTML = `<div class="price-alert-message error">${error.message || 'An error occurred. Please try again.'}</div>`;
                submitBtn.disabled = false;
                submitBtn.textContent = 'SUBMIT RESERVATION';
            });
        }
        
        // Mobile Menu Functions
        function toggleMobileMenu() {
            const hamburger = document.getElementById('hamburgerMenu');
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            
            if (!hamburger || !mobileMenu || !overlay) return;
            
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
            
            // Prevent body scroll when menu is open
            if (mobileMenu.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function closeMobileMenu() {
            const hamburger = document.getElementById('hamburgerMenu');
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');
            
            if (!hamburger || !mobileMenu || !overlay) return;
            
            hamburger.classList.remove('active');
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const hamburger = document.getElementById('hamburgerMenu');
            
            if (mobileMenu && mobileMenu.classList.contains('active')) {
                if (!mobileMenu.contains(event.target) && hamburger && !hamburger.contains(event.target)) {
                    closeMobileMenu();
                }
            }
        });

        // Close mobile menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        // Mobile account dropdown toggle
        function toggleMobileAccountDropdown(event) {
            event.stopPropagation();
            const dropdown = event.currentTarget.closest('.account-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('active');
            }
        }

        // Notifications in shared mobile panel: go to notifications section
        function toggleNotificationDropdown(event) {
            event.preventDefault();
            if (typeof closeMobileMenu === 'function') closeMobileMenu();
            window.location.href = 'user-account.php?section=notifications';
        }
    </script>

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

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
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
                label: 'Price',
                data: prices,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#22c55e',
                pointBorderColor: '#ffffff',
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
                    borderColor: '#000000',
                    borderWidth: 2,
                    padding: 12,
                    titleFont: {
                        family: 'VT323',
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        family: 'VT323',
                        size: 13
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
</script>

<!-- GSAP + Page Transition System (Codrops clip-path style) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script>
(function () {
    var CSS = 'position:fixed;inset:0;background:#0a0a0a;z-index:99997;pointer-events:none;will-change:clip-path;';

    /* ENTER — overlay covers screen, wipes away from bottom-to-top */
    var enterOv = document.createElement('div');
    enterOv.style.cssText = CSS;
    document.body.appendChild(enterOv);

    gsap.from('.header', { y: '4vh', opacity: 0, force3D: true, duration: 0.72, ease: 'power3.inOut', delay: 0.05 });

    gsap.fromTo(enterOv,
        { clipPath: 'inset(0% 0% 0% 0%)' },
        {
            clipPath: 'inset(0% 0% 100% 0%)',
            duration: 0.72, ease: 'power3.inOut', force3D: true, delay: 0.05,
            onComplete: function () { enterOv.parentNode && enterOv.parentNode.removeChild(enterOv); }
        }
    );

    /* EXIT — page slides up, overlay clips in from bottom */
    var transitioning = false;
    function doPageExit(href) {
        if (transitioning) return;
        transitioning = true;
        gsap.to('body > *', { y: '-8vh', opacity: 0, scale: 0.97, force3D: true, duration: 0.68, ease: 'power3.inOut' });
        var exitOv = document.createElement('div');
        exitOv.style.cssText = CSS;
        document.body.appendChild(exitOv);
        gsap.fromTo(exitOv,
            { clipPath: 'inset(100% 0% 0% 0%)' },
            {
                clipPath: 'inset(0% 0% 0% 0%)',
                duration: 0.68, ease: 'power3.inOut', force3D: true,
                onComplete: function () { window.location.href = href; }
            }
        );
    }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('://') !== -1) return;
        if (href === window.location.pathname || href === window.location.href) return;
        if (href.indexOf('logout') !== -1 || href.indexOf('api/') !== -1) return;
        e.preventDefault();
        doPageExit(href);
    });
}());
</script>

</body>
</html>
