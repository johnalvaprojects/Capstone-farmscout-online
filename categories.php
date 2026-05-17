<?php
// Redirect categories.php to market-finder.php
// Both pages do the same thing, so we consolidated them

$market = isset($_GET['market']) ? '?market=' . intval($_GET['market']) : '';
$category = isset($_GET['category']) ? ($market ? '&' : '?') . 'category=' . intval($_GET['category']) : '';

header('Location: market-finder.php' . $market . $category, true, 301);
exit;
?>
