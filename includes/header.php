<?php
if (!defined('FS_SECURITY_BOOTSTRAPPED')) {
    require_once __DIR__ . '/security.php';
    initSecurity();
    define('FS_SECURITY_BOOTSTRAPPED', true);
}
if (!function_exists('fs_public_asset_url')) {
    require_once __DIR__ . '/enhanced_functions.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($page_title) ? $page_title : 'FarmScout Online - Real-Time Agricultural Price Monitoring'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Get real-time prices from Baloan Public Market. Your trusted digital guide for fresh produce, meat, fish, and processed goods with transparent pricing.'; ?>" />
    <link rel="stylesheet" href="css/main.css?v=20250916C" />
    <link rel="stylesheet" href="css/enhancements.css?v=20250916C" />
    <link rel="stylesheet" href="css/admin-enhancements.css?v=20250916C" />
    <link rel="stylesheet" href="css/hero-slider.css" />
    <?php
    if (!empty($GLOBALS['extra_stylesheets'])) {
        $stylesheets = is_array($GLOBALS['extra_stylesheets']) ? $GLOBALS['extra_stylesheets'] : [$GLOBALS['extra_stylesheets']];
        $stylesheets = array_unique(array_filter(array_map('trim', $stylesheets)));
        foreach ($stylesheets as $stylesheet) {
            $href = htmlspecialchars($stylesheet, ENT_QUOTES, 'UTF-8');
            echo '<link rel="stylesheet" href="' . $href . "\" />\n";
        }
    }
    ?>
    <!-- Updated 2025-09-15 16:50:00 - Added more spacing between hero and products sections -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="public/manifest.json?v=20260405" />
    <meta name="theme-color" content="#2D5016" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="FarmScout" />
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif')); ?>" />
    
    <!-- Favicon - Using FarmScout GIF -->
    <link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif?v=20250116')); ?>" />
    <link rel="shortcut icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif?v=20250116')); ?>" />
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(assetUrl('assets/images/farmscoutlogo.png?v=20250116')); ?>" />
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars(assetUrl('assets/images/farmscoutlogo.png?v=20250116')); ?>" />
    <!-- Fallback to favicon.ico if GIF not supported -->
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(assetUrl('favicon.ico?v=20250116')); ?>" />
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars(assetUrl('favicon.ico?v=20250116')); ?>" />
    
    <!-- PWA Service Worker Registration (path must match subfolder installs, e.g. /farmscout_online/) -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                var swUrl = <?php
                    $sw = function_exists('fs_public_asset_url')
                        ? fs_public_asset_url('public/sw.js')
                        : '/public/sw.js';
                    echo json_encode($sw, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                ?>;
                navigator.serviceWorker.register(swUrl)
                    .then(function() { /* optional: console.debug('SW registered', swUrl); */ })
                    .catch(function() { /* silent: ad blockers / offline also fail here */ });
            });
        }
    </script>
    
    <!-- Logo Animation Styles -->
    <style>
        @font-face {
            font-family: 'Ronzino';
            font-style: normal;
            font-weight: 700;
            src: url('assets/fonts/Ronzino-Bold.woff2') format('woff2'),
                 url('assets/fonts/Ronzino-Bold.otf') format('opentype');
            font-display: swap;
        }

        @font-face {
            font-family: 'Ronzino';
            font-style: normal;
            font-weight: 400;
            src: url('assets/fonts/Ronzino-Regular.woff2') format('woff2'),
                 url('assets/fonts/Ronzino-Regular.otf') format('opentype');
            font-display: swap;
        }

        /* Animated Logo Enhancements */
        .animated-logo {
            border-radius: 8px;
        }
        
        /* Mobile optimization */
        @media (max-width: 640px) {
            .animated-logo {
                height: 2rem; /* h-8 equivalent */
                width: 2rem;
                border-radius: 6px;
            }
            
        }
        
        /* Tablet optimization */
        @media (min-width: 641px) and (max-width: 768px) {
            .animated-logo {
                height: 2.5rem; /* h-10 equivalent */
                width: 2.5rem;
            }
        }
        
        /* Desktop optimization */
        @media (min-width: 769px) {
            .animated-logo {
                height: 2.5rem; /* h-10 equivalent */
                width: 2.5rem;
            }
        }
        
        /* Smooth loading for GIF */
        .animated-logo {
            opacity: 0;
            animation: fadeInLogo 0.5s ease-in forwards;
        }
        
        @keyframes fadeInLogo {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        /* Prefers reduced motion - respect accessibility */
        @media (prefers-reduced-motion: reduce) {
            .animated-logo {
                animation: none;
                opacity: 1;
                transform: none;
            }
            
        }
        
        /* Navigation Underline Reveal Animation */
        .modern-navigation {
            position: relative;
            overflow: hidden;
        }
        
        .modern-navigation::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: #000000; /* Black underline */
            transition: width 0.3s ease;
        }
        
        .modern-navigation:hover::before {
            width: 100%;
        }
        
        /* Keep active page underline always visible */
        .modern-navigation.text-black::before {
            width: 100%;
            background-color: #000000;
        }
        
        /* Ensure hover doesn't interfere with active state */
        .modern-navigation.text-black:hover::before {
            width: 100%;
        }
        
        /* Mobile navigation underline animation */
        @media (max-width: 767px) {
            .mobile-nav-item {
                position: relative;
                overflow: hidden;
            }
            
            .mobile-nav-item::before {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 0;
                height: 2px;
                background-color: #000000;
                transition: width 0.3s ease;
            }
            
            .mobile-nav-item:hover::before {
                width: 100%;
            }
            
            .mobile-nav-item.text-gray-900::before {
                width: 100%;
            }
        }
        
        /* Profile Button Styling - Pure Black Background with White Text */
        .profile-button {
            position: relative;
            overflow: hidden;
            background-color: transparent !important;
            border: 1px solid currentColor;
            transform: scale(1);
            box-shadow: none !important;
            color: inherit;
        }
        
        .profile-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .profile-button:hover::before {
            left: 100%;
        }
        
        .profile-button:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            transform: translateY(-2px) scale(1.02);
            box-shadow: none !important;
        }
        
        .profile-button:active {
            transform: translateY(0) scale(0.98);
            background-color: rgba(255, 255, 255, 0.15) !important;
            box-shadow: none !important;
        }
        
        /* Active state when dropdown is open */
        #profile-dropdown[data-open="true"] .profile-button {
            background-color: rgba(255, 255, 255, 0.1) !important;
            box-shadow: none !important;
        }
        
        /* Chevron rotation when dropdown is open */
        #profile-dropdown-menu:not(.hidden) ~ button #profile-chevron,
        #profile-dropdown[data-open="true"] #profile-chevron {
            transform: rotate(180deg);
        }
        
        
        /* Profile Dropdown Animation - Cool Entrance */
        #profile-dropdown-menu {
            opacity: 0;
            transform: translateY(-20px) scale(0.9) rotateX(-10deg);
            transform-origin: top right;
            transition: opacity 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), 
                        transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
            filter: blur(4px);
        }
        
        #profile-dropdown-menu:not(.hidden) {
            opacity: 1;
            transform: translateY(0) scale(1) rotateX(0deg);
            filter: blur(0);
            pointer-events: auto;
            animation: dropdownBounce 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        @keyframes dropdownBounce {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.9) rotateX(-10deg);
                filter: blur(4px);
            }
            50% {
                transform: translateY(5px) scale(1.02) rotateX(2deg);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotateX(0deg);
                filter: blur(0);
            }
        }
        
        /* Staggered animation for menu items */
        .profile-menu-item {
            opacity: 0;
            transform: translateX(-10px);
            transition: opacity 0.2s ease, transform 0.2s ease, color 0.2s ease, background-color 0.2s ease;
        }
        
        #profile-dropdown-menu:not(.hidden) .profile-menu-item {
            animation: slideInMenuItem 0.3s ease forwards;
        }
        
        #profile-dropdown-menu:not(.hidden) .profile-menu-item:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        #profile-dropdown-menu:not(.hidden) .profile-menu-item:nth-child(2) {
            animation-delay: 0.15s;
        }
        
        #profile-dropdown-menu:not(.hidden) .profile-menu-item:nth-child(3) {
            animation-delay: 0.2s;
        }
        
        #profile-dropdown-menu:not(.hidden) .profile-menu-item:nth-child(4) {
            animation-delay: 0.25s;
        }
        
        @keyframes slideInMenuItem {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Profile Menu Item Hover Animation */
        .profile-menu-item:hover {
            color: #000000 !important;
            background-color: #f3f4f6 !important;
            transform: translateX(4px) !important;
        }
        
        .profile-menu-item:hover svg {
            color: #000000 !important;
            transform: scale(1.1);
        }
        
        /* Logout item special hover */
        .profile-menu-item.logout-item:hover {
            color: #dc2626 !important;
            background-color: #fef2f2 !important;
            transform: translateX(4px) !important;
        }
        
        .profile-menu-item.logout-item:hover svg {
            color: #dc2626 !important;
            transform: scale(1.1);
        }
        
        /* Profile info section animation */
        #profile-dropdown-menu .px-4.py-3 {
            opacity: 0;
            transform: translateY(-5px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        
        #profile-dropdown-menu:not(.hidden) .px-4.py-3 {
            animation: fadeInUp 0.3s ease 0.05s forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Navbar - Always visible, no scroll hide - Fully transparent */
        .modern-header {
            background: transparent !important;
            background-color: transparent !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            border: none !important;
            border-bottom: none !important;
            box-shadow: none !important;
        }
        
        .modern-header nav {
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            border-bottom: none !important;
            box-shadow: none !important;
        }

        /* Add minimal spacing below fixed navbar */
        body {
            padding-top: 70px !important;
        }

        /* For pages with hero sections that start immediately, add minimal spacing */
        main.hero-fashion,
        .hero-container,
        .fashion-hero {
            margin-top: 10px;
        }

        /* Ensure content doesn't overlap with navbar */
        body > *:not(header) {
            position: relative;
            z-index: 1;
        }

        .floating-nav-link {
            font-family: 'Ronzino', 'AileronRegular', sans-serif;
            letter-spacing: 0.15em;
            font-size: 0.7rem;
            color: #0a0a0a;
            text-transform: uppercase;
            position: relative;
            padding: 0.5rem 0.4rem;
            transition: color 0.3s ease;
        }

        /* Light text for dark backgrounds */
        .navbar-dark-mode .floating-nav-link {
            color: #ffffff !important;
        }

        .navbar-dark-mode .floating-nav-link:hover,
        .navbar-dark-mode .floating-nav-link:focus-visible {
            color: #f0f0f0 !important;
        }

        .navbar-dark-mode .modern-logo-text,
        .navbar-dark-mode .modern-tagline {
            color: #ffffff !important;
        }

        /* Light text for light backgrounds (default) */
        .navbar-light-mode .floating-nav-link {
            color: #0a0a0a !important;
        }

        .navbar-light-mode .floating-nav-link:hover,
        .navbar-light-mode .floating-nav-link:focus-visible {
            color: #000000 !important;
        }

        .navbar-light-mode .modern-logo-text,
        .navbar-light-mode .modern-tagline {
            color: #000000 !important;
        }

        .floating-nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background-color: currentColor;
            transition: width 0.25s ease;
        }

        .floating-nav-link:hover,
        .floating-nav-link:focus-visible {
            color: #000000;
        }

        .floating-nav-link:hover::after,
        .floating-nav-link:focus-visible::after,
        .floating-nav-link.is-active::after {
            width: 100%;
        }

        .floating-nav-container {
            position: fixed;
            top: 16px;
            left: 50%;
            transform: translateX(-50%);
            display: none;
            align-items: center;
            gap: 24px;
            pointer-events: auto !important;
            background: transparent !important;
            z-index: 9999 !important;
        }

        .floating-nav-link {
            pointer-events: auto !important;
            cursor: pointer !important;
            z-index: 10000 !important;
            position: relative;
        }

        @media (min-width: 1024px) {
            .floating-nav-container {
                display: flex;
            }
        }
        
        /* Login Button Animation */
        .login-btn {
            background: #fffcd5;
            box-shadow: 4px 4px #ffe00b, 9px 9px #151515;
            color: #151515;
            text-transform: uppercase;
            border: solid 2px #151515;
            text-decoration: none;
            padding: 0.5rem 0.4rem;
            display: inline-flex;
            align-items: center;
            font-size: 0.7rem;
            font-weight: 700;
            position: relative;
            z-index: 1;
            transition: 0.5s cubic-bezier(0.785, 0.135, 0.15, 0.86);
            cursor: pointer;
            overflow: hidden;
            transition-delay: 0s !important;
            letter-spacing: 0.15em;
            font-family: 'Ronzino', 'AileronRegular', sans-serif;
        }
        
        .login-btn::before {
            position: absolute;
            content: "";
            top: 0;
            right: 0;
            width: 0%;
            height: 100%;
            background: #151515;
            z-index: -1;
            transition: 0.5s cubic-bezier(0.785, 0.135, 0.15, 0.86);
        }
        
        .login-btn:hover::before {
            width: 100%;
            left: 0;
            right: unset;
        }
        
        .login-btn:hover {
            box-shadow: 0 0 #ffe00b, 0 0 #151515;
            color: white;
        }
        
        /* Mobile login button - keep same size as nav links */
        @media (max-width: 1023px) {
            .login-btn {
                padding: 0.5rem 0.4rem;
                font-size: 0.7rem;
                letter-spacing: 0.15em;
            }
        }
    </style>
</head>
<?php
$bodyClasses = ['bg-background', 'min-h-screen'];
if (!empty($GLOBALS['market_finder_page'])) {
    $bodyClasses[] = 'market-finder-page';
}
if (!empty($GLOBALS['body_classes'])) {
    $additionalClasses = is_array($GLOBALS['body_classes']) ? $GLOBALS['body_classes'] : [$GLOBALS['body_classes']];
    foreach ($additionalClasses as $className) {
        $trimmed = trim((string)$className);
        if ($trimmed !== '') {
            $bodyClasses[] = $trimmed;
        }
    }
}
$bodyClasses = array_unique($bodyClasses);
$bodyClassAttr = htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8');
?>
<body class="<?php echo $bodyClassAttr; ?>" id="body">
    <!-- Navigation Header -->
    <?php if (!empty($GLOBALS['use_noeinoi_header'])): ?>
        <?php include 'includes/header-noeinoi.php'; ?>
    <?php else: ?>
    <header class="modern-header fixed top-0 left-0 w-full z-50" style="backdrop-filter: none !important; border: none !important; box-shadow: none !important; pointer-events: none;">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" style="background: transparent !important; border: none !important; pointer-events: auto;">
            <div class="flex items-center justify-between h-16 relative">
                <!-- Logo -->
                <div class="flex items-center flex-shrink-0" style="pointer-events: auto; z-index: 10000; position: relative;">
                    <div class="flex-shrink-0">
                        <img src="<?php echo htmlspecialchars(assetUrl('assets/images/gif-wazulafu-no-bg.gif')); ?>" 
                             alt="FarmScout - Tapat na Presyo" 
                             class="h-8 w-8 md:h-10 md:w-10 object-contain logo-img animated-logo"
                             loading="lazy"
                             decoding="async"
                             onerror="this.src='<?php echo assetUrl('assets/images/farmscoutlogo.png'); ?>'; this.onerror=null;" />
                    </div>
                    <div class="ml-3">
                        <h1 class="modern-logo-text" style="font-size: 0.9rem; line-height: 1.2;">FARMSCOUT</h1>
                        <p class="modern-tagline" style="font-size: 0.65rem; line-height: 1;">Tapat na Presyo</p>
                    </div>
                </div>

                <!-- Desktop Navigation -->
                <div class="floating-nav-container">
                    <?php if (isset($_SESSION['user_role'])): ?>
                        <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                            <!-- Farmer Navigation -->
                            <a href="farmer-dashboard.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'farmer-dashboard.php' ? 'is-active' : ''; ?>">
                                FARMER DASHBOARD
                            </a>
                        <?php elseif (isAdminUser()): ?>
                            <!-- Admin Navigation -->
                            <a href="admin-console.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin-console.php' ? 'is-active' : ''; ?>">
                                <?php echo getAdminDashboardLabel(); ?>
                            </a>
                        <?php else: ?>
                            <!-- Consumer Navigation -->
                            <a href="index.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'is-active' : ''; ?>">
                                HOME
                            </a>
                            <a href="market-finder.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'market-finder.php' ? 'is-active' : ''; ?>">
                                MARKET FINDER
                            </a>
                            <a href="price-alerts.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'price-alerts.php' ? 'is-active' : ''; ?>">
                                MANAGE ALERTS
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guest Navigation -->
                        <a href="index.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'is-active' : ''; ?>">
                            HOME
                        </a>
                        <a href="market-finder.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'market-finder.php' ? 'is-active' : ''; ?>">
                            MARKET FINDER
                        </a>
                        <a href="price-alerts.php" class="floating-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'price-alerts.php' ? 'is-active' : ''; ?>">
                            PRICE ALERTS
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Right Section: Action Buttons -->
                <div class="flex items-center flex-shrink-0 space-x-4" style="pointer-events: auto; z-index: 10000; position: relative;">
                    <?php if (isLoggedIn()): ?>
                        <!-- Profile Dropdown -->
                        <div class="relative group" id="profile-dropdown">
                            <button type="button" class="profile-button flex items-center justify-center space-x-2 px-4 py-2 rounded-lg transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2" onclick="toggleProfileDropdown()" style="background: transparent !important; border: 1px solid currentColor; color: inherit;">
                                <span class="text-xs font-semibold hidden md:block whitespace-nowrap uppercase tracking-wide" style="color: inherit;">
                                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                                </span>
                                <svg class="w-5 h-5 hidden md:block transition-transform duration-200 flex-shrink-0" id="profile-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: inherit;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="profile-dropdown-menu" class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-50 hidden">
                                <div class="px-4 py-3 border-b border-gray-200">
                                    <p class="text-sm font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        @<?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1 capitalize">
                                        <?php
                                            $role = $_SESSION['user_role'] ?? 'user';
                                            if ($role === 'super_admin') {
                                                $role_label = 'Super Admin';
                                            } elseif ($role === 'admin') {
                                                $role_label = 'DTI Admin';
                                            } elseif ($role === 'farmer') {
                                                $role_label = 'Market Manager';
                                            } else {
                                                $role_label = $role;
                                            }
                                            echo htmlspecialchars($role_label);
                                        ?>
                                    </p>
                                </div>
                                <div class="py-1">
                                    <?php if (($_SESSION['user_role'] ?? '') === 'farmer'): ?>
                                        <a href="farmer-dashboard.php" class="profile-menu-item block px-4 py-2 text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                                </svg>
                                                Dashboard
                                            </div>
                                        </a>
                                        <a href="manage-products.php" class="profile-menu-item block px-4 py-2 text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                                </svg>
                                                Manage Products
                                            </div>
                                        </a>
                                    <?php elseif (isAdminUser()): ?>
                                        <a href="admin-console.php" class="profile-menu-item block px-4 py-2 text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                                </svg>
                                                <?php echo getAdminDashboardLabel(); ?>
                                            </div>
                                        </a>
                                        <a href="analytics.php" class="profile-menu-item block px-4 py-2 text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                                </svg>
                                                Analytics
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <a href="user-account.php" class="profile-menu-item block px-4 py-2 text-sm text-gray-700">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                                My Account
                                            </div>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="border-t border-gray-200 py-1">
                                    <a href="logout.php" class="profile-menu-item logout-item block px-4 py-2 text-sm text-red-600">
                                        <div class="flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                            Logout
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                    <a href="login.php" class="login-btn">
                        LOGIN
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button type="button" class="text-gray-600 hover:text-gray-900 p-2" onclick="toggleMobileMenu()">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation Menu -->
            <div id="mobile-menu" class="md:hidden hidden border-t border-gray-200 bg-white">
                <div class="px-6 pt-4 pb-6 space-y-4">
                    <?php if (isset($_SESSION['user_role'])): ?>
                        <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                            <!-- Farmer Mobile Navigation -->
                            <a href="farmer-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'farmer-dashboard.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                                FARMER DASHBOARD
                            </a>
                        <?php elseif (isAdminUser()): ?>
                            <!-- Admin Mobile Navigation -->
                            <a href="admin-console.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin-console.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                                <?php echo getAdminDashboardLabel(); ?>
                            </a>
                        <?php else: ?>
                            <!-- Consumer Mobile Navigation -->
                            <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                                HOME
                            </a>
                            <a href="market-finder.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'market-finder.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                                MARKET FINDER
                            </a>
                            <a href="price-alerts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'price-alerts.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                                MANAGE ALERTS
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Guest Mobile Navigation -->
                        <a href="index.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                            HOME
                        </a>
                        <a href="market-finder.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'market-finder.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                            MARKET FINDER
                        </a>
                        <a href="price-alerts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'price-alerts.php' ? 'text-gray-900 font-semibold' : 'text-gray-600 hover:text-gray-900'; ?> block text-sm font-medium transition-colors duration-200 mobile-nav-item">
                            PRICE ALERTS
                        </a>
                    <?php endif; ?>
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <?php if (isLoggedIn()): ?>
                            <?php if (($_SESSION['user_role'] ?? $_SESSION['role'] ?? '') === 'admin'): ?>
                            <a href="analytics.php" class="block text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors duration-200 mb-3">
                                ANALYTICS
                            </a>
                            <?php elseif (($_SESSION['user_role'] ?? $_SESSION['role'] ?? '') === 'farmer'): ?>
                            <a href="manage-products.php" class="block text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors duration-200 mb-3">
                                MANAGE PRODUCTS
                            </a>
                            <?php else: ?>
                            <a href="user-account.php" class="block text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors duration-200 mb-3">
                                ACCOUNT
                            </a>
                            <?php endif; ?>
                            <a href="logout.php" class="bg-lime-400 hover:bg-lime-500 text-gray-900 px-6 py-2 rounded text-sm font-semibold transition-colors duration-200 inline-block">
                                LOGOUT
                            </a>
                        <?php else: ?>
                        <a href="register.php" class="bg-lime-400 hover:bg-lime-500 text-gray-900 px-6 py-2 rounded text-sm font-semibold transition-colors duration-200 inline-block mb-3">
                            SIGN UP
                        </a>
                        <a href="login.php" class="login-btn">
                            LOGIN
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <?php endif; ?>

    <script>
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        }
        
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown-menu');
            const profileDropdown = document.getElementById('profile-dropdown');
            const chevron = document.getElementById('profile-chevron');
            
            dropdown.classList.toggle('hidden');
            
            // Toggle data attribute for styling
            if (dropdown.classList.contains('hidden')) {
                profileDropdown.setAttribute('data-open', 'false');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                profileDropdown.setAttribute('data-open', 'true');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profile-dropdown');
            const dropdownMenu = document.getElementById('profile-dropdown-menu');
            const chevron = document.getElementById('profile-chevron');
            
            if (profileDropdown && dropdownMenu && !profileDropdown.contains(event.target)) {
                dropdownMenu.classList.add('hidden');
                profileDropdown.setAttribute('data-open', 'false');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            }
        });

        // Adaptive navbar text color based on background
        function getBackgroundColor(element) {
            if (!element) return null;
            
            const style = window.getComputedStyle(element);
            let bgColor = style.backgroundColor;
            
            // If background is transparent, check parent
            if (bgColor === 'rgba(0, 0, 0, 0)' || bgColor === 'transparent') {
                const parent = element.parentElement;
                if (parent && parent !== document.body) {
                    return getBackgroundColor(parent);
                }
                // Default to white if we reach body
                return 'rgb(255, 255, 255)';
            }
            
            return bgColor;
        }

        function isDarkColor(color) {
            if (!color) return false;
            
            // Extract RGB values
            const rgbMatch = color.match(/\d+/g);
            if (!rgbMatch || rgbMatch.length < 3) return false;
            
            const r = parseInt(rgbMatch[0]);
            const g = parseInt(rgbMatch[1]);
            const b = parseInt(rgbMatch[2]);
            
            // Calculate luminance (perceived brightness)
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            
            // If luminance is less than 0.5, it's dark
            return luminance < 0.5;
        }

        function updateNavbarColor() {
            const header = document.querySelector('.modern-header');
            if (!header) return;
            
            // Get element directly below navbar (at navbar position)
            const navbarHeight = header.offsetHeight;
            const centerX = window.innerWidth / 2;
            const sampleY = navbarHeight + 10; // Just below navbar
            
            // Get element at that position
            const elementBelow = document.elementFromPoint(centerX, sampleY);
            
            if (!elementBelow) return;
            
            // Get background color
            const bgColor = getBackgroundColor(elementBelow);
            const isDark = isDarkColor(bgColor);
            
            // Apply appropriate class
            header.classList.remove('navbar-dark-mode', 'navbar-light-mode');
            if (isDark) {
                header.classList.add('navbar-dark-mode');
            } else {
                header.classList.add('navbar-light-mode');
            }
        }

        // Update on scroll and resize
        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    updateNavbarColor();
                    ticking = false;
                });
                ticking = true;
            }
        });

        window.addEventListener('resize', updateNavbarColor);
        
        // Initial check
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(updateNavbarColor, 100);
        });
        
        // Also check after page load
        window.addEventListener('load', function() {
            setTimeout(updateNavbarColor, 200);
        });
    </script>
    
    <!-- Enhanced Animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="js/enhanced-animations.js?v=20260405"></script>
    
