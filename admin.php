<?php
// Redirect admin.php to manage-products.php for backward compatibility
// This file is kept for legacy reasons but redirects to the new name
header('Location: manage-products.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
exit();
