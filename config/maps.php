<?php
require_once __DIR__ . '/env.php';

/**
 * Google Maps API Configuration
 */

if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');
}

if (!defined('MAP_DEFAULT_LAT')) {
    define('MAP_DEFAULT_LAT', (float)(getenv('MAP_DEFAULT_LAT') ?: 16.8219));
}

if (!defined('MAP_DEFAULT_LNG')) {
    define('MAP_DEFAULT_LNG', (float)(getenv('MAP_DEFAULT_LNG') ?: 120.4042));
}

if (!defined('MAP_DEFAULT_ZOOM')) {
    define('MAP_DEFAULT_ZOOM', (int)(getenv('MAP_DEFAULT_ZOOM') ?: 11));
}

$maps_config = [
    'api_key' => GOOGLE_MAPS_API_KEY,
    'default_center' => [
        'lat' => MAP_DEFAULT_LAT,
        'lng' => MAP_DEFAULT_LNG
    ],
    'default_zoom' => MAP_DEFAULT_ZOOM,
    'map_style' => 'roadmap', // roadmap, satellite, hybrid, terrain
];

return $maps_config;
