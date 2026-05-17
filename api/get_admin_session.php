<?php
/**
 * JSON session info for SPA admin dashboard (DTI / Super Admin).
 * Use with credentials: 'include' from same site or allowed dev origins.
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';

$allowed_origins = [
    'http://localhost:3005',
    'http://127.0.0.1:3005',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || !isAdminUser()) {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'role' => null,
        'is_super_admin' => false,
    ]);
    exit;
}

$role = $_SESSION['user_role'] ?? 'admin';

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'role' => $role,
    'username' => $_SESSION['username'] ?? null,
    'is_super_admin' => isSuperAdmin(),
]);
