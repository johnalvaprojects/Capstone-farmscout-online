<?php
// Noeinoi Style Header Component
// Simple, minimalist navigation matching noeinoi.com design

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$fs_mf_nav_href = function_exists('fs_spa_home_url')
    ? htmlspecialchars(fs_spa_home_url() . 'markets', ENT_QUOTES, 'UTF-8')
    : 'market-finder.php';
?>
<style>
    /* Hamburger Menu Styles for Noeinoi Header */
    .noeinoi-hamburger-menu {
        display: none;
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
    
    .noeinoi-hamburger-menu:hover {
        background-color: var(--text-color, #000000);
    }
    
    .noeinoi-hamburger-menu:hover .noeinoi-hamburger-line {
        background-color: var(--bg-color, #ffffff);
    }
    
    .noeinoi-hamburger-line {
        width: 24px;
        height: 3px;
        background-color: var(--text-color, #000000);
        transition: all 0.3s ease;
    }
    
    .noeinoi-hamburger-menu.active .noeinoi-hamburger-line:nth-child(1) {
        transform: rotate(45deg) translate(7px, 7px);
    }
    
    .noeinoi-hamburger-menu.active .noeinoi-hamburger-line:nth-child(2) {
        opacity: 0;
    }
    
    .noeinoi-hamburger-menu.active .noeinoi-hamburger-line:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -7px);
    }
    
    /* Mobile Menu Overlay */
    .noeinoi-mobile-menu-overlay {
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
    
    .noeinoi-mobile-menu-overlay.active {
        display: block;
        opacity: 1;
    }
    
    .noeinoi-mobile-menu {
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
    
    .noeinoi-mobile-menu.active {
        right: 0;
    }
    
    .noeinoi-mobile-menu-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-color, #000000);
    }
    
    .noeinoi-mobile-menu-header .noeinoi-logo {
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .noeinoi-mobile-menu-close {
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
    
    .noeinoi-mobile-menu-close:hover {
        background-color: var(--text-color, #000000);
        color: var(--bg-color, #ffffff);
    }
    
    .noeinoi-mobile-menu-nav {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .noeinoi-mobile-menu-nav a {
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
    
    .noeinoi-mobile-menu-nav a:hover,
    .noeinoi-mobile-menu-nav a.active {
        background-color: var(--text-color, #000000);
        color: var(--bg-color, #ffffff);
    }
    
    @media (max-width: 768px) {
        .noeinoi-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            gap: 1rem;
        }
        
        .noeinoi-nav {
            display: none;
        }
        
        .noeinoi-hamburger-menu {
            display: flex;
        }
    }
</style>

<header class="noeinoi-header">
    <div class="noeinoi-logo">FARMSCOUT</div>
    
    <!-- Hamburger Menu Button (Mobile Only) -->
    <button class="noeinoi-hamburger-menu" id="noeinoiHamburgerMenu" onclick="toggleNoeinoiMobileMenu()" aria-label="Toggle menu">
        <span class="noeinoi-hamburger-line"></span>
        <span class="noeinoi-hamburger-line"></span>
        <span class="noeinoi-hamburger-line"></span>
    </button>
    
    <nav class="noeinoi-nav">
        <?php if (isset($_SESSION['user_role'])): ?>
            <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                <!-- Farmer Navigation -->
                <a href="farmer-dashboard.php" class="noeinoi-nav-link <?php echo ($current_page == 'farmer-dashboard.php') ? 'active' : ''; ?>">FARMER DASHBOARD</a>
            <?php elseif (isAdminUser()): ?>
                <!-- Admin Navigation -->
                <a href="admin-console.php" class="noeinoi-nav-link <?php echo ($current_page == 'admin-console.php') ? 'active' : ''; ?>"><?php echo getAdminDashboardLabel(); ?></a>
            <?php else: ?>
                <!-- Consumer Navigation -->
                <a href="index.php" class="noeinoi-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">HOME</a>
                <a href="<?php echo $fs_mf_nav_href; ?>" class="noeinoi-nav-link <?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>">MARKET FINDER</a>
                <a href="price-alerts.php" class="noeinoi-nav-link <?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>">PRICE ALERTS</a>
            <?php endif; ?>
        <?php else: ?>
            <!-- Guest Navigation -->
            <a href="index.php" class="noeinoi-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">HOME</a>
            <a href="<?php echo $fs_mf_nav_href; ?>" class="noeinoi-nav-link <?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>">MARKET FINDER</a>
            <a href="price-alerts.php" class="noeinoi-nav-link <?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>">PRICE ALERTS</a>
        <?php endif; ?>
        
        <div class="noeinoi-nav-icons">
            <div class="noeinoi-icon-box"></div>
            <div class="noeinoi-icon-box"></div>
            <div class="noeinoi-icon-box"></div>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- User is logged in - show profile link or user menu -->
            <a href="user-account.php" class="noeinoi-nav-link">ACCOUNT</a>
        <?php else: ?>
            <!-- User is not logged in - show login button -->
            <a href="login.php" class="noeinoi-login-btn">LOGIN</a>
        <?php endif; ?>
    </nav>
</header>

<!-- Mobile Menu Overlay -->
<div class="noeinoi-mobile-menu-overlay" id="noeinoiMobileMenuOverlay" onclick="closeNoeinoiMobileMenu()"></div>

<!-- Mobile Menu -->
<div class="noeinoi-mobile-menu" id="noeinoiMobileMenu">
    <div class="noeinoi-mobile-menu-header">
        <div class="noeinoi-logo">FARMSCOUT</div>
        <button class="noeinoi-mobile-menu-close" onclick="closeNoeinoiMobileMenu()" aria-label="Close menu">×</button>
    </div>
    
    <nav class="noeinoi-mobile-menu-nav">
        <?php if (isset($_SESSION['user_role'])): ?>
            <?php if ($_SESSION['user_role'] === 'farmer'): ?>
                <a href="farmer-dashboard.php" class="<?php echo ($current_page == 'farmer-dashboard.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">FARMER DASHBOARD</a>
            <?php elseif (isAdminUser()): ?>
                <a href="admin-console.php" class="<?php echo ($current_page == 'admin-console.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()"><?php echo getAdminDashboardLabel(); ?></a>
            <?php else: ?>
                <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">HOME</a>
                <a href="<?php echo $fs_mf_nav_href; ?>" class="<?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">MARKET FINDER</a>
                <a href="price-alerts.php" class="<?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">PRICE ALERTS</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">HOME</a>
            <a href="<?php echo $fs_mf_nav_href; ?>" class="<?php echo ($current_page == 'market-finder.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">MARKET FINDER</a>
            <a href="price-alerts.php" class="<?php echo ($current_page == 'price-alerts.php') ? 'active' : ''; ?>" onclick="closeNoeinoiMobileMenu()">PRICE ALERTS</a>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="user-account.php" onclick="closeNoeinoiMobileMenu()">ACCOUNT</a>
        <?php else: ?>
            <a href="login.php" class="noeinoi-login-btn" onclick="closeNoeinoiMobileMenu()" style="margin-top: 1rem;">LOGIN</a>
        <?php endif; ?>
    </nav>
</div>

<script>
    // Noeinoi Mobile Menu Functions
    function toggleNoeinoiMobileMenu() {
        const hamburger = document.getElementById('noeinoiHamburgerMenu');
        const mobileMenu = document.getElementById('noeinoiMobileMenu');
        const overlay = document.getElementById('noeinoiMobileMenuOverlay');
        
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

    function closeNoeinoiMobileMenu() {
        const hamburger = document.getElementById('noeinoiHamburgerMenu');
        const mobileMenu = document.getElementById('noeinoiMobileMenu');
        const overlay = document.getElementById('noeinoiMobileMenuOverlay');
        
        if (!hamburger || !mobileMenu || !overlay) return;
        
        hamburger.classList.remove('active');
        mobileMenu.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const mobileMenu = document.getElementById('noeinoiMobileMenu');
        const hamburger = document.getElementById('noeinoiHamburgerMenu');
        
        if (mobileMenu && mobileMenu.classList.contains('active')) {
            if (!mobileMenu.contains(event.target) && hamburger && !hamburger.contains(event.target)) {
                closeNoeinoiMobileMenu();
            }
        }
    });

    // Close mobile menu on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeNoeinoiMobileMenu();
        }
    });
</script>