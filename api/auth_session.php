<?php
/**
 * Current PHP session user for SPA / real-time auth checks (same-origin, cookie session).
 *
 * GET returns JSON: logged_in, user_role (farmer, consumer, admin, super_admin, …), username, etc.
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode([
        'logged_in' => false,
        'user' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$role = $_SESSION['user_role'] ?? '';

if ($role === 'super_admin' || $role === 'admin') {
    $defaultDashboard = 'admin-console.php';
} elseif ($role === 'farmer') {
    $defaultDashboard = 'farmer-dashboard.php';
} else {
    $defaultDashboard = 'user-account.php';
}

echo json_encode([
    'logged_in' => true,
    'user' => [
        'id' => (int) ($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'user_role' => $role,
    ],
    // Hints for client routing (mirror login.php defaults)
    'default_dashboard' => $defaultDashboard,
], JSON_UNESCAPED_UNICODE);
