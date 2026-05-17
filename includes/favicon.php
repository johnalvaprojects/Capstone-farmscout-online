<?php
// Favicon include file - Add this to any page's <head> section
if (!function_exists('assetUrl')) {
    require_once __DIR__ . '/enhanced_functions.php';
}
?>
<!-- Favicon - Doggo GIF -->
<link rel="icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif?v=20250116')); ?>" />
<link rel="shortcut icon" type="image/gif" href="<?php echo htmlspecialchars(assetUrl('assets/images/demi doggu.gif?v=20250116')); ?>" />
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars(assetUrl('assets/images/farmscoutlogo.png?v=20250116')); ?>" />
<link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars(assetUrl('assets/images/farmscoutlogo.png?v=20250116')); ?>" />
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars(assetUrl('assets/images/farmscoutlogo.png?v=20250116')); ?>" />
