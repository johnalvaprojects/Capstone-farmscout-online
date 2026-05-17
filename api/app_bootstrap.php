<?php
require_once __DIR__ . '/../config/maps.php';

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$payload = [
    'googleMapsApiKey' => defined('GOOGLE_MAPS_API_KEY') ? (string)GOOGLE_MAPS_API_KEY : '',
    'mapDefaults' => [
        'lat' => defined('MAP_DEFAULT_LAT') ? (float)MAP_DEFAULT_LAT : 16.8219,
        'lng' => defined('MAP_DEFAULT_LNG') ? (float)MAP_DEFAULT_LNG : 120.4042,
        'zoom' => defined('MAP_DEFAULT_ZOOM') ? (int)MAP_DEFAULT_ZOOM : 11,
    ],
];
?>
window.FarmScoutAppConfig = <?php echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
