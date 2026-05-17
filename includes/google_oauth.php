<?php
/**
 * Google OAuth Authentication Handler for FarmScout Online
 */

require_once __DIR__ . '/../config/google_oauth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/enhanced_functions.php';

class GoogleOAuth {
    private $config;
    private $conn;
    
    public function __construct() {
        $this->config = include __DIR__ . '/../config/google_oauth.php';
        $this->conn = getDB();
        if (!($this->conn instanceof PDO)) {
            throw new Exception('Database connection unavailable for Google OAuth');
        }
    }
    
    /**
     * Check if Google OAuth is properly configured
     */
    public function isConfigured() {
        return !empty($this->config['client_id']) && 
               !empty($this->config['client_secret']) &&
               $this->config['client_id'] !== 'your-google-client-id.apps.googleusercontent.com';
    }
    
    /**
     * Generate Google OAuth login URL
     *
     * @param string $postLoginRedirect Optional path (e.g. /farmscout_online/app/account) to use after OAuth.
     */
    public function getAuthUrl($postLoginRedirect = '') {
        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => implode(' ', $this->config['scopes']),
            'response_type' => 'code',
            'access_type' => $this->config['access_type'],
            'prompt' => $this->config['prompt']
        ];
        
        // Add state parameter for CSRF protection
        if ($this->config['state_parameter']) {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth_state'] = $state;
            $params['state'] = $state;
        }

        if (is_string($postLoginRedirect) && $postLoginRedirect !== '') {
            $_SESSION['oauth_login_redirect'] = $postLoginRedirect;
        } else {
            unset($_SESSION['oauth_login_redirect']);
        }
        
        return $this->config['auth_url'] . '?' . http_build_query($params);
    }
    
    /**
     * Handle OAuth callback and exchange code for tokens
     */
    public function handleCallback($code, $state = null) {
        // Verify state parameter for CSRF protection (only if enabled)
        if ($this->config['state_parameter'] && isset($_SESSION['oauth_state']) && $state !== $_SESSION['oauth_state']) {
            throw new Exception('Invalid state parameter');
        }
        
        // Exchange authorization code for access token
        $token_data = $this->exchangeCodeForToken($code);
        
        // Get user information from Google
        $user_info = $this->getUserInfo($token_data['access_token']);
        
        // Create or update user account
        $user = $this->createOrUpdateUser($user_info);
        
        // Log user in
        $this->loginUser($user);

        if ($this->config['state_parameter'] && isset($_SESSION['oauth_state'])) {
            unset($_SESSION['oauth_state']);
        }
        
        return $user;
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken($code) {
        $data = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'grant_type' => 'authorization_code',
            'code' => $code
        ];
        
        $response = $this->makeHttpRequest($this->config['token_url'], $data);
        
        if (!$response || !isset($response['access_token'])) {
            throw new Exception('Failed to exchange code for token');
        }
        
        return $response;
    }
    
    /**
     * Get user information from Google
     */
    private function getUserInfo($access_token) {
        $url = $this->config['user_info_url'] . '?access_token=' . $access_token;
        $response = $this->makeHttpRequest($url, null, 'GET');
        
        if (!$response || !isset($response['email'])) {
            throw new Exception('Failed to get user information');
        }
        
        return $response;
    }
    
    /**
     * Create or update user account
     */
    private function createOrUpdateUser($user_info) {
        if (!($this->conn instanceof PDO)) {
            throw new Exception('Database connection unavailable for Google OAuth');
        }
        $email = $user_info['email'];
        $name = $user_info['name'] ?? '';
        $google_id = $user_info['id'] ?? '';
        $picture = $user_info['picture'] ?? '';
        
        // Check if user already exists
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user with Google ID
            $update_query = "UPDATE users SET google_id = :google_id, profile_picture = :picture, updated_at = NOW() WHERE email = :email";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(':google_id', $google_id);
            $update_stmt->bindParam(':picture', $picture);
            $update_stmt->bindParam(':email', $email);
            $update_stmt->execute();
            
            return $existing_user;
        } else {
            // Create new user (password_hash + full_name are NOT NULL in schema)
            $username = $this->generateUsername($name, $email);
            $full_name = trim((string) $name) !== '' ? $name : explode('@', $email)[0];
            $password_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

            $insert_query = "INSERT INTO users (username, email, password_hash, full_name, google_id, profile_picture, role, user_role, is_verified, is_active, login_method, created_at, updated_at) 
                           VALUES (:username, :email, :password_hash, :full_name, :google_id, :picture, 'user', 'consumer', 1, 1, 'google', NOW(), NOW())";
            $insert_stmt = $this->conn->prepare($insert_query);
            $insert_stmt->bindParam(':username', $username);
            $insert_stmt->bindParam(':email', $email);
            $insert_stmt->bindParam(':password_hash', $password_hash);
            $insert_stmt->bindParam(':full_name', $full_name);
            $insert_stmt->bindParam(':google_id', $google_id);
            $insert_stmt->bindParam(':picture', $picture);
            $insert_stmt->execute();
            
            $user_id = $this->conn->lastInsertId();
            
            return [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'role' => 'user',
                'user_role' => 'consumer',
                'full_name' => $full_name,
                'is_verified' => 1
            ];
        }
    }
    
    /**
     * Generate username from name and email
     */
    private function generateUsername($name, $email) {
        if (!empty($name)) {
            $username = strtolower(str_replace(' ', '', $name));
        } else {
            $username = explode('@', $email)[0];
        }
        
        // Ensure username is unique
        $original_username = $username;
        $counter = 1;
        
        while ($this->usernameExists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username) {
        $query = "SELECT id FROM users WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
    
    /**
     * Log user in (session keys must match login.php / api/auth_session.php)
     */
    private function loginUser($user) {
        $user_role = $user['user_role'] ?? '';
        if ($user_role === '' || $user_role === null) {
            $legacy = $user['role'] ?? '';
            if ($legacy === 'admin') {
                $user_role = 'admin';
            } elseif ($legacy === 'vendor') {
                $user_role = 'farmer';
            } else {
                $user_role = 'consumer';
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_role'] = $user_role;
        $_SESSION['full_name'] = $user['full_name'] ?? '';
        $_SESSION['is_verified'] = $user['is_verified'] ?? 1;
        $_SESSION['login_method'] = 'google';

        $marketAccess = getUserMarketAccess((int) $user['id']);
        $_SESSION['market_access'] = $marketAccess;
        $managedMarketIds = [];
        foreach (array_merge($marketAccess['owned'] ?? [], $marketAccess['member'] ?? []) as $marketRow) {
            if (isset($marketRow['id'])) {
                $managedMarketIds[] = (int) $marketRow['id'];
            }
        }
        $_SESSION['managed_market_ids'] = $managedMarketIds;
        $defaultMarketId = null;
        if (!empty($marketAccess['owned'])) {
            $defaultMarketId = (int) $marketAccess['owned'][0]['id'];
        } elseif (!empty($marketAccess['member'])) {
            $defaultMarketId = (int) $marketAccess['member'][0]['id'];
        }
        $_SESSION['active_market_id'] = $defaultMarketId;
    }
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $data = null, $method = 'POST') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('HTTP Error: ' . $http_code);
        }
        
        return json_decode($response, true);
    }
}
?>
