<?php
require_once __DIR__ . '/../includes/enhanced_functions.php';

header('Content-Type: application/json');

// Only allow POST with multipart/form-data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Ensure user is authenticated and has permission (admin, farmer, or vendor)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in']);
    exit;
}

$allowed_roles = ['admin', 'farmer', 'vendor'];
if (!in_array($_SESSION['user_role'] ?? '', $allowed_roles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden - Insufficient permissions']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];

// Basic upload validation
$maxSize = 2 * 1024 * 1024; // 2MB
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Upload error']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File too large. Max 2MB']);
    exit;
}

$allowedExt = ['jpg','jpeg','png','gif','webp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}

// Verify MIME type via finfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMime = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];
if (!in_array($mime, $allowedMime, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid image content']);
    exit;
}

// Prepare destination
$uploadDir = __DIR__ . '/../assets/images/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$basename = bin2hex(random_bytes(8));
$filename = $basename . '.' . $ext;
$destination = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Public URL path (includes subfolder when app is not at web root)
$publicUrl = fs_public_asset_url('/assets/images/uploads/' . $filename);

echo json_encode([
    'success' => true,
    'url' => $publicUrl,
    'name' => $filename,
    'mime' => $mime,
    'size' => $file['size']
]);
exit;
?>


