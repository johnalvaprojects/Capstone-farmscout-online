<?php
// Market Finder Style Navigation Component
// EXACT COPY from market-finder.php

// Get current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Vite SPA Market Finder (legacy hub remains at market-finder.php for bookmarks)
$fs_mf_nav_href = function_exists('fs_spa_home_url')
    ? htmlspecialchars(fs_spa_home_url() . 'markets', ENT_QUOTES, 'UTF-8')
    : 'market-finder.php';

// Get CSRF token for JavaScript
if (!function_exists('getCSRFToken')) {
    require_once __DIR__ . '/security.php';
}
$csrf_token_js = isLoggedIn() ? getCSRFToken() : '';
?>
<style>
    @font-face {
        font-family: 'InterDisplay';
        src: url('assets/fonts/Inter-4.1/extras/otf/InterDisplay-Bold.otf') format('opentype');
        font-weight: 700;
        font-style: normal;
        font-display: swap;
    }

    /* Header - EXACT COPY from market-finder.php */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 3rem;
        font-size: 1.25rem;
        letter-spacing: 0.05em;
    }

    .header.admin-fixed-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
        background: transparent;
        padding: 1rem;
        margin-bottom: 0;
    }

    .admin-header-spacer {
        height: 4.5rem;
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
        color: var(--text-color, #000000);
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1.1rem;
        display: inline-block;
    }
    
    .nav a:not(.login-btn):not(.account-dropdown-toggle):hover {
        transform: translateY(-3px);
    }
    
    .nav a.account-dropdown-toggle:hover {
        transform: none;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .nav-icons {
        display: flex;
        gap: 0.5rem;
        margin-left: 1rem;
    }
    
    .icon-box {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border-color, #000000);
        display: inline-block;
    }
    
    .login-btn {
        padding: 0.2rem 0.6rem;
        border: 2px solid var(--border-color, #000000);
        background-color: var(--bg-color, #ffffff);
        color: var(--text-color, #000000);
        text-decoration: none;
        font-size: 0.9rem;
        transition: all 0.3s ease 0.1s;
    }
    
    .login-btn:hover {
        background-color: var(--text-color, #000000);
        color: var(--bg-color, #ffffff);
        transition: all 0.3s ease 0s;
    }
    
    /* Account Dropdown */
    .account-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .account-dropdown-toggle {
        cursor: pointer;
        position: relative;
        color: var(--text-color, #000000) !important;
        text-decoration: none !important;
        transition: color 0.3s ease;
        font-size: 1.1rem;
        background-color: transparent !important;
        border: none !important;
        padding: 0 !important;
        display: inline-block;
    }
    
    .account-dropdown-toggle:hover,
    .account-dropdown-toggle:focus,
    .account-dropdown-toggle:active,
    .account-dropdown-toggle:hover *,
    .account-dropdown-toggle:focus *,
    .account-dropdown-toggle:active * {
        color: var(--text-color, #000000) !important;
        background-color: transparent !important;
        border: none !important;
        outline: none !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .account-dropdown:hover .account-dropdown-toggle {
        color: var(--text-color, #000000) !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .account-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 0.5rem;
        background-color: #ffffff !important;
        border: 2px solid #000000;
        min-width: 150px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.3s ease;
        z-index: 10000;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: block;
    }
    
    .account-dropdown.active .account-dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .account-dropdown-menu a {
        display: block !important;
        padding: 0.75rem 1rem;
        color: #000000 !important;
        text-decoration: none !important;
        font-family: 'VT323', monospace !important;
        font-size: 1rem !important;
        transition: background-color 0.3s ease, color 0.3s ease !important;
        border-bottom: 1px solid #000000;
        opacity: 1 !important;
        visibility: visible !important;
        position: relative;
        z-index: 10001;
        background-color: transparent !important;
    }
    
    .account-dropdown-menu a:last-child {
        border-bottom: none;
    }
    
    .account-dropdown-menu a:hover {
        background-color: #000000 !important;
        color: #ffffff !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .account-dropdown-menu a:hover,
    .account-dropdown-menu a:hover *,
    .account-dropdown-menu a:focus,
    .account-dropdown-menu a:focus * {
        color: #ffffff !important;
        opacity: 1 !important;
        visibility: visible !important;
        text-shadow: none !important;
    }
    
    .account-dropdown-menu a:not(:hover) {
        color: #000000 !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Notification Bell Icon */
    .notification-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .notification-bell {
        position: relative;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0.5rem;
        color: var(--text-color, #000000);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-bell:hover {
        transform: scale(1.1);
        opacity: 0.8;
    }
    
    .notification-bell svg {
        width: 24px;
        height: 24px;
    }
    
    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background-color: #dc3545;
        color: #ffffff;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 10px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-color, #ffffff);
        font-family: 'VT323', monospace;
    }
    
    .notification-badge.pulse {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    /* Notification Panel */
    .notification-panel {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 0.5rem;
        background-color: var(--bg-color, #ffffff);
        border: 3px solid var(--border-color, #000000);
        width: 350px;
        max-height: 400px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1001;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
    }
    
    .notification-dropdown.active .notification-panel {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .notification-header {
        padding: 1rem;
        border-bottom: 2px solid var(--border-color, #000000);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-header h3 {
        font-family: 'VT323', monospace;
        font-size: 1.2rem;
        font-weight: bold;
        text-transform: uppercase;
        margin: 0;
    }
    
    .mark-all-read-btn {
        background: none;
        border: 1px solid var(--border-color, #000000);
        padding: 0.25rem 0.5rem;
        font-family: 'VT323', monospace;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .mark-all-read-btn:hover {
        background-color: var(--text-color, #000000);
        color: var(--bg-color, #ffffff);
    }
    
    .notification-list {
        overflow-y: auto;
        max-height: 300px;
        flex: 1;
    }
    
    .notification-loading,
    .notification-empty {
        padding: 2rem;
        text-align: center;
        font-family: 'VT323', monospace;
        color: #666;
    }
    
    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #000000);
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'VT323', monospace;
    }
    
    .notification-item:hover {
        background-color: #f5f5f5;
    }
    
    .notification-item.unread {
        background-color: #fff3cd;
        border-left: 4px solid #ffc107;
    }
    
    .notification-item-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: bold;
        font-size: 1rem;
        margin-bottom: 0.25rem;
        text-transform: uppercase;
    }
    
    .notification-item-icon {
        width: 24px;
        height: 24px;
        object-fit: contain;
        flex-shrink: 0;
    }
    
    .notification-item-message {
        font-size: 0.9rem;
        color: #333;
        margin-bottom: 0.25rem;
    }
    
    .notification-item-time {
        font-size: 0.8rem;
        color: #666;
    }
    
    @media (max-width: 768px) {
        .notification-panel {
            width: 90vw;
            right: 0;
            left: auto;
        }
    }
    
    /* Hamburger Menu Styles */
    .hamburger-menu {
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
        inset: 0;
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px);
        z-index: 9998;
        opacity: 0;
        transition: opacity 0.32s ease;
    }
    .mobile-menu-overlay.active {
        display: block;
        opacity: 1;
    }
    
    .mobile-menu {
        position: fixed;
        top: 0;
        right: -100%;
        width: 320px;
        max-width: 88vw;
        height: 100%;
        background-color: #fff;
        border-left: 1px solid rgba(0,0,0,.12);
        box-shadow: -18px 0 60px rgba(0,0,0,.12);
        z-index: 9999;
        transition: right 0.32s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        padding: 0;
        font-family: "Poppins", Inter, system-ui, sans-serif;
        display: flex;
        flex-direction: column;
    }
    
    .mobile-menu.active {
        right: 0;
    }
    
    .mobile-menu-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(0,0,0,.10);
        flex-shrink: 0;
    }
    
    .mobile-menu-header .logo {
        font-family: "Poppins", Inter, system-ui, sans-serif;
        font-size: 1rem;
        font-weight: 900;
        letter-spacing: .02em;
        text-transform: uppercase;
    }
    
    .mobile-menu-close {
        background: none;
        border: 1px solid rgba(0,0,0,.18);
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1.2rem;
        color: #111;
        transition: background .15s, color .15s;
    }
    
    .mobile-menu-close:hover {
        background: #111;
        color: #fff;
        border-color: #111;
    }
    
    .mobile-menu-nav {
        display: flex;
        flex-direction: column;
        padding: 1.25rem 1.5rem;
        gap: 0;
        flex: 1;
    }
    
    .mobile-menu-nav a,
    .mobile-menu-nav .account-dropdown-toggle {
        display: flex;
        align-items: center;
        padding: 0.85rem 0;
        border: none;
        border-bottom: 1px solid rgba(0,0,0,.07);
        color: #111;
        text-decoration: none;
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        transition: color .12s;
        background: none;
        cursor: pointer;
        width: 100%;
        text-align: left;
    }
    
    .mobile-menu-nav a:last-child { border-bottom: none; }
    
    .mobile-menu-nav a:hover,
    .mobile-menu-nav a.active,
    .mobile-menu-nav .account-dropdown-toggle:hover {
        color: #4A7C59;
        background: none;
    }
    
    .mobile-menu-nav .login-btn {
        margin-top: 0.5rem;
        border: 1px solid #111 !important;
        border-radius: 8px;
        padding: 0.7rem 1rem !important;
        justify-content: center;
        background: #111 !important;
        color: #fff !important;
    }
    .mobile-menu-nav .login-btn:hover {
        background: #4A7C59 !important;
        border-color: #4A7C59 !important;
        color: #fff !important;
    }
    
    .mobile-menu-nav .account-dropdown {
        width: 100%;
        position: relative;
    }
    
    .mobile-menu-nav .account-dropdown-menu {
        position: static;
        opacity: 1;
        visibility: visible;
        transform: none;
        box-shadow: none;
        border: none;
        background: rgba(0,0,0,.03);
        border-radius: 8px;
        margin: 0.25rem 0 0.5rem;
        padding: 0.25rem 0;
        display: none;
    }
    .mobile-menu-nav .account-dropdown.active .account-dropdown-menu {
        display: block;
    }
    .mobile-menu-nav .account-dropdown-menu a {
        padding: 0.6rem 1rem;
        font-size: .875rem;
        border-bottom: none;
    }
    
    .mobile-menu-nav .notification-bell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 0;
        border: none;
        border-bottom: 1px solid rgba(0,0,0,.07);
        background: transparent;
        color: #111;
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        cursor: pointer;
        width: 100%;
        text-align: left;
    }
    .mobile-menu-nav .notification-bell svg { width: 18px; height: 18px; }
    
    .mobile-menu-footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid rgba(0,0,0,.08);
        flex-shrink: 0;
    }
    
    /* Mobile overlay always active */
    @media (max-width: 768px) {
        .header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            gap: 1rem;
        }
        .logo { font-size: 1rem; }
        .logo img { height: 1.5rem; }
        .nav { display: none !important; }
        .hamburger-menu { display: flex !important; }
        .mobile-menu-footer { display: block; }
        .mobile-menu-footer-brand {
            font-size: .95rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 0.5rem;
        }
        .mobile-menu-footer-meta {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: rgba(0,0,0,.5);
        }
        .mobile-menu-footer-meta a {
            text-decoration: none;
            color: inherit;
        }
    }

    @media (max-width: 480px) {
        .header { padding: 0.75rem; }
        .nav { display: none !important; }
        .hamburger-menu { display: flex !important; }
    }
</style>

<!-- Header - EXACT COPY from market-finder.php -->
<?php
$header_classes = 'header';
if (isAdminUser()) {
    $header_classes .= ' admin-fixed-header';
}
?>
<header class="<?php echo $header_classes; ?>">
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
                <a href="admin-price-monitoring.php" class="<?php echo ($current_page == 'admin-price-monitoring.php') ? 'active' : ''; ?>">PRICE MONITORING</a>
                <a href="admin-reservations.php" class="<?php echo ($current_page == 'admin-reservations.php') ? 'active' : ''; ?>">RESERVATIONS</a>
                <a href="admin-logs.php" class="<?php echo ($current_page == 'admin-logs.php') ? 'active' : ''; ?>">LOGS</a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars(fs_spa_home_url()); ?>" class="">HOME</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars(fs_spa_home_url()); ?>" class="">HOME</a>
        <?php endif; ?>
        
        <?php /* MANAGE PRODUCTS link removed — now inside farmer dashboard */ ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Notification Bell Icon -->
            <div class="notification-dropdown">
                <button class="notification-bell" onclick="event.preventDefault(); toggleNotificationDropdown(event);" aria-label="Notifications">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-panel" id="notificationPanel">
                    <div class="notification-header">
                        <h3>NOTIFICATIONS</h3>
                        <button class="mark-all-read-btn" id="markAllReadBtn" style="display: none;">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">Loading notifications...</div>
                    </div>
                    <div class="notification-empty" id="notificationEmpty" style="display: none;">
                        <p>No notifications</p>
                        <p style="font-size: 0.8rem; color: #666; margin-top: 0.5rem;">You're all caught up!</p>
                    </div>
                </div>
            </div>
            
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

<?php if (isAdminUser()): ?>
<div class="admin-header-spacer"></div>
<?php endif; ?>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

<?php include __DIR__ . '/mobile-menu-panel.php'; ?>

<script>
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
    if (!event.target.closest('.notification-dropdown')) {
        document.querySelectorAll('.notification-dropdown').forEach(d => d.classList.remove('active'));
    }
});

// Notification System
function toggleNotificationDropdown(event) {
    event.stopPropagation();
    const dropdown = event.currentTarget.closest('.notification-dropdown');
    const isActive = dropdown.classList.contains('active');
    
    // Close all dropdowns
    document.querySelectorAll('.notification-dropdown, .account-dropdown').forEach(d => d.classList.remove('active'));
    
    // Toggle this dropdown
    if (!isActive) {
        dropdown.classList.add('active');
        loadNotifications();
    }
}

// CSRF token for JavaScript
const CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token_js, ENT_QUOTES, 'UTF-8'); ?>';

// Helper function to get CSRF token
function getCSRFToken() {
    return CSRF_TOKEN;
}

let notificationPollInterval = null;

function loadNotifications() {
    const list = document.getElementById('notificationList');
    const empty = document.getElementById('notificationEmpty');
    
    // Show loading state
    if (list) {
        list.style.display = 'block';
        list.innerHTML = '<div class="notification-loading">Loading notifications...</div>';
    }
    if (empty) empty.style.display = 'none';
    
    // Use relative path like other API calls
    fetch('api/get_notifications.php')
        .then(response => {
            console.log('Notifications API response status:', response.status); // Debug
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text().then(text => {
                console.log('Notifications API raw response:', text); // Debug
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e, 'Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Notifications API parsed data:', data); // Debug log
            if (data && data.success !== false) {
                updateNotificationBadge(data.unread_count || 0);
                displayNotifications(data.notifications || []);
            } else {
                // Show error message
                if (list) {
                    list.innerHTML = '<div class="notification-loading" style="color: #dc3545;">' + (data?.message || 'Failed to load notifications') + '</div>';
                }
                updateNotificationBadge(0);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            // Silently fail - don't show error message to user
            // Notifications are working fine in the account page
            if (list) {
                list.innerHTML = '';
            }
            if (empty) empty.style.display = 'block';
            updateNotificationBadge(0);
        });
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    const markAllBtn = document.getElementById('markAllReadBtn');
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
        badge.classList.add('pulse');
        if (markAllBtn) markAllBtn.style.display = 'block';
    } else {
        badge.style.display = 'none';
        badge.classList.remove('pulse');
        if (markAllBtn) markAllBtn.style.display = 'none';
    }
}

function displayNotifications(notifications) {
    const list = document.getElementById('notificationList');
    const empty = document.getElementById('notificationEmpty');
    
    if (!list || !empty) {
        console.error('Notification list elements not found');
        return;
    }
    
    if (!notifications || notifications.length === 0) {
        list.style.display = 'none';
        empty.style.display = 'block';
        return;
    }
    
    list.style.display = 'block';
    empty.style.display = 'none';
    list.innerHTML = '';
    
    notifications.forEach(notif => {
        const item = document.createElement('div');
        item.className = 'notification-item' + (notif.is_read ? '' : ' unread');
        item.onclick = () => markNotificationRead(notif.id, notif.link);
        
        const icon = getNotificationIcon(notif.type);
        
        item.innerHTML = `
            <div class="notification-item-title">
                <img src="assets/images/box.png" alt="Notification" class="notification-item-icon">
                ${escapeHtml(notif.title)}
            </div>
            <div class="notification-item-message">${escapeHtml(notif.message)}</div>
            <div class="notification-item-time">${escapeHtml(notif.time_ago)}</div>
        `;
        
        list.appendChild(item);
    });
}

function getNotificationIcon(type) {
    const icons = {
        'new_reservation': '📦',
        'reservation_accepted': '✅',
        'reservation_declined': '❌',
        'new_message': '💬'
    };
    return icons[type] || '🔔';
}

function markNotificationRead(notificationId, link) {
    const formData = new FormData();
    formData.append('notification_id', notificationId);
    formData.append('csrf_token', getCSRFToken());
    
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            if (link) {
                // Safety check: if link points to farmer-dashboard but user is not a farmer, redirect to user-account instead
                // This fixes old notifications that were created with wrong links
                if (link.includes('farmer-dashboard.php') && !document.body.classList.contains('farmer-user')) {
                    // Check if user is actually a farmer by looking for farmer-specific elements or use a safer approach
                    // For now, if link is farmer-dashboard and we're on a non-farmer page, redirect to user-account
                    const currentPath = window.location.pathname;
                    if (!currentPath.includes('farmer-dashboard.php')) {
                        // User is not on farmer dashboard, so they're likely a regular user
                        // Replace farmer-dashboard with user-account for reservations section
                        link = link.replace('farmer-dashboard.php?section=reservations', 'user-account.php?section=reservations');
                    }
                }
                window.location.href = link;
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function markAllNotificationsRead() {
    const formData = new FormData();
    formData.append('mark_all', 'true');
    formData.append('csrf_token', getCSRFToken());
    
    fetch('api/mark_notification_read.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize notification system when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Cleanup old notifications (runs once per page load, not every time)
    // This is a lightweight check that happens in the background
    fetch('api/cleanup_old_notifications.php')
        .catch(error => {
            // Silently fail - cleanup is not critical
            console.log('Notification cleanup skipped');
        });
    
    // Load notifications on page load
    loadNotifications();
    
    // Set up polling for new notifications (every 30 seconds)
    notificationPollInterval = setInterval(loadNotifications, 30000);
    
    // Set up mark all as read button
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllNotificationsRead();
        });
    }
});

// Clean up interval when page unloads
window.addEventListener('beforeunload', function() {
    if (notificationPollInterval) {
        clearInterval(notificationPollInterval);
    }
});
</script>

<!-- GSAP + Page Transition System (Codrops clip-path style) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script>
(function () {
    // Allow pages to disable these transitions (e.g., user-account.php)
    if (document.documentElement && document.documentElement.classList.contains('fs-no-page-transitions')) {
        return;
    }

    var CSS = 'position:fixed;inset:0;background:#0a0a0a;z-index:99997;pointer-events:none;will-change:clip-path;';

    /* ── ENTER ───────────────────────────────────────────────────
       Overlay covers the whole screen on load (clip-path full).
       Wipes AWAY upward (clip-path shrinks from the bottom up),
       revealing the page while the header slides up from below.
    ──────────────────────────────────────────────────────────── */
    var enterOv = document.createElement('div');
    enterOv.style.cssText = CSS;
    document.body.appendChild(enterOv);

    /* page content starts slightly below — slides up as overlay leaves */
    gsap.from('.header', {
        y: '4vh', opacity: 0, force3D: true,
        duration: 0.72, ease: 'power3.inOut', delay: 0.05
    });

    /* overlay wipes away from bottom-to-top (inset(0 0 0 0) → inset(0 0 100% 0)) */
    gsap.fromTo(enterOv,
        { clipPath: 'inset(0% 0% 0% 0%)' },
        {
            clipPath: 'inset(0% 0% 100% 0%)',
            duration: 0.72, ease: 'power3.inOut', force3D: true, delay: 0.05,
            onComplete: function () { enterOv.parentNode && enterOv.parentNode.removeChild(enterOv); }
        }
    );

    /* ── EXIT ────────────────────────────────────────────────────
       When any internal link is clicked:
       • Header + page content slides up + fades (3D exit feel)
       • clip-path overlay enters from the bottom simultaneously
       • Navigate when overlay fully covers the screen
    ──────────────────────────────────────────────────────────── */
    var transitioning = false;

    function doPageExit(href) {
        if (transitioning) return;
        transitioning = true;

        /* current page slides up + fades */
        gsap.to('body > *', {
            y: '-8vh', opacity: 0, scale: 0.97,
            force3D: true, duration: 0.68, ease: 'power3.inOut'
        });

        /* overlay clips in from the bottom */
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

