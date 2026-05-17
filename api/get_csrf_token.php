<?php
/**
 * Returns current session CSRF token for SPA (same-origin, cookie session).
 */
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'csrf_token' => '']);
    exit;
}

echo json_encode([
    'success' => true,
    'csrf_token' => getCSRFToken(),
]);
