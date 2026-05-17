<?php
require_once __DIR__ . '/env.php';

/**
 * Google OAuth Configuration for FarmScout Online
 */

$appUrl = getenv('APP_URL') ? rtrim((string)getenv('APP_URL'), '/') : '';
$defaultRedirect =
    getenv('GOOGLE_OAUTH_REDIRECT_URI')
    ?: ($appUrl ? ($appUrl . '/auth/google_callback.php') : 'http://localhost/farmscout_online/auth/google_callback.php');
$defaultLogo =
    getenv('APP_LOGO_URL')
    ?: ($appUrl ? ($appUrl . '/assets/images/farmscoutlogo.png') : 'http://localhost/farmscout_online/assets/images/farmscoutlogo.png');

return [
    // Google OAuth Settings
    'client_id' => getenv('GOOGLE_OAUTH_CLIENT_ID') ?: '',
    'client_secret' => getenv('GOOGLE_OAUTH_CLIENT_SECRET') ?: '',
    'redirect_uri' => $defaultRedirect,
    
    // OAuth Scopes
    'scopes' => [
        'openid',
        'email',
        'profile'
    ],
    
    // Google OAuth URLs
    'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
    
    // Application Settings
    'app_name' => 'FarmScout Online',
    'app_logo' => $defaultLogo,
    
    // Security Settings
    'state_parameter' => fs_env_bool(getenv('GOOGLE_OAUTH_STATE_PARAM'), true),
    'access_type' => 'offline', // Allow refresh tokens
    'prompt' => 'consent', // Force consent screen
];
?>
