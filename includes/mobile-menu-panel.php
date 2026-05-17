<?php
// Single mobile menu panel – included on every page with a hamburger.
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
$_mob_home_url = function_exists('fs_spa_home_url') ? htmlspecialchars(fs_spa_home_url(), ENT_QUOTES, 'UTF-8') : '/farmscout_online/app/';
$_mob_market_url = function_exists('fs_spa_home_url')
    ? htmlspecialchars(fs_spa_home_url() . 'nearest', ENT_QUOTES, 'UTF-8')
    : '/farmscout_online/app/nearest';
$_mob_products_url = function_exists('fs_spa_home_url')
    ? htmlspecialchars(fs_spa_home_url() . 'products', ENT_QUOTES, 'UTF-8')
    : '/farmscout_online/app/products';
?>
<!-- Mobile Drawer Panel -->
<div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="Navigation">
    <div class="mobile-menu-header">
        <div class="logo">
            <img src="assets/images/gif-wazulafu-no-bg.gif" alt="FarmScout" class="logo-gif" onerror="this.style.display='none';">
            FARMSCOUT
        </div>
        <button class="mobile-menu-close" onclick="closeMobileMenu()" aria-label="Close menu">&#x2715;</button>
    </div>

    <nav class="mobile-menu-nav" aria-label="Mobile navigation">
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'farmer'): ?>
            <a href="farmer-dashboard.php" onclick="closeMobileMenu()">Dashboard</a>
        <?php elseif (isset($_SESSION['user_role']) && function_exists('isAdminUser') && isAdminUser()): ?>
            <a href="admin-console.php" onclick="closeMobileMenu()"><?php echo function_exists('getAdminDashboardLabel') ? getAdminDashboardLabel() : 'Admin'; ?></a>
            <a href="admin-price-monitoring.php" onclick="closeMobileMenu()">Price Monitoring</a>
            <a href="admin-reservations.php" onclick="closeMobileMenu()">Reservations</a>
            <a href="admin-logs.php" onclick="closeMobileMenu()">Logs</a>
        <?php else: ?>
            <a href="<?php echo $_mob_home_url; ?>" onclick="closeMobileMenu()">Home</a>
            <a href="<?php echo $_mob_products_url; ?>" onclick="closeMobileMenu()">Products</a>
            <a href="<?php echo $_mob_market_url; ?>" onclick="closeMobileMenu()">Markets</a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="notification-bell" onclick="event.preventDefault(); closeMobileMenu(); toggleNotificationDropdown(event);" aria-label="Notifications">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-2 2v1h18v-1l-2-2Z"/></svg>
                Notifications
                <span class="notification-badge" id="mobileNotificationBadge" style="display:none;">0</span>
            </button>

            <div class="account-dropdown">
                <a href="user-account.php" class="account-dropdown-toggle" onclick="event.preventDefault(); toggleMobileAccountDropdown(event);">Account</a>
                <div class="account-dropdown-menu">
                    <a href="user-account.php" onclick="closeMobileMenu()">My Account</a>
                    <a href="logout.php" onclick="closeMobileMenu()">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="login-btn" onclick="closeMobileMenu()">Login</a>
        <?php endif; ?>
    </nav>

    <footer class="mobile-menu-footer">
        <div class="mobile-menu-footer-brand">FarmScout</div>
        <div class="mobile-menu-footer-meta">
            <span>La Union</span>
            <span>&copy;<?php echo date('Y'); ?> FarmScout</span>
        </div>
    </footer>
</div>
