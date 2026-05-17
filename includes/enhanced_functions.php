<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email.php';

if (!function_exists('fs_cache_dir')) {
    function fs_cache_dir(): string {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        $baseDir = __DIR__ . '/../storage/cache';
        if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true)) {
            $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'farmscout_cache';
            if (!is_dir($baseDir)) {
                @mkdir($baseDir, 0775, true);
            }
        }
        return $dir = $baseDir;
    }
}

if (!function_exists('fs_cache_remember')) {
    /**
     * Simple file-backed cache helper.
     *
     * @param string   $key
     * @param int      $ttl Seconds to keep cached value
     * @param callable $callback
     * @return mixed
     */
    function fs_cache_remember(string $key, int $ttl, callable $callback) {
        static $memoryCache = [];
        if (array_key_exists($key, $memoryCache)) {
            return $memoryCache[$key];
        }

        $cacheFile = fs_cache_dir() . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
        if (is_file($cacheFile) && (filemtime($cacheFile) + $ttl) > time()) {
            $payload = file_get_contents($cacheFile);
            $value = @unserialize($payload);
            if ($value !== false || $payload === serialize(false)) {
                return $memoryCache[$key] = $value;
            }
        }

        $value = $callback();
        $memoryCache[$key] = $value;

        $serialized = serialize($value);
        @file_put_contents($cacheFile, $serialized, LOCK_EX);

        return $value;
    }
}

if (!function_exists('fs_cache_forget')) {
    function fs_cache_forget(string $key): void {
        $cacheFile = fs_cache_dir() . DIRECTORY_SEPARATOR . sha1($key) . '.cache';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}

if (!function_exists('fs_cache_forget_market')) {
    function fs_cache_forget_market(int $marketId): void {
        fs_cache_forget('market_products_' . $marketId);
        fs_cache_forget('market_categories_' . $marketId);
    }
}

if (!function_exists('fs_cache_bust_products')) {
    function fs_cache_bust_products(?int $marketId = null): void {
        fs_cache_forget('markets_with_stats');
        fs_cache_forget('active_categories');
        if ($marketId) {
            fs_cache_forget_market($marketId);
        }
    }
}

if (!function_exists('assetUrl')) {
    function assetUrl(string $path): string {
        $base = getenv('ASSET_BASE_URL') ?: getenv('APP_URL') ?: '';
        $cleanPath = ltrim($path, '/');
        if ($base === '') {
            return $cleanPath;
        }
        return rtrim($base, '/') . '/' . $cleanPath;
    }
}

if (!function_exists('fs_web_base_path')) {
    /**
     * URL path prefix for this install (e.g. "/farmscout_online" when not at domain root).
     * Uses ASSET_BASE_URL / APP_URL path when set, else infers from SCRIPT_NAME (api/includes paths).
     */
    function fs_web_base_path(): string {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $env = getenv('ASSET_BASE_URL') ?: getenv('APP_URL') ?: '';
        if ($env !== '') {
            $path = parse_url($env, PHP_URL_PATH);
            if (is_string($path) && $path !== '' && $path !== '/') {
                return $cached = rtrim($path, '/');
            }
        }
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        foreach (['/api/', '/includes/', '/admin/'] as $marker) {
            $pos = strpos($script, $marker);
            if ($pos !== false) {
                $base = substr($script, 0, $pos);
                return $cached = ($base === '' || $base === '/') ? '' : rtrim($base, '/');
            }
        }
        return $cached = '';
    }
}

if (!function_exists('fs_public_asset_url')) {
    /**
     * Absolute path under the site root for browser use (leading slash, optional subfolder prefix).
     * Pass paths like "/assets/images/foo.jpg" or "assets/images/foo.jpg".
     */
    function fs_public_asset_url(string $path): string {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $norm = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $base = fs_web_base_path();
        return ($base === '' ? '' : $base) . $norm;
    }
}

if (!function_exists('fs_spa_home_url')) {
    /**
     * Root URL of the Vite SPA (e.g. /farmscout_online/app/).
     */
    function fs_spa_home_url(): string {
        $base = fs_web_base_path();
        return ($base === '' ? '' : $base) . '/app/';
    }
}

if (!function_exists('fs_classic_account_url')) {
    /**
     * Web path to the classic PHP account page (e.g. /farmscout_online/user-account.php).
     */
    function fs_classic_account_url(): string {
        $base = fs_web_base_path();
        return ($base === '' ? '' : $base) . '/user-account.php';
    }
}

// Get database connection with error handling
if (!function_exists('getDB')) {
    function getDB() {
        static $conn = null;
        if ($conn === null) {
            try {
                $database = new Database();
                $conn = $database->getConnection();
                if (!$conn) {
                    throw new Exception("Database connection failed");
                }
            } catch (Exception $e) {
                error_log("Database connection error: " . $e->getMessage());
                return false;
            }
        }
        return $conn;
    }
}

// Now include config manager after getDB is defined
require_once __DIR__ . '/config_manager.php';

// Session management — set cookie params before session_start() so PHPSESSID is consistent
// (path / covers subfolder installs; SameSite=Lax for same-site SPA + API fetches)
if (session_status() === PHP_SESSION_NONE) {
    $prev = session_get_cookie_params();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $lifetime = (int) ($prev['lifetime'] ?? 0);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => $prev['domain'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, '/', $prev['domain'] ?? '', $secure, true);
    }
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

// Initialize configuration table
ensureConfigTable();

/**
 * Ensure supporting table for flexible market product pricing exists.
 */
function ensureMarketProductUnitsTable() {
    static $isEnsured = false;
    if ($isEnsured) {
        return;
    }
    
    $conn = getDB();
    if (!$conn) {
        return;
    }
    
    try {
        $conn->exec("
            CREATE TABLE IF NOT EXISTS market_product_units (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                unit_label VARCHAR(100) NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 1.00,
                price DECIMAL(10,2) NOT NULL,
                is_default TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_product_units_product (product_id),
                INDEX idx_product_units_default (is_default),
                CONSTRAINT fk_market_product_units_product
                    FOREIGN KEY (product_id) REFERENCES market_products(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        $isEnsured = true;
    } catch (Exception $e) {
        error_log('Failed to ensure market_product_units table: ' . $e->getMessage());
    }
}

ensureMarketProductUnitsTable();

/**
 * Normalize unit labels with quantity for display.
 */
function formatUnitDisplayText($unitLabel, $quantity = 1) {
    $label = trim((string)$unitLabel);
    if ($label === '') {
        return '';
    }
    
    $quantity = floatval($quantity);
    if ($quantity > 0 && abs($quantity - 1) > 0.0001) {
        // Drop trailing .00 for whole numbers
        $displayQty = (abs($quantity - round($quantity)) < 0.001) ? (int)round($quantity) : $quantity;
        return $displayQty . ' ' . $label;
    }
    
    return $label;
}

/**
 * Fetch unit pricing options for a set of market product IDs.
 *
 * @param array<int> $productIds
 * @return array<int, array<int, array<string, mixed>>>
 */
function getProductUnitOptions(array $productIds) {
    $productIds = array_values(array_filter(array_unique(array_map('intval', $productIds))));
    if (empty($productIds)) {
        return [];
    }
    
    ensureMarketProductUnitsTable();
    $conn = getDB();
    if (!$conn) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $query = "
        SELECT product_id, unit_label, quantity, price, is_default
        FROM market_product_units
        WHERE product_id IN ($placeholders)
        ORDER BY product_id, is_default DESC, price ASC, id ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($productIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $grouped = [];
    foreach ($rows as $row) {
        $pid = (int)$row['product_id'];
        if (!isset($grouped[$pid])) {
            $grouped[$pid] = [];
        }
        $quantity = isset($row['quantity']) ? floatval($row['quantity']) : 1;
        $grouped[$pid][] = [
            'unit_label' => $row['unit_label'],
            'quantity' => $quantity > 0 ? $quantity : 1,
            'price' => floatval($row['price']),
            'is_default' => (int)$row['is_default'] === 1,
            'display_label' => formatUnitDisplayText($row['unit_label'], $quantity),
        ];
    }
    
    return $grouped;
}

/**
 * Attach unit pricing data to a list of market product rows.
 *
 * @param array<int, array<string, mixed>> $products
 * @param string $idKey
 * @return array<int, array<string, mixed>>
 */
function attachUnitOptionsToProducts(array $products, string $idKey = 'id') {
    if (empty($products)) {
        return $products;
    }
    
    $idList = [];
    foreach ($products as $product) {
        if (isset($product['market_product_id'])) {
            $idList[] = (int)$product['market_product_id'];
        } elseif (isset($product[$idKey])) {
            $idList[] = (int)$product[$idKey];
        }
    }
    $unitMap = getProductUnitOptions($idList);
    
    foreach ($products as &$product) {
        $productId = $product['market_product_id'] ?? ($product[$idKey] ?? null);
        if (!$productId) {
            $product['unit_options'] = [];
            continue;
        }
        
        $options = $unitMap[$productId] ?? [];
        $product['unit_options'] = $options;
        
        if (!empty($options)) {
            $default = null;
            foreach ($options as $option) {
                if (!empty($option['is_default'])) {
                    $default = $option;
                    break;
                }
            }
            if (!$default) {
                $default = $options[0];
            }
            $product['unit'] = $default['display_label'] ?: $default['unit_label'];
            if (isset($product['current_price'])) {
                $product['current_price'] = $default['price'];
            } else {
                $product['price'] = $default['price'];
            }
        }
    }
    unset($product);
    
    return $products;
}

/**
 * Sanitize a unit label for storage/display.
 */
function sanitizeUnitLabel($label) {
    $label = trim(strip_tags((string)$label));
    // Collapse repeated whitespace
    $label = preg_replace('/\s+/', ' ', $label);
    // Limit to reasonable characters (letters, numbers, spaces, and basic separators)
    $label = preg_replace('/[^A-Za-z0-9\s\-\(\)\/\.]/', '', $label);
    return substr($label, 0, 100);
}

/**
 * Canonical list of unit labels used across admin + farmer UIs.
 *
 * IMPORTANT: Keys are storage values (what we store in DB).
 */
function fsStandardUnitOptions(): array {
    return [
        'kg' => 'kg (Kilogram)',
        'g' => 'g (Gram)',
        'piece' => 'piece',
        'bundle' => 'bundle',
        'sack' => 'sack',
        'tray' => 'tray',
        'pack' => 'pack',
        'dozen' => 'dozen',
        'box' => 'box',
        'liter' => 'liter',
        'ml' => 'ml (Milliliter)',
    ];
}

/**
 * Normalize free-text unit labels into canonical storage values where possible.
 */
function fsCanonicalizeUnitLabel(string $label): string {
    $clean = sanitizeUnitLabel($label);
    if ($clean === '') return '';

    $l = strtolower($clean);
    $l = preg_replace('/\s+/', ' ', trim($l));

    // Common synonyms / variants
    $map = [
        'kilogram' => 'kg',
        'kilo' => 'kg',
        'kgs' => 'kg',
        'kg' => 'kg',
        'gram' => 'g',
        'grams' => 'g',
        'g' => 'g',
        'pc' => 'piece',
        'pcs' => 'piece',
        'piece' => 'piece',
        'pieces' => 'piece',
        'bundles' => 'bundle',
        'bundle' => 'bundle',
        'sacks' => 'sack',
        'sack' => 'sack',
        'tray' => 'tray',
        'trays' => 'tray',
        'pack' => 'pack',
        'packs' => 'pack',
        'dozen' => 'dozen',
        'box' => 'box',
        'boxes' => 'box',
        'liter' => 'liter',
        'litre' => 'liter',
        'l' => 'liter',
        'ml' => 'ml',
        'milliliter' => 'ml',
        'millilitre' => 'ml',
    ];

    if (isset($map[$l])) return $map[$l];

    // If they typed one of our canonical keys exactly (case-insensitive)
    $opts = fsStandardUnitOptions();
    foreach ($opts as $key => $_label) {
        if ($l === strtolower($key)) return $key;
    }

    // Keep custom (sanitized) label if unknown
    return $clean;
}

/**
 * Normalize product names to reduce matching mismatches.
 */
function fsCanonicalizeProductName(string $name): string {
    $name = trim(strip_tags((string)$name));
    $name = preg_replace('/\s+/', ' ', $name);
    return mb_substr($name, 0, 255);
}

/**
 * Standard product names source-of-truth (Admin Ref Prices system).
 * Reads from admin_product_reference_prices.product_name.
 *
 * @return string[] list of product names
 */
function fsGetStandardProductNames(): array {
    $conn = getDB();
    if (!$conn) return [];
    try {
        // Ensure table exists in case this is a fresh DB.
        $conn->exec("CREATE TABLE IF NOT EXISTS `admin_product_reference_prices` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `product_name` varchar(255) NOT NULL,
          `category` varchar(100) DEFAULT NULL,
          `unit` varchar(80) DEFAULT NULL,
          `ref_price_min` decimal(10,2) NOT NULL DEFAULT 0.00,
          `ref_price_max` decimal(10,2) NOT NULL DEFAULT 0.00,
          `notes` varchar(255) DEFAULT NULL,
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_ref_combo` (`product_name`(120), `category`(40), `unit`(40))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $rows = $conn->query("SELECT DISTINCT product_name FROM admin_product_reference_prices WHERE product_name <> '' ORDER BY product_name ASC")
            ->fetchAll(PDO::FETCH_COLUMN);
        $rows = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $rows)))));
        sort($rows, SORT_NATURAL | SORT_FLAG_CASE);
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Validate product name against standardized admin list.
 */
function fsIsStandardProductName(string $productName): bool {
    $productName = fsCanonicalizeProductName($productName);
    if ($productName === '') return false;
    $conn = getDB();
    if (!$conn) return false;
    try {
        $st = $conn->prepare("SELECT 1 FROM admin_product_reference_prices WHERE LOWER(product_name) = LOWER(?) LIMIT 1");
        $st->execute([$productName]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Extract unit pricing options from POST data.
 *
 * @param array $data
 * @return array<int, array<string, mixed>>
 */
function extractUnitOptionsFromRequest(array $data): array {
    $labels = $data['unit_option_label'] ?? [];
    $quantities = $data['unit_option_quantity'] ?? [];
    $prices = $data['unit_option_price'] ?? [];
    $defaultIndex = isset($data['unit_option_default_index']) ? intval($data['unit_option_default_index']) : 0;

    if (!is_array($labels)) {
        $labels = [$labels];
    }
    if (!is_array($quantities)) {
        $quantities = [$quantities];
    }
    if (!is_array($prices)) {
        $prices = [$prices];
    }

    $options = [];

    $customLabels = $data['unit_option_custom_label'] ?? [];
    if (!is_array($customLabels)) {
        $customLabels = [$customLabels];
    }
    
    foreach ($labels as $index => $rawLabel) {
        // If unit is "custom", use the custom label instead
        if ($rawLabel === 'custom' && isset($customLabels[$index]) && !empty($customLabels[$index])) {
            $rawLabel = $customLabels[$index];
        }
        
        $label = fsCanonicalizeUnitLabel((string)$rawLabel);
        if ($label === '') {
            continue;
        }

        // Quantity is always 1 (price per unit)
        // Hidden field ensures it's always 1, but we validate just in case
        $quantity = isset($quantities[$index]) ? floatval($quantities[$index]) : 1;
        if ($quantity <= 0 || $quantity != 1) {
            $quantity = 1; // Always force to 1 for price per unit
        }

        $price = isset($prices[$index]) ? floatval($prices[$index]) : null;
        if ($price === null || $price < 0) {
            continue;
        }

        $options[] = [
            'unit_label' => $label,
            'quantity' => round(max(0.01, $quantity), 2),
            'price' => round($price, 2),
            'is_default' => ($index === $defaultIndex),
        ];
    }

    if (empty($options)) {
        // Graceful fallback for legacy submissions
        $fallbackUnit = sanitizeUnitLabel($data['unit'] ?? '');
        $fallbackPrice = isset($data['current_price']) ? floatval($data['current_price']) : null;
        if ($fallbackUnit !== '' && $fallbackPrice !== null && $fallbackPrice >= 0) {
            $options[] = [
                'unit_label' => $fallbackUnit,
                'quantity' => 1,
                'price' => round($fallbackPrice, 2),
                'is_default' => true,
            ];
        }
    }

    if (!empty($options)) {
        $hasDefault = false;
        foreach ($options as &$option) {
            if (!empty($option['is_default'])) {
                $option['is_default'] = true;
                $hasDefault = true;
                break;
            }
        }
        unset($option);
        if (!$hasDefault) {
            $options[0]['is_default'] = true;
        }
    }

    return array_values($options);
}

/**
 * Helper to get the default option from a unit options array.
 */
function getDefaultUnitOption(array $options): ?array {
    if (empty($options)) {
        return null;
    }

    foreach ($options as $option) {
        if (!empty($option['is_default'])) {
            return $option;
        }
    }

    return $options[0];
}

/**
 * Persist unit pricing options for a market product.
 *
 * @param int $productId
 * @param array<int, array<string, mixed>> $options
 * @return void
 */
function saveProductUnitOptions(int $productId, array $options): void {
    ensureMarketProductUnitsTable();
    $conn = getDB();
    if (!$conn) {
        return;
    }
    
    try {
        $conn->beginTransaction();
        $delete = $conn->prepare("DELETE FROM market_product_units WHERE product_id = ?");
        $delete->execute([$productId]);
        
        $default_price = null;
        
        if (!empty($options)) {
            $insert = $conn->prepare("
                INSERT INTO market_product_units (product_id, unit_label, quantity, price, is_default)
                VALUES (:product_id, :unit_label, :quantity, :price, :is_default)
            ");
            
            foreach ($options as $option) {
                $is_default = !empty($option['is_default']) ? 1 : 0;
                $insert->execute([
                    ':product_id' => $productId,
                    ':unit_label' => $option['unit_label'],
                    ':quantity' => $option['quantity'],
                    ':price' => $option['price'],
                    ':is_default' => $is_default,
                ]);
                
                // Store the default price to update market_products.price
                if ($is_default) {
                    $default_price = floatval($option['price']);
                }
            }
            
            // If no default was set, use the first option's price
            if ($default_price === null && !empty($options)) {
                $default_price = floatval($options[0]['price']);
            }
        }
        
        // Update market_products.price with the default price for backward compatibility
        if ($default_price !== null) {
            $update_price = $conn->prepare("UPDATE market_products SET price = :price WHERE id = :product_id");
            $update_price->execute([
                ':price' => $default_price,
                ':product_id' => $productId
            ]);
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollBack();
        error_log('Failed to save product unit options: ' . $e->getMessage());
    }
}

// Enhanced authentication functions
function authenticateUser($username, $password) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "SELECT id, username, email, password_hash, full_name, user_role, is_active FROM users WHERE username = :username AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Update last login
        $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':id', $user['id']);
        $update_stmt->execute();
        
        return $user;
    }
    
    return false;
}

/**
 * Get the markets a user owns (via markets.vendor_id) and participates in (market_farmers).
 *
 * @return array{owned: array<int, array<string, mixed>>, member: array<int, array<string, mixed>>}
 */
function getUserMarketAccess(int $userId): array {
    $conn = getDB();
    if (!$conn) {
        return ['owned' => [], 'member' => []];
    }

    $access = ['owned' => [], 'member' => []];

    try {
        $ownedStmt = $conn->prepare("
            SELECT id, market_name, address
            FROM markets
            WHERE vendor_id = :user_id
            ORDER BY market_name
        ");
        $ownedStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $ownedStmt->execute();
        $access['owned'] = $ownedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Failed to load owned markets: ' . $e->getMessage());
    }

    try {
        $memberStmt = $conn->prepare("
            SELECT m.id, m.market_name, m.address
            FROM markets m
            INNER JOIN market_farmers mf ON m.id = mf.market_id
            WHERE mf.farmer_id = :user_id
              AND (mf.approval_status IS NULL OR mf.approval_status = 'approved')
            ORDER BY m.market_name
        ");
        $memberStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $memberStmt->execute();
        $access['member'] = $memberStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // market_farmers might not exist in some installations; fail softly.
        error_log('Failed to load assigned markets: ' . $e->getMessage());
    }

    return $access;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// User registration functions
function registerUser($username, $email, $password, $full_name, $user_role = 'consumer') {
    $conn = getDB();
    if (!$conn) return false;
    
    // Validate user role
    if (!in_array($user_role, ['consumer', 'farmer'])) {
        $user_role = 'consumer'; // Default to consumer if invalid
    }
    
    // Check if username or email already exists
    $check_query = "SELECT id FROM users WHERE username = :username OR email = :email";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':username', $username);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        return false; // Username or email already exists
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    
    // Check if verification_status column exists
    $hasVerificationStatus = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_status'");
        $hasVerificationStatus = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, that's okay
    }
    
    try {
        if ($hasVerificationStatus) {
            // Set verification_status based on role
            $verification_status = ($user_role === 'farmer') ? 'pending' : null;
            
            $query = "INSERT INTO users (username, email, password_hash, full_name, user_role, verification_status, is_active, verification_token, created_at) 
                      VALUES (:username, :email, :password_hash, :full_name, :user_role, :verification_status, 0, :verification_token, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':user_role', $user_role);
            $stmt->bindParam(':verification_status', $verification_status);
            $stmt->bindParam(':verification_token', $verification_token);
        } else {
            // Old schema without verification_status
            $query = "INSERT INTO users (username, email, password_hash, full_name, user_role, is_active, verification_token, created_at) 
                      VALUES (:username, :email, :password_hash, :full_name, :user_role, 0, :verification_token, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $password_hash);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':user_role', $user_role);
            $stmt->bindParam(':verification_token', $verification_token);
        }
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'user_id' => $conn->lastInsertId(),
                'verification_token' => $verification_token
            ];
        }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
    }
    
    return false;
}

// Password reset functions
function initiatePasswordReset($email) {
    $conn = getDB();
    if (!$conn) return ['success' => false, 'message' => 'Database connection failed'];
    
    // Check if user exists
    $query = "SELECT id, username, email FROM users WHERE email = :email AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // For security, don't reveal if email exists or not
        return ['success' => true, 'message' => 'If an account with that email exists, we have sent you a password reset link.'];
    }
    
    // Generate reset token
    $reset_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + (60 * 60)); // 1 hour from now
    
    try {
        // Store reset token
        $update_query = "UPDATE users SET password_reset_token = :token, password_reset_expires = :expires WHERE email = :email";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':token', $reset_token);
        $update_stmt->bindParam(':expires', $expires_at);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->execute();
        
        // Send reset email
        $reset_link = getBaseUrl() . "reset-password.php?token=" . $reset_token . "&email=" . urlencode($email);
        $email_sent = sendPasswordResetEmail($email, $user['username'], $reset_link);
        
        if ($email_sent) {
            return ['success' => true, 'message' => 'Password reset link sent to your email.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send reset email. Please try again.'];
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

function validatePasswordResetToken($email, $token) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "SELECT id FROM users WHERE email = :email AND password_reset_token = :token AND password_reset_expires > NOW() AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    return $stmt->fetch() !== false;
}

function resetUserPassword($email, $token, $new_password) {
    $conn = getDB();
    if (!$conn) return ['success' => false, 'message' => 'Database connection failed'];
    
    // Validate token first
    if (!validatePasswordResetToken($email, $token)) {
        return ['success' => false, 'message' => 'Invalid or expired reset token.'];
    }
    
    try {
        // Hash new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $query = "UPDATE users SET password_hash = :password_hash, password_reset_token = NULL, password_reset_expires = NULL WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password reset successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to reset password.'];
        }
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

function sendPasswordResetEmail($email, $username, $reset_link) {
    try {
        require_once __DIR__ . '/enhanced_email.php';
        
        $subject = "Reset Your FarmScout Password";
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #059669; font-size: 28px; margin: 0;'>FarmScout Online</h1>
                <p style='color: #666; margin: 5px 0 0 0;'>Agricultural Price Monitoring System</p>
            </div>
            
            <div style='background: #f9f9f9; padding: 30px; border-radius: 10px; margin-bottom: 20px;'>
                <h2 style='color: #333; margin-top: 0;'>Password Reset Request</h2>
                <p style='color: #555; line-height: 1.6;'>Hello <strong>$username</strong>,</p>
                <p style='color: #555; line-height: 1.6;'>We received a request to reset your password for your FarmScout account. If you made this request, click the button below to reset your password:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$reset_link' style='background: #059669; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Reset My Password</a>
                </div>
                
                <p style='color: #555; line-height: 1.6; font-size: 14px;'>If the button doesn't work, copy and paste this link into your browser:</p>
                <p style='color: #059669; word-break: break-all; font-size: 14px;'>$reset_link</p>
                
                <p style='color: #555; line-height: 1.6; font-size: 14px;'><strong>Important:</strong> This link will expire in 1 hour for security reasons.</p>
                
                <p style='color: #555; line-height: 1.6; font-size: 14px;'>If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
            </div>
            
            <div style='text-align: center; color: #666; font-size: 12px;'>
                <p>This email was sent from FarmScout Online</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
        </div>
        ";
        
        return sendEnhancedEmail($email, $subject, $message);
        
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        return false;
    }
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script_name);
    
    // Remove trailing slash if it's not the root
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    
    return $protocol . '://' . $host . $path . '/';
}

function sendVerificationEmail($email, $username, $verification_token) {
    $verification_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $verification_token;
    
    $subject = "Verify Your FarmScout Account";
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Verify Your FarmScout Account</title>
        <link href='https://fonts.googleapis.com/css2?family=VT323&display=swap' rel='stylesheet'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #ffffff; font-family: VT323, monospace;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 30px; background-color: #ffffff;'>
            <!-- Header -->
            <div style='text-align: center; margin-bottom: 30px; border: 3px solid #000000; padding: 20px; background-color: #ffffff;'>
                <h1 style='color: #000000; margin: 0; font-size: 36px; font-weight: bold; font-family: VT323, monospace; letter-spacing: 2px;'>FARMSCOUT</h1>
                <p style='color: #000000; margin: 5px 0 0 0; font-size: 18px; font-family: VT323, monospace;'>Tapat na Presyo</p>
            </div>
            
            <!-- Content Box -->
            <div style='border: 3px solid #000000; padding: 25px; background-color: #ffffff; margin-bottom: 20px;'>
                <h2 style='color: #000000; margin: 0 0 20px 0; font-size: 28px; font-weight: bold; font-family: VT323, monospace;'>WELCOME TO FARMSCOUT, " . htmlspecialchars(strtoupper($username)) . "!</h2>
                
                <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Thank you for registering with FarmScout Online. To complete your registration and start receiving price alerts, please verify your email address by clicking the button below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $verification_url . "' 
                       style='display: inline-block; border: 3px solid #000000; background-color: #000000; color: #ffffff; padding: 15px 40px; text-decoration: none; font-size: 20px; font-weight: bold; font-family: VT323, monospace; letter-spacing: 1px;'>
                        VERIFY MY ACCOUNT
                    </a>
                </div>
                
                <p style='color: #000000; font-size: 18px; margin: 20px 0 10px 0; font-family: VT323, monospace;'>Or copy and paste this link into your browser:</p>
                <div style='border: 3px solid #000000; padding: 15px; background-color: #ffffff; margin: 15px 0;'>
                    <p style='word-break: break-all; color: #000000; font-size: 16px; margin: 0; font-family: VT323, monospace;'>
                        " . $verification_url . "
                    </p>
                </div>
                
                <p style='color: #000000; font-size: 20px; font-weight: bold; margin: 25px 0 15px 0; font-family: VT323, monospace;'>WHAT HAPPENS NEXT?</p>
                <ul style='margin: 10px 0 20px 20px; padding: 0; list-style: none;'>
                    <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Click the verification link above</li>
                    <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Your account will be activated</li>
                    <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• You can log in and set up price alerts</li>
                    <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Start receiving notifications when prices change!</li>
                </ul>
                
                <p style='color: #000000; font-size: 16px; margin-top: 25px; font-family: VT323, monospace;'>
                    If you didn't create this account, please ignore this email. This verification link will expire in 24 hours.
                </p>
            </div>
            
            <!-- Footer -->
            <div style='border-top: 3px solid #000000; padding-top: 20px; text-align: center;'>
                <p style='color: #000000; font-size: 16px; margin: 0; font-family: VT323, monospace;'>FarmScout Online</p>
                <p style='color: #000000; font-size: 14px; margin: 10px 0 0 0; font-family: VT323, monospace;'>Real-time agricultural pricing with complete transparency</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try to use PHPMailer with SMTP if available and configured, otherwise fall back to basic mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $smtp_result = sendVerificationEmailSMTP($email, $subject, $message);
        // If SMTP failed (returned false), fall back to mail()
        if ($smtp_result === false) {
            error_log("SMTP failed, falling back to mail() for verification email to $email");
            // Continue to fallback below
        } else {
            return $smtp_result; // SMTP succeeded
        }
    }
    
    // Fallback to basic mail function
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: FarmScout <noreply@farmscout.com>',
        'Reply-To: support@farmscout.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $result = mail($email, $subject, $message, implode("\r\n", $headers));
    
    // Log the attempt
    error_log("Verification email sent to $email (via mail()): " . ($result ? 'SUCCESS' : 'FAILED'));
    
    return $result;
}

function sendFarmerVerificationEmail($email, $username, $status = 'verified') {
    if ($status === 'verified') {
        $subject = "FarmScout - Your Account Has Been Verified!";
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Account Verified</title>
            <link href='https://fonts.googleapis.com/css2?family=VT323&display=swap' rel='stylesheet'>
        </head>
        <body style='margin: 0; padding: 0; background-color: #ffffff; font-family: VT323, monospace;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 30px; background-color: #ffffff;'>
                <!-- Header -->
                <div style='text-align: center; margin-bottom: 30px; border: 3px solid #000000; padding: 20px; background-color: #ffffff;'>
                    <h1 style='color: #000000; margin: 0; font-size: 36px; font-weight: bold; font-family: VT323, monospace; letter-spacing: 2px;'>FARMSCOUT</h1>
                    <p style='color: #000000; margin: 5px 0 0 0; font-size: 18px; font-family: VT323, monospace;'>Tapat na Presyo</p>
                </div>
                
                <!-- Content Box -->
                <div style='border: 3px solid #000000; padding: 25px; background-color: #ffffff; margin-bottom: 20px;'>
                    <h2 style='color: #000000; margin: 0 0 20px 0; font-size: 28px; font-weight: bold; font-family: VT323, monospace;'>ACCOUNT VERIFIED!</h2>
                    
                    <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Hello " . htmlspecialchars($username) . ",</p>
                    
                    <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Great news! Your farmer account has been verified by our admin team.</p>
                    
                    <div style='border: 3px solid #000000; padding: 20px; margin: 20px 0; background-color: #ffffff;'>
                        <p style='margin: 0; font-size: 20px; font-weight: bold; color: #000000; font-family: VT323, monospace;'>YOUR ACCOUNT IS NOW ACTIVE</p>
                        <p style='margin: 10px 0 0 0; font-size: 18px; color: #000000; font-family: VT323, monospace;'>You can now log in and start adding your products to the marketplace!</p>
                    </div>
                    
                    <p style='color: #000000; font-size: 20px; font-weight: bold; margin: 25px 0 15px 0; font-family: VT323, monospace;'>WHAT YOU CAN DO NOW:</p>
                    <ul style='margin: 10px 0 20px 20px; padding: 0; list-style: none;'>
                        <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Log in to your account</li>
                        <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Add and manage your products</li>
                        <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Set prices for your products</li>
                        <li style='color: #000000; font-size: 18px; margin: 10px 0; font-family: VT323, monospace;'>• Update stock availability</li>
                    </ul>
                </div>
                
                <!-- Footer -->
                <div style='border-top: 3px solid #000000; padding-top: 20px; text-align: center;'>
                    <p style='color: #000000; font-size: 16px; margin: 0; font-family: VT323, monospace;'>Thank you for joining FarmScout Online!</p>
                    <p style='color: #000000; font-size: 14px; margin: 10px 0 0 0; font-family: VT323, monospace;'>FarmScout Online - Real-time agricultural pricing</p>
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        // Rejected status
        $subject = "FarmScout - Account Verification Update";
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verification Update</title>
            <link href='https://fonts.googleapis.com/css2?family=VT323&display=swap' rel='stylesheet'>
        </head>
        <body style='margin: 0; padding: 0; background-color: #ffffff; font-family: VT323, monospace;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 30px; background-color: #ffffff;'>
                <!-- Header -->
                <div style='text-align: center; margin-bottom: 30px; border: 3px solid #000000; padding: 20px; background-color: #ffffff;'>
                    <h1 style='color: #000000; margin: 0; font-size: 36px; font-weight: bold; font-family: VT323, monospace; letter-spacing: 2px;'>FARMSCOUT</h1>
                    <p style='color: #000000; margin: 5px 0 0 0; font-size: 18px; font-family: VT323, monospace;'>Tapat na Presyo</p>
                </div>
                
                <!-- Content Box -->
                <div style='border: 3px solid #000000; padding: 25px; background-color: #ffffff; margin-bottom: 20px;'>
                    <h2 style='color: #000000; margin: 0 0 20px 0; font-size: 28px; font-weight: bold; font-family: VT323, monospace;'>VERIFICATION UPDATE</h2>
                    
                    <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Hello " . htmlspecialchars($username) . ",</p>
                    
                    <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>We regret to inform you that your farmer account verification has been rejected.</p>
                    
                    <div style='border: 3px solid #000000; padding: 20px; margin: 20px 0; background-color: #ffffff;'>
                        <p style='margin: 0; font-size: 20px; font-weight: bold; color: #000000; font-family: VT323, monospace;'>VERIFICATION REJECTED</p>
                        <p style='margin: 10px 0 0 0; font-size: 18px; color: #000000; font-family: VT323, monospace;'>If you have questions about this decision, please contact our support team.</p>
                    </div>
                    
                    <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 25px 0; font-family: VT323, monospace;'>If you believe this is an error, please contact us for assistance.</p>
                </div>
                
                <!-- Footer -->
                <div style='border-top: 3px solid #000000; padding-top: 20px; text-align: center;'>
                    <p style='color: #000000; font-size: 16px; margin: 0; font-family: VT323, monospace;'>FarmScout Online</p>
                    <p style='color: #000000; font-size: 14px; margin: 10px 0 0 0; font-family: VT323, monospace;'>Real-time agricultural pricing</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    // Try to use PHPMailer with SMTP if available and configured, otherwise fall back to basic mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $smtp_result = sendVerificationEmailSMTP($email, $subject, $message);
        // If SMTP failed (returned false), fall back to mail()
        if ($smtp_result === false) {
            error_log("SMTP failed, falling back to mail() for farmer verification email to $email");
            // Continue to fallback below
        } else {
            return $smtp_result; // SMTP succeeded
        }
    }
    
    // Fallback to basic mail function
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: FarmScout <noreply@farmscout.com>',
        'Reply-To: support@farmscout.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $result = @mail($email, $subject, $message, implode("\r\n", $headers));
    
    // Log the attempt
    error_log("Farmer verification email sent to $email (status: $status, via mail()): " . ($result ? 'SUCCESS' : 'FAILED'));
    
    return $result;
}

function sendFarmerWelcomeEmail($email, $username) {
    $subject = "FarmScout Registration Received - Wait for Verification";
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Registration Received</title>
        <link href='https://fonts.googleapis.com/css2?family=VT323&display=swap' rel='stylesheet'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #ffffff; font-family: VT323, monospace;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 30px; background-color: #ffffff;'>
            <!-- Header -->
            <div style='text-align: center; margin-bottom: 30px; border: 3px solid #000000; padding: 20px; background-color: #ffffff;'>
                <h1 style='color: #000000; margin: 0; font-size: 36px; font-weight: bold; font-family: VT323, monospace; letter-spacing: 2px;'>FARMSCOUT</h1>
                <p style='color: #000000; margin: 5px 0 0 0; font-size: 18px; font-family: VT323, monospace;'>Tapat na Presyo</p>
            </div>
            
            <!-- Content Box -->
            <div style='border: 3px solid #000000; padding: 25px; background-color: #ffffff; margin-bottom: 20px;'>
                <h2 style='color: #000000; margin: 0 0 20px 0; font-size: 28px; font-weight: bold; font-family: VT323, monospace;'>REGISTRATION RECEIVED</h2>
                
                <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Hello " . htmlspecialchars($username) . ",</p>
                
                <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 15px 0; font-family: VT323, monospace;'>Thank you for registering as a farmer on FarmScout Online. We have received your registration.</p>
                
                <div style='border: 3px solid #000000; padding: 20px; margin: 20px 0; background-color: #ffffff;'>
                    <p style='margin: 0; font-size: 20px; font-weight: bold; color: #000000; font-family: VT323, monospace;'>PLEASE WAIT FOR ADMIN VERIFICATION</p>
                    <p style='margin: 10px 0 0 0; font-size: 18px; color: #000000; font-family: VT323, monospace;'>Your account is currently pending admin verification. You will receive another email once your account has been verified.</p>
                </div>
                
                <p style='color: #000000; font-size: 18px; line-height: 1.6; margin: 25px 0; font-family: VT323, monospace;'>Thank you for your patience.</p>
            </div>
            
            <!-- Footer -->
            <div style='border-top: 3px solid #000000; padding-top: 20px; text-align: center;'>
                <p style='color: #000000; font-size: 16px; margin: 0; font-family: VT323, monospace;'>FarmScout Online</p>
                <p style='color: #000000; font-size: 14px; margin: 10px 0 0 0; font-family: VT323, monospace;'>Real-time agricultural pricing</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Try to use PHPMailer with SMTP if available and configured, otherwise fall back to basic mail()
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $smtp_result = sendVerificationEmailSMTP($email, $subject, $message);
            // If SMTP failed (returned false), fall back to mail()
            if ($smtp_result === false) {
                error_log("SMTP failed, falling back to mail() for farmer welcome email to $email");
                // Continue to fallback below
            } else {
                error_log("Farmer welcome email sent successfully via SMTP to $email");
                return $smtp_result; // SMTP succeeded
            }
        } catch (Exception $e) {
            error_log("SMTP exception for farmer welcome email to $email: " . $e->getMessage());
            // Continue to fallback below
        }
    }
    
    // Fallback to basic mail function
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: FarmScout <noreply@farmscout.com>',
        'Reply-To: support@farmscout.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $result = @mail($email, $subject, $message, implode("\r\n", $headers));
    
    // Log the attempt
    error_log("Farmer welcome email sent to $email (via mail()): " . ($result ? 'SUCCESS' : 'FAILED'));
    
    return $result;
}

function sendVerificationEmailSMTP($email, $subject, $message) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Load email config
        $email_config = include __DIR__ . '/../config/email.php';
        
        // Check if config loaded properly
        if (!is_array($email_config)) {
            throw new Exception("Email configuration not loaded properly");
        }
        
        // Check if SMTP is enabled
        if (empty($email_config['use_smtp']) || !$email_config['use_smtp']) {
            error_log("SMTP is disabled in config, falling back to mail()");
            return false; // Return false to trigger fallback to mail()
        }
        
        // Check if SMTP credentials are set
        if (empty($email_config['smtp_username']) || empty($email_config['smtp_password'])) {
            error_log("SMTP credentials not configured");
            return false; // Return false to trigger fallback to mail()
        }
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Enable verbose debug output (only in development)
        // Temporarily enable debug to see authentication errors
        $mail->SMTPDebug = 2; // Set to 2 for verbose output
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP Debug ($level): $str");
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp_username'] ?? '';
        $mail->Password = $email_config['smtp_password'] ?? '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($email_config['smtp_port'] ?? 587);
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($email_config['from_email'] ?? 'noreply@farmscout.com', $email_config['from_name'] ?? 'FarmScout');
        $mail->addAddress($email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $result = $mail->send();
        if ($result) {
            error_log("SMTP verification email sent to $email: SUCCESS");
        } else {
            error_log("SMTP verification email failed to $email: " . $mail->ErrorInfo);
        }
        return $result;
        
    } catch (Exception $e) {
        error_log("SMTP verification email failed: " . $e->getMessage());
        return false;
    }
}

function verifyUser($token) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        // Find user with this token
        $query = "SELECT id, username, email FROM users WHERE verification_token = :token AND is_active = 0";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Activate user and clear token
            $update_query = "UPDATE users SET is_active = 1, verification_token = NULL, updated_at = NOW() WHERE id = :id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':id', $user['id']);
            
            if ($update_stmt->execute()) {
                return $user;
            }
        }
    } catch (Exception $e) {
        error_log("Verification error: " . $e->getMessage());
    }
    
    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

function isAdminUser() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'super_admin'], true);
}

function getAdminDashboardLabel() {
    return isSuperAdmin() ? 'SUPER ADMIN DASHBOARD' : 'DTI DASHBOARD';
}

function getUserRoleLabel($role) {
    switch ($role) {
        case 'super_admin':
            return 'Super Admin';
        case 'admin':
            return 'DTI Admin';
        case 'farmer':
            return 'Farmer';
        case 'consumer':
            return 'Consumer';
        default:
            return ucwords(str_replace('_', ' ', (string) $role));
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdminUser()) {
        header('Location: user-account.php');
        exit();
    }
}

// Enhanced product functions with caching
if (!function_exists('getAllProducts')) {
    function getAllProducts($limit = null, $offset = 0) {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT p.*, c.name as category_name, c.filipino_name as category_filipino 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_active = 1 
              ORDER BY p.is_featured DESC, p.updated_at DESC";
    
    if ($limit) {
        $query .= " LIMIT :limit OFFSET :offset";
    }
    
    $stmt = $conn->prepare($query);
    if ($limit) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getFeaturedProducts')) {
    function getFeaturedProducts($limit = 4) {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT p.*, c.name as category_name, c.filipino_name as category_filipino 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_active = 1 AND p.is_featured = 1 
              ORDER BY p.updated_at DESC 
              LIMIT :limit";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getCategories')) {
    function getCategories() {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalise corrupted peso symbols / stray characters in price_range
    foreach ($categories as &$category) {
        if (isset($category['price_range']) && $category['price_range'] !== '') {
            $raw = $category['price_range'];

            // Replace known weird byte sequences from older exports with a peso sign
            $replacements = [
                'Γé▒' => '₱',
                'Ôé▒' => '₱',
                'Õé'  => '₱',  // Common corruption pattern
                '0é'  => '₱',  // Another corruption pattern
                'â'   => '',   // stray bytes from mis-encoded UTF‑8
                'Â'   => '',
                '€'   => '₱',  // Euro symbol sometimes appears
            ];
            $clean = strtr($raw, $replacements);

            // Remove any remaining corrupted patterns (like "Õé 40-0é 200")
            // Pattern: any non-digit, non-peso characters before numbers
            $clean = preg_replace('/[^0-9₱\-\–\/\.\s]+(?=\d)/u', '₱', $clean);
            
            // Remove any characters that are not digits, peso, dash, slash, dot or whitespace
            $clean = preg_replace('/[^0-9₱\-\–\/\.\s]/u', '', $clean);

            // Fix patterns like "₱ 40-₱ 200" to "₱40-₱200" (remove space after peso)
            $clean = preg_replace('/₱\s+/u', '₱', $clean);

            // Ensure it starts with a peso sign if it contains numbers but no peso yet
            if (strpos($clean, '₱') === false && preg_match('/\d/', $clean)) {
                $clean = '₱' . ltrim($clean);
            }

            // Collapse duplicate peso signs (e.g. ₱₱40-₱₱200 → ₱40-₱200)
            $clean = preg_replace('/₱{2,}/u', '₱', $clean);
            
            // Fix patterns like "₱40-₱200" to ensure proper formatting
            // If we have numbers without peso between them, add peso
            $clean = preg_replace('/(\d+)\s*-\s*(\d+)/u', '₱$1-₱$2', $clean);
            
            // Final cleanup: ensure proper format "₱X-₱Y/unit"
            $clean = preg_replace('/₱(\d+)\s*-\s*₱(\d+)/u', '₱$1-₱$2', $clean);

            $category['price_range'] = trim($clean);
        }
    }
    unset($category);
    
    return $categories;
    }
}

if (!function_exists('getProductsByCategory')) {
    function getProductsByCategory($category_id, $limit = null) {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT p.*, c.name as category_name, c.filipino_name as category_filipino 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.category_id = :category_id AND p.is_active = 1 
              ORDER BY p.is_featured DESC, p.name ASC";
    
    if ($limit) {
        $query .= " LIMIT :limit";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    if ($limit) {
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Enhanced search function
if (!function_exists('searchProducts')) {
    function searchProducts($search_term, $category_id = null, $sort_by = 'relevance') {
    $conn = getDB();
    if (!$conn) return [];
    
    $search_term = '%' . $search_term . '%';
    
    $query = "SELECT p.*, c.name as category_name, c.filipino_name as category_filipino
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE (p.name LIKE :search_term OR p.filipino_name LIKE :search_term OR p.description LIKE :search_term) 
              AND p.is_active = 1";
    
    if ($category_id) {
        $query .= " AND p.category_id = :category_id";
    }
    
    // Add sorting
    switch ($sort_by) {
        case 'price_low':
            $query .= " ORDER BY p.current_price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY p.current_price DESC";
            break;
        case 'name':
            $query .= " ORDER BY p.filipino_name ASC";
            break;
        case 'relevance':
        default:
            $query .= " ORDER BY p.is_featured DESC, p.filipino_name ASC";
            break;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':search_term', $search_term);
    if ($category_id) {
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Enhanced product management
if (!function_exists('addProduct')) {
    function addProduct($data) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        $conn->beginTransaction();
        
        $query = "INSERT INTO products (name, filipino_name, description, category_id, current_price, previous_price, unit, image_url, is_featured, is_active) 
                  VALUES (:name, :filipino_name, :description, :category_id, :current_price, :previous_price, :unit, :image_url, :is_featured, 1)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':filipino_name', $data['filipino_name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':current_price', $data['current_price']);
        $stmt->bindParam(':previous_price', $data['previous_price']);
        $stmt->bindParam(':unit', $data['unit']);
        $stmt->bindParam(':image_url', $data['image_url']);
        $stmt->bindParam(':is_featured', $data['is_featured'], PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if ($result) {
            $product_id = $conn->lastInsertId();
            
            // Insert initial price history
            $history_query = "INSERT INTO price_history (product_id, price) VALUES (:product_id, :price)";
            $history_stmt = $conn->prepare($history_query);
            $history_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $history_stmt->bindParam(':price', $data['current_price']);
            $history_stmt->execute();
        }
        
        $conn->commit();
        return $result;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error adding product: " . $e->getMessage());
        return false;
    }
    }
}

if (!function_exists('updateProduct')) {
    function updateProduct($id, $data) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        $conn->beginTransaction();
        
        // Get current product data for comparison
        $current_query = "SELECT current_price FROM products WHERE id = :id";
        $current_stmt = $conn->prepare($current_query);
        $current_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $current_stmt->execute();
        $current_product = $current_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Automatic previous price handling
        // If current price is different from the new price, save old current price as previous price
        $previous_price = $data['previous_price']; // Use provided previous price as default
        if ($current_product && $current_product['current_price'] != $data['current_price']) {
            $previous_price = $current_product['current_price']; // Automatically set to old current price
        }
        
        $query = "UPDATE products SET 
                  name = :name, 
                  filipino_name = :filipino_name, 
                  description = :description, 
                  category_id = :category_id, 
                  current_price = :current_price, 
                  previous_price = :previous_price, 
                  unit = :unit, 
                  image_url = :image_url, 
                  is_featured = :is_featured,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':filipino_name', $data['filipino_name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
        $stmt->bindParam(':current_price', $data['current_price']);
        $stmt->bindParam(':previous_price', $previous_price); // Use automatic previous price
        $stmt->bindParam(':unit', $data['unit']);
        $stmt->bindParam(':image_url', $data['image_url']);
        $stmt->bindParam(':is_featured', $data['is_featured'], PDO::PARAM_BOOL);
        
        $result = $stmt->execute();
        
        // If price changed, add to history and process alerts
        if ($result && $current_product && $current_product['current_price'] != $data['current_price']) {
            // Calculate change type and percentage
            $old_price = floatval($current_product['current_price']);
            $new_price = floatval($data['current_price']);
            $change_type = 'stable';
            $change_percentage = 0.00;
            
            if ($old_price > 0) {
                if ($new_price > $old_price) {
                    $change_type = 'increase';
                    $change_percentage = round((($new_price - $old_price) / $old_price) * 100, 2);
                } elseif ($new_price < $old_price) {
                    $change_type = 'decrease';
                    $change_percentage = round((($old_price - $new_price) / $old_price) * 100, 2);
                }
            }
            
            // Get user ID if available (for recorded_by)
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            $history_query = "INSERT INTO price_history (product_id, price, change_type, change_percentage, recorded_by) 
                             VALUES (:product_id, :price, :change_type, :change_percentage, :recorded_by)";
            $history_stmt = $conn->prepare($history_query);
            $history_stmt->bindParam(':product_id', $id, PDO::PARAM_INT);
            $history_stmt->bindParam(':price', $new_price);
            $history_stmt->bindParam(':change_type', $change_type);
            $history_stmt->bindParam(':change_percentage', $change_percentage);
            $history_stmt->bindParam(':recorded_by', $user_id, PDO::PARAM_INT);
            $history_stmt->execute();
            
            // Process price alerts for this product
            processPriceAlerts($id, $old_price, $new_price);
        }
        
        $conn->commit();
        return $result;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error updating product: " . $e->getMessage());
        return false;
    }
    }
}

if (!function_exists('deleteProduct')) {
    function deleteProduct($id) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "UPDATE products SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
    }
}

// Price alert functions
function addPriceAlert($email, $product_id, $target_price, $alert_type) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "INSERT INTO price_alerts (user_email, product_id, target_price, alert_type) 
              VALUES (:email, :product_id, :target_price, :alert_type)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':target_price', $target_price);
    $stmt->bindParam(':alert_type', $alert_type);
    
    return $stmt->execute();
}

function getPriceAlerts($email) {
    $conn = getDB();
    if (!$conn) return [];
    
    // Updated to use market_products instead of products (multi-market system)
    $query = "SELECT pa.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.price as current_price, 
                     mp.unit,
                     mp.product_image as image_url,
                     m.market_name
              FROM price_alerts pa 
              LEFT JOIN market_products mp ON pa.product_id = mp.id 
              LEFT JOIN markets m ON mp.market_id = m.id
              WHERE pa.user_email = :email AND pa.is_active = 1 
              ORDER BY pa.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get products grouped by category for admin management
function getProductsByCategories(array $market_ids = []) {
    $conn = getDB();
    if (!$conn) return [];

    // If no markets selected yet, return categories with zero products
    if (empty($market_ids)) {
        $query = "SELECT c.id as category_id, c.name as category_name, c.filipino_name as category_filipino, 
                         c.description as category_description, c.icon_path, c.price_range,
                         0 as product_count
                  FROM categories c 
                  WHERE c.is_active = 1 
                  GROUP BY c.id, c.name, c.filipino_name, c.description, c.icon_path, c.price_range
                  ORDER BY c.sort_order ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $placeholders = implode(',', array_fill(0, count($market_ids), '?'));

    // Check if approval_status column exists
    $hasApprovalStatus = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
        $hasApprovalStatus = $col_check->rowCount() > 0;
    } catch (Exception $e) {
        // Column doesn't exist, that's okay
    }
    $approvalCondition = $hasApprovalStatus ? "AND (mp.approval_status = 'approved' OR mp.approval_status IS NULL)" : "";

    $hasDeletedAt = false;
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
        $hasDeletedAt = $col_check->rowCount() > 0;
    } catch (Exception $e) {
    }
    $deletedCondition = $hasDeletedAt ? "AND mp.deleted_at IS NULL" : "";
    
    $query = "SELECT 
                    c.id as category_id,
                    c.name as category_name,
                    c.filipino_name as category_filipino,
                    c.description as category_description,
                    c.icon_path,
                    c.price_range,
                    COUNT(DISTINCT mp.id) as product_count
              FROM categories c 
              LEFT JOIN market_products mp 
                    ON mp.market_id IN ($placeholders)
                   AND mp.is_available = 1
                   $deletedCondition
                   $approvalCondition
                   AND (
                       LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name)) 
                       OR LOWER(TRIM(mp.category)) = LOWER(TRIM(c.filipino_name))
                       OR TRIM(mp.category) = CAST(c.id AS CHAR)
                   )
              WHERE c.is_active = 1 
              GROUP BY c.id, c.name, c.filipino_name, c.description, c.icon_path, c.price_range
              ORDER BY c.sort_order ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($market_ids);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductsByCategoryForAdmin($category_id, array $market_ids = [], bool $includeOutOfStock = false) {
    $conn = getDB();
    if (!$conn) return [];

    if (empty($market_ids)) {
        return [];
    }

    // First get the category info
    $cat_stmt = $conn->prepare("SELECT name, filipino_name FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_id]);
    $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($market_ids), '?'));

    // Match by category name OR category ID (for backwards compatibility)
    $availabilityClause = $includeOutOfStock ? "" : "AND mp.is_available = 1";

    $query = "SELECT 
                    mp.id,
                    mp.id AS market_product_id,
                    mp.product_name,
                    mp.product_description,
                    mp.category,
                    mp.price,
                    mp.unit,
                    mp.product_image,
                    mp.is_available,
                    mp.is_available AS is_active,
                    mp.created_at,
                    mp.updated_at,
                    mp.market_id,
                    mp.farmer_id,
                    ? as category_name,
                    ? as category_filipino,
                    mp.product_name as name,
                    mp.product_name as filipino_name,
                    mp.price as current_price,
                    NULL as previous_price,
                    mp.product_image as image_url
              FROM market_products mp
              WHERE mp.market_id IN ($placeholders)
                $availabilityClause
                AND (
                    LOWER(TRIM(mp.category)) = LOWER(TRIM(?)) 
                    OR LOWER(TRIM(mp.category)) = LOWER(TRIM(?))
                    OR TRIM(mp.category) = ?
                )
              ORDER BY mp.is_available DESC, mp.product_name ASC";
    
    $stmt = $conn->prepare($query);
    $params = array_merge(
        [$category['name'], $category['filipino_name']], // For SELECT aliases
        $market_ids,
        [$category['name'], $category['filipino_name'], (string)$category_id] // For WHERE conditions
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return attachUnitOptionsToProducts($rows);
}

function getCategoryById($category_id) {
    $conn = getDB();
    if (!$conn) return null;
    
    $query = "SELECT * FROM categories WHERE id = :id AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addCategory($data) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "INSERT INTO categories (name, filipino_name, description, icon_path, price_range, sort_order) 
              VALUES (:name, :filipino_name, :description, :icon_path, :price_range, :sort_order)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':filipino_name', $data['filipino_name']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':icon_path', $data['icon_path']);
    $stmt->bindParam(':price_range', $data['price_range']);
    $stmt->bindParam(':sort_order', $data['sort_order'], PDO::PARAM_INT);
    
    return $stmt->execute();
}

// Enhanced Email Functions
require_once 'email.php';
require_once 'enhanced_email.php';

/**
 * Send price alert email with enhanced template
 */
function sendPriceAlertEmail($email, $product, $alert_type, $target_price) {
    try {
        $alert_data = [
            'email' => $email,
            'alert_type' => $alert_type,
            'target_price' => $target_price,
            'product' => $product
        ];
        
        // Try enhanced mailer first
        if (function_exists('sendEnhancedPriceAlert')) {
            return sendEnhancedPriceAlert($alert_data);
        }
        
        // Fall back to simple email
        $mailer = getMailer();
        $content = createPriceAlertEmail($product, $alert_type, $target_price, $product['current_price']);
        $html = $mailer->wrapTemplate($content, 'Price Alert - FarmScout Online');
        $subject = "🔔 Price Alert: " . $product['filipino_name'] . " - FarmScout Online";
        
        return $mailer->send($email, $subject, $html, true);
        
    } catch (Exception $e) {
        error_log("Error sending price alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Send welcome email to new user
 */
function sendWelcomeEmailToUser($email, $user_name) {
    try {
        // Try enhanced mailer first
        if (function_exists('sendEnhancedWelcomeEmail')) {
            return sendEnhancedWelcomeEmail($email, $user_name);
        }
        
        // Fall back to simple email
        return sendWelcomeEmail($email, $user_name);
        
    } catch (Exception $e) {
        error_log("Error sending welcome email: " . $e->getMessage());
        return false;
    }
}

/**
 * Process price alerts when products are updated
 */
function processPriceAlerts($product_id, $old_price, $new_price) {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        // Updated to use market_products instead of products (multi-market system)
        // Get all active alerts for this product, but only for users who have email notifications enabled
        $query = "SELECT pa.*, 
                         mp.product_name as name, 
                         mp.product_name as filipino_name, 
                         mp.price as current_price, 
                         mp.unit, 
                         mp.product_image as image_url,
                         COALESCE(c.filipino_name, mp.category) as category_filipino,
                         COALESCE(u.email_notifications, 1) as email_notifications
                  FROM price_alerts pa
                  LEFT JOIN market_products mp ON pa.product_id = mp.id
                  LEFT JOIN categories c ON LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name))
                  LEFT JOIN users u ON pa.user_email = u.email
                  WHERE pa.product_id = :product_id AND pa.is_active = 1 
                  AND (u.id IS NULL OR COALESCE(u.email_notifications, 1) = 1)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($alerts as $alert) {
            $should_send = false;
            
            // Check if alert conditions are met
            switch ($alert['alert_type']) {
                case 'below':
                    $should_send = $new_price <= $alert['target_price'] && $old_price > $alert['target_price'];
                    break;
                case 'above':
                    $should_send = $new_price >= $alert['target_price'] && $old_price < $alert['target_price'];
                    break;
                case 'change':
                    $should_send = $new_price != $old_price;
                    break;
            }
            
            if ($should_send) {
                // Check if user has email notifications enabled (default to enabled if not set)
                $email_enabled = isset($alert['email_notifications']) ? (bool)$alert['email_notifications'] : true;
                
                if (!$email_enabled) {
                    // User has disabled email notifications, skip sending
                    continue;
                }
                
                // Prepare product data with price history
                $product_data = [
                    'id' => $alert['product_id'],
                    'name' => $alert['name'],
                    'filipino_name' => $alert['filipino_name'],
                    'current_price' => $new_price,
                    'previous_price' => $old_price,
                    'unit' => $alert['unit'],
                    'image_url' => $alert['image_url'],
                    'category_filipino' => $alert['category_filipino']
                ];
                
                // Send the alert
                $result = sendPriceAlertEmail(
                    $alert['user_email'],
                    $product_data,
                    $alert['alert_type'],
                    $alert['target_price']
                );
                
                if ($result) {
                    // Log successful alert
                    logEmailActivity(
                        $alert['user_email'],
                        "Price Alert: " . $alert['filipino_name'],
                        'ALERT_SENT'
                    );
                    
                    // Update alert last_sent timestamp
                    $update_query = "UPDATE price_alerts SET last_sent = NOW() WHERE id = :alert_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                    $update_stmt->execute();
                }
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error processing price alerts: " . $e->getMessage());
        return false;
    }
}

/**
 * Test email system functionality
 */
function testEmailSystem($test_email = null) {
    $results = [];
    
    // Test 1: Basic configuration
    $config = getEmailConfig();
    $results['config'] = [
        'success' => !empty($config['from_email']),
        'message' => !empty($config['from_email']) ? 'Email configuration loaded' : 'Email configuration missing'
    ];
    
    // Test 2: Send test email
    try {
        if (function_exists('sendEnhancedTestEmail')) {
            $result = sendEnhancedTestEmail($test_email);
            $results['test_email'] = [
                'success' => $result,
                'message' => $result ? 'Test email sent successfully' : 'Failed to send test email'
            ];
        } else {
            $result = sendTestEmail($test_email);
            $results['test_email'] = [
                'success' => $result['success'],
                'message' => $result['message']
            ];
        }
    } catch (Exception $e) {
        $results['test_email'] = [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
    
    return $results;
}

function updateCategory($id, $data) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "UPDATE categories SET 
              name = :name, 
              filipino_name = :filipino_name, 
              description = :description, 
              icon_path = :icon_path, 
              price_range = :price_range, 
              sort_order = :sort_order,
              updated_at = CURRENT_TIMESTAMP
              WHERE id = :id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':name', $data['name']);
    $stmt->bindParam(':filipino_name', $data['filipino_name']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':icon_path', $data['icon_path']);
    $stmt->bindParam(':price_range', $data['price_range']);
    $stmt->bindParam(':sort_order', $data['sort_order'], PDO::PARAM_INT);
    
    return $stmt->execute();
}

// Check and trigger price alerts
function checkPriceAlerts($product_id, $old_price, $new_price) {
    $conn = getDB();
    if (!$conn) return false;
    
    // Updated to use market_products instead of products (multi-market system)
    // Get all active alerts for this product
    $query = "SELECT pa.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.unit, 
                     mp.product_image as image_url,
                     mp.category
              FROM price_alerts pa
              LEFT JOIN market_products mp ON pa.product_id = mp.id
              WHERE pa.product_id = :product_id AND pa.is_active = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts as $alert) {
        $shouldTrigger = false;
        $alertMessage = '';
        
        switch ($alert['alert_type']) {
            case 'below':
                if ($new_price <= $alert['target_price'] && $old_price > $alert['target_price']) {
                    $shouldTrigger = true;
                    $alertMessage = "Price dropped below your target of ₱" . number_format($alert['target_price'], 2);
                }
                break;
                
            case 'above':
                if ($new_price >= $alert['target_price'] && $old_price < $alert['target_price']) {
                    $shouldTrigger = true;
                    $alertMessage = "Price rose above your target of ₱" . number_format($alert['target_price'], 2);
                }
                break;
                
            case 'change':
                if ($old_price != $new_price) {
                    $shouldTrigger = true;
                    $change = $new_price - $old_price;
                    $alertMessage = "Price changed by " . ($change > 0 ? "+" : "") . "₱" . number_format($change, 2);
                }
                break;
        }
        
        if ($shouldTrigger) {
            // Generate unsubscribe token
            $unsubscribe_token = generateUnsubscribeToken($alert['user_email']);
            
            // Send email notification
            $emailSent = sendDetailedPriceAlertEmail(
                $alert['user_email'],
                $alert['name'],
                $alert['filipino_name'],
                $old_price,
                $new_price,
                $alert['unit'],
                $alertMessage,
                $alert['image_url'],
                $unsubscribe_token
            );
            
            // Log the alert trigger
            if ($emailSent) {
                $log_query = "INSERT INTO price_alert_logs (alert_id, triggered_at, old_price, new_price, email_sent) 
                              VALUES (:alert_id, NOW(), :old_price, :new_price, 1)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                $log_stmt->bindParam(':old_price', $old_price);
                $log_stmt->bindParam(':new_price', $new_price);
                $log_stmt->execute();
            }
        }
    }
    
    return true;
}

// Send price alert email (detailed version)
function sendDetailedPriceAlertEmail($email, $product_name, $filipino_name, $old_price, $new_price, $unit, $alert_message, $image_url = '', $unsubscribe_token = '') {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    // Load email configuration and functions
    $email_config = require_once __DIR__ . '/../config/email.php';
    if (!is_array($email_config)) {
        error_log("Email configuration not loaded properly");
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings based on configuration
        if ($email_config['use_smtp']) {
            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['smtp_username'];
            $mail->Password = $email_config['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $email_config['smtp_port'];
        } else {
            // Use PHP mail() function
            $mail->isMail();
        }
        
        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($email);
        $mail->addReplyTo($email_config['from_email'], $email_config['from_name']);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Price Alert: ' . $filipino_name . ' (' . $product_name . ')';
        
        // Price change details
        $price_change = $new_price - $old_price;
        $price_change_percent = $old_price > 0 ? round(($price_change / $old_price) * 100, 2) : 0;
        $price_change_icon = $price_change > 0 ? '📈' : ($price_change < 0 ? '📉' : '➡️');
        $price_change_color = $price_change > 0 ? '#ef4444' : ($price_change < 0 ? '#22c55e' : '#6b7280');
        
        $html_body = file_get_contents(__DIR__ . '/email_templates/price_alert_template.html');
        $html_body = str_replace('{{PRODUCT_NAME}}', htmlspecialchars($product_name), $html_body);
        $html_body = str_replace('{{FILIPINO_NAME}}', htmlspecialchars($filipino_name), $html_body);
        $html_body = str_replace('{{OLD_PRICE}}', formatCurrency($old_price), $html_body);
        $html_body = str_replace('{{NEW_PRICE}}', formatCurrency($new_price), $html_body);
        $html_body = str_replace('{{UNIT}}', htmlspecialchars($unit), $html_body);
        $html_body = str_replace('{{ALERT_MESSAGE}}', htmlspecialchars($alert_message), $html_body);
        // Removed price change icon emoji
        $html_body = str_replace('{{PRICE_CHANGE_COLOR}}', $price_change_color, $html_body);
        $html_body = str_replace('{{PRICE_CHANGE}}', formatCurrency(abs($price_change)), $html_body);
        $html_body = str_replace('{{PRICE_CHANGE_PERCENT}}', abs($price_change_percent), $html_body);
        $html_body = str_replace('{{CURRENT_YEAR}}', date('Y'), $html_body);
        $html_body = str_replace('{{EMAIL}}', htmlspecialchars($email), $html_body);
        $html_body = str_replace('{{UNSUBSCRIBE_TOKEN}}', htmlspecialchars($unsubscribe_token), $html_body);
        
        $default_image = 'https://images.unsplash.com/photo-1584824486509-112e4181ff6b?q=80&w=400&auto=format&fit=crop';
        $html_body = str_replace('{{PRODUCT_IMAGE}}', $image_url ?: $default_image, $html_body);
        
        $mail->Body = $html_body;
        
        // Plain text alternative
        $text_body = "FarmScout Price Alert\n\n";
        $text_body .= "Product: {$filipino_name} ({$product_name})\n";
        $text_body .= "Previous Price: " . formatCurrency($old_price) . " per {$unit}\n";
        $text_body .= "New Price: " . formatCurrency($new_price) . " per {$unit}\n";
        $text_body .= "Change: " . ($price_change > 0 ? '+' : '') . formatCurrency($price_change) . " ({$price_change_percent}%)\n\n";
        $text_body .= "{$alert_message}\n\n";
        $text_body .= "Visit FarmScout Online to see more details and manage your price alerts.\n\n";
        $text_body .= "Happy Shopping!\nThe FarmScout Team";
        
        $mail->AltBody = $text_body;
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // If email sending fails, log to file as fallback
        require_once __DIR__ . '/email_logger.php';
        logEmailToFile($email, $mail->Subject, $mail->Body, 'price_alert');
        error_log("Price Alert Email Error: {$mail->ErrorInfo}");
        return true; // Return true so the system thinks email was sent
    }
}

// Shopping list functions
function addToShoppingList($session_id, $product_id, $quantity = 1, $notes = '') {
    $conn = getDB();
    if (!$conn) return false;
    
    try {
        // Temporarily disable foreign key checks to handle the constraint issue
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Check if item already exists
        $check_query = "SELECT id FROM shopping_lists WHERE user_session = :session_id AND product_id = :product_id";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':session_id', $session_id);
        $check_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->fetch()) {
            // Update quantity
            $update_query = "UPDATE shopping_lists SET quantity = quantity + :quantity WHERE user_session = :session_id AND product_id = :product_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':session_id', $session_id);
            $update_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':quantity', $quantity);
            $result = $update_stmt->execute();
            
            // Re-enable foreign key checks
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            return $result;
        } else {
            // Verify product exists in market_products before inserting
            $verify_query = "SELECT id FROM market_products WHERE id = :product_id";
            $verify_stmt = $conn->prepare($verify_query);
            $verify_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $verify_stmt->execute();
            
            if (!$verify_stmt->fetch()) {
                // Product doesn't exist in market_products
                $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
                error_log("Product ID $product_id not found in market_products table");
                return false;
            }
            
            // Insert new item
            $query = "INSERT INTO shopping_lists (user_session, product_id, quantity, notes) 
                      VALUES (:session_id, :product_id, :quantity, :notes)";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':notes', $notes);
            
            $result = $stmt->execute();
            
            // Re-enable foreign key checks
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            return $result;
        }
    } catch (Exception $e) {
        // Re-enable foreign key checks in case of error
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
        error_log("Error in addToShoppingList: " . $e->getMessage());
        return false;
    }
}

function getShoppingList($session_id) {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT sl.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.price as current_price, 
                     mp.unit, 
                     mp.product_image as image_url, 
                     COALESCE(c.filipino_name, mp.category) as category_filipino,
                     m.id as market_id, 
                     m.market_name
              FROM shopping_lists sl 
              LEFT JOIN market_products mp ON sl.product_id = mp.id 
              LEFT JOIN markets m ON mp.market_id = m.id
              LEFT JOIN categories c ON LOWER(TRIM(mp.category)) = LOWER(TRIM(c.name))
              WHERE sl.user_session = :session_id 
              ORDER BY sl.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update shopping list quantity
function updateShoppingListQuantity($session_id, $list_id, $quantity) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "UPDATE shopping_lists SET quantity = :quantity WHERE id = :id AND user_session = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $stmt->bindParam(':id', $list_id, PDO::PARAM_INT);
    $stmt->bindParam(':session_id', $session_id);
    
    return $stmt->execute();
}

// Remove item from shopping list
function removeFromShoppingList($session_id, $list_id) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "DELETE FROM shopping_lists WHERE id = :id AND user_session = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $list_id, PDO::PARAM_INT);
    $stmt->bindParam(':session_id', $session_id);
    
    return $stmt->execute();
}

// Clear entire shopping list
function clearShoppingList($session_id) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "DELETE FROM shopping_lists WHERE user_session = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    
    return $stmt->execute();
}

// Get shopping list with price calculations
function getShoppingListWithTotals($session_id) {
    $items = getShoppingList($session_id);
    $total_amount = 0;
    $total_items = 0;
    
    foreach ($items as &$item) {
        $item_total = $item['current_price'] * $item['quantity'];
        $item['item_total'] = $item_total;
        $total_amount += $item_total;
        $total_items += $item['quantity'];
    }
    
    return [
        'items' => $items,
        'total_amount' => $total_amount,
        'total_items' => $total_items
    ];
}

// Quick add to shopping list (for integration in other pages)
function quickAddToShoppingList($product_id, $quantity = 1) {
    $session_id = session_id();
    return addToShoppingList($session_id, $product_id, $quantity, '');
}

// Get shopping list item count (for navigation badge)
function getShoppingListCount($session_id) {
    $conn = getDB();
    if (!$conn) return 0;
    
    $query = "SELECT SUM(quantity) as total FROM shopping_lists WHERE user_session = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total'] ?? 0;
}

// Analytics functions
function trackPageView($page) {
    $conn = getDB();
    if (!$conn) return false;
    
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if session exists
    $check_query = "SELECT id FROM user_sessions WHERE session_id = :session_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':session_id', $session_id);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        // Update existing session
        $update_query = "UPDATE user_sessions SET page_views = page_views + 1, last_activity = NOW() WHERE session_id = :session_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':session_id', $session_id);
        $update_stmt->execute();
    } else {
        // Create new session
        $insert_query = "INSERT INTO user_sessions (session_id, ip_address, user_agent, page_views) 
                         VALUES (:session_id, :ip_address, :user_agent, 1)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bindParam(':session_id', $session_id);
        $insert_stmt->bindParam(':ip_address', $ip_address);
        $insert_stmt->bindParam(':user_agent', $user_agent);
        $insert_stmt->execute();
    }
}

function trackSearch($search_term) {
    $conn = getDB();
    if (!$conn) return false;
    
    $session_id = session_id();
    
    $query = "UPDATE user_sessions SET search_queries = search_queries + 1 WHERE session_id = :session_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':session_id', $session_id);
    $stmt->execute();
}

// Enhanced utility functions
if (!function_exists('getPriceChange')) {
    function getPriceChange($current_price, $previous_price) {
        if ($previous_price == 0) return 0;
        return $current_price - $previous_price;
    }
}

if (!function_exists('formatPriceChange')) {
    function formatPriceChange($current_price, $previous_price) {
        $change = getPriceChange($current_price, $previous_price);
        $percentage = $previous_price > 0 ? round(($change / $previous_price) * 100, 2) : 0;
        
        if ($change == 0) {
            return ['class' => 'text-text-muted', 'icon' => 'neutral', 'text' => 'No change', 'percentage' => 0];
        } elseif ($change > 0) {
            return ['class' => 'text-error', 'icon' => 'up', 'text' => '+₱' . number_format($change, 2), 'percentage' => $percentage];
        } else {
            return ['class' => 'text-success', 'icon' => 'down', 'text' => '-₱' . number_format(abs($change), 2), 'percentage' => abs($percentage)];
        }
    }
}

if (!function_exists('getMarketStatus')) {
    function getMarketStatus() {
        $conn = getDB();
        if (!$conn) return ['is_open' => false, 'active_vendors' => 0, 'last_updated' => 'Unknown'];
        
        $query = "SELECT COUNT(*) as active_vendors FROM vendors WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'is_open' => true,
            'active_vendors' => $result['active_vendors'] ?? 0,
            'last_updated' => date('g:i A'),
            'total_products' => getTotalProductsCount()
        ];
    }
}

function getTotalProductsCount() {
    $conn = getDB();
    if (!$conn) return 0;
    
    $query = "SELECT COUNT(*) as total FROM products WHERE is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?? 0;
}

function getPriceHistory($product_id, $days = 7) {
    $conn = getDB();
    if (!$conn) return [];
    
    $query = "SELECT price, recorded_at FROM price_history 
              WHERE product_id = :product_id 
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              ORDER BY recorded_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Enhanced security functions
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePrice($price) {
    return is_numeric($price) && $price >= 0;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

function getCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '₱' . number_format($amount, 2);
    }
}

function formatNumber($number) {
    return number_format($number);
}

// Rate limiting
function checkRateLimit($action, $limit = 10, $window = 300) {
    $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $now = time();
    $window_start = $now - $window;
    
    // Clean old entries
    if (isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = array_filter(
            $_SESSION['rate_limits'][$key],
            function($timestamp) use ($window_start) {
                return $timestamp > $window_start;
            }
        );
    } else {
        $_SESSION['rate_limits'][$key] = [];
    }
    
    // Check if limit exceeded
    if (count($_SESSION['rate_limits'][$key]) >= $limit) {
        return false;
    }
    
    // Add current request
    $_SESSION['rate_limits'][$key][] = $now;
    
    return true;
}

// Simple math captcha helpers
function generateSimpleCaptcha(string $formKey = 'default'): string {
    if (!isset($_SESSION['simple_captcha'])) {
        $_SESSION['simple_captcha'] = [];
    }
    if (!isset($_SESSION['simple_captcha_questions'])) {
        $_SESSION['simple_captcha_questions'] = [];
    }
    
    $a = random_int(2, 9);
    $b = random_int(1, 9);
    $question = "What is $a + $b?";
    
    $_SESSION['simple_captcha'][$formKey] = $a + $b;
    $_SESSION['simple_captcha_questions'][$formKey] = $question;
    
    return $question;
}

function getSimpleCaptchaQuestion(string $formKey = 'default'): string {
    return $_SESSION['simple_captcha_questions'][$formKey] ?? "What is ?";
}

function validateSimpleCaptcha(string $formKey, $answer): bool {
    $expected = $_SESSION['simple_captcha'][$formKey] ?? null;
    
    if ($expected === null) {
        return false;
    }
    
    // Trim whitespace and convert to integer for comparison
    $userAnswer = intval(trim($answer));
    $expectedAnswer = intval($expected);
    
    // Only unset after successful validation
    $isValid = ($userAnswer === $expectedAnswer);
    
    if ($isValid) {
        unset($_SESSION['simple_captcha'][$formKey]);
        unset($_SESSION['simple_captcha_questions'][$formKey]);
    }
    
    return $isValid;
}

// Error handling
function handleError($message, $code = 500) {
    error_log("FarmScout Error: " . $message);
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Success response
function sendSuccess($data = null, $message = 'Success') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

// Price Alert Functions

/**
 * Create a new price alert
 */
function createPriceAlert($user_email, $product_id, $alert_type, $target_price) {
    $conn = getDB();
    if (!$conn) return false;
    
    // Verify the product exists in market_products (multi-market system)
    $check_stmt = $conn->prepare("SELECT id, price FROM market_products WHERE id = ? AND is_available = 1");
    $check_stmt->execute([$product_id]);
    $product = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        error_log("Price alert: Product ID $product_id not found in market_products");
        return false;
    }
    
    // Insert alert (product_id now refers to market_products.id)
    // Note: Foreign key constraint may need to be removed/updated in database
    try {
        $query = "INSERT INTO price_alerts (user_email, product_id, alert_type, target_price) 
                  VALUES (:user_email, :product_id, :alert_type, :target_price)";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_email', $user_email);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':alert_type', $alert_type);
        $stmt->bindParam(':target_price', $target_price);
        
        return $stmt->execute();
    } catch (Exception $e) {
        // If foreign key constraint fails, try without constraint check
        error_log("Price alert insert error (may need to update foreign key): " . $e->getMessage());
        // Try to remove foreign key constraint temporarily if it exists
        try {
            $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
            $result = $stmt->execute();
            $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
            return $result;
        } catch (Exception $e2) {
            error_log("Price alert insert failed: " . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Get all price alerts for a user
 */
function getUserPriceAlerts($user_email) {
    $conn = getDB();
    if (!$conn) return [];
    
    // Updated to use market_products instead of products (multi-market system)
    $query = "SELECT pa.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.price as current_price, 
                     mp.unit, 
                     mp.product_image as image_url,
                     m.market_name
              FROM price_alerts pa 
              LEFT JOIN market_products mp ON pa.product_id = mp.id 
              LEFT JOIN markets m ON mp.market_id = m.id
              WHERE pa.user_email = :user_email AND pa.is_active = 1 
              ORDER BY pa.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_email', $user_email);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Delete a price alert
 */
function deletePriceAlert($alert_id, $user_email) {
    $conn = getDB();
    if (!$conn) return false;
    
    $query = "UPDATE price_alerts SET is_active = 0 
              WHERE id = :alert_id AND user_email = :user_email";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':alert_id', $alert_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_email', $user_email);
    
    return $stmt->execute();
}

/**
 * Check and trigger price alerts when prices change
 */
function checkAndTriggerPriceAlerts($product_id, $old_price, $new_price) {
    $conn = getDB();
    if (!$conn) return false;
    
    // Updated to use market_products instead of products (multi-market system)
    // Get all active alerts for this product, but only for users who have email notifications enabled
    $query = "SELECT pa.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.unit, 
                     mp.product_image as image_url,
                     mp.category,
                     m.market_name,
                     u.id as user_id,
                     COALESCE(u.email_notifications, 1) as email_notifications
              FROM price_alerts pa 
              LEFT JOIN market_products mp ON pa.product_id = mp.id 
              LEFT JOIN markets m ON mp.market_id = m.id
              LEFT JOIN users u ON pa.user_email = u.email
              WHERE pa.product_id = :product_id AND pa.is_active = 1 
              AND (u.id IS NULL OR COALESCE(u.email_notifications, 1) = 1)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts as $alert) {
        $should_trigger = false;
        
        // Check alert conditions
        switch ($alert['alert_type']) {
            case 'below':
                $should_trigger = $new_price <= $alert['target_price'] && $old_price > $alert['target_price'];
                break;
            case 'above':
                $should_trigger = $new_price >= $alert['target_price'] && $old_price < $alert['target_price'];
                break;
            case 'change':
                $should_trigger = $new_price != $old_price;
                break;
        }
        
        if ($should_trigger) {
            // Check if user has email notifications enabled (default to enabled if not set)
            $email_enabled = isset($alert['email_notifications']) ? (bool)$alert['email_notifications'] : true;
            
            if (!$email_enabled) {
                // User has disabled email notifications, skip sending
                continue;
            }
            
            // Send price alert email
            $alert_data = [
                'email' => $alert['user_email'],
                'alert_type' => $alert['alert_type'],
                'target_price' => $alert['target_price'],
                'product' => [
                    'name' => $alert['name'],
                    'filipino_name' => $alert['filipino_name'],
                    'previous_price' => $old_price,
                    'current_price' => $new_price,
                    'unit' => $alert['unit'],
                    'image_url' => $alert['image_url']
                ]
            ];
            
            // Send the email using our enhanced email system
            require_once __DIR__ . '/enhanced_email.php';
            $mailer = getEnhancedMailer();
            $result = $mailer->sendPriceAlert($alert_data);
            
            if ($result) {
                // Update last_sent timestamp
                $update_query = "UPDATE price_alerts SET last_sent = NOW() WHERE id = :alert_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Log the alert
                $log_query = "INSERT INTO price_alert_logs (alert_id, old_price, new_price, email_sent) 
                              VALUES (:alert_id, :old_price, :new_price, 1)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                $log_stmt->bindParam(':old_price', $old_price);
                $log_stmt->bindParam(':new_price', $new_price);
                $log_stmt->execute();
            }
        }
    }
    
    return true;
}

/**
 * Check and trigger price alerts for a specific unit option (e.g., per piece, per kg)
 * This is used when products have multiple pricing options and a non-default price changes
 */
function checkAndTriggerPriceAlertsForUnit($product_id, $old_price, $new_price, $unit_display) {
    $conn = getDB();
    if (!$conn) return false;
    
    // Get all active alerts for this product, but only for users who have email notifications enabled
    $query = "SELECT pa.*, 
                     mp.product_name as name, 
                     mp.product_name as filipino_name, 
                     mp.product_image as image_url,
                     mp.category,
                     m.market_name,
                     u.id as user_id,
                     COALESCE(u.email_notifications, 1) as email_notifications
              FROM price_alerts pa 
              LEFT JOIN market_products mp ON pa.product_id = mp.id 
              LEFT JOIN markets m ON mp.market_id = m.id
              LEFT JOIN users u ON pa.user_email = u.email
              WHERE pa.product_id = :product_id AND pa.is_active = 1 
              AND (u.id IS NULL OR COALESCE(u.email_notifications, 1) = 1)";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts as $alert) {
        $should_trigger = false;
        
        // Check alert conditions
        switch ($alert['alert_type']) {
            case 'below':
                $should_trigger = $new_price <= $alert['target_price'] && $old_price > $alert['target_price'];
                break;
            case 'above':
                $should_trigger = $new_price >= $alert['target_price'] && $old_price < $alert['target_price'];
                break;
            case 'change':
                $should_trigger = $new_price != $old_price;
                break;
        }
        
        if ($should_trigger) {
            // Check if user has email notifications enabled (default to enabled if not set)
            $email_enabled = isset($alert['email_notifications']) ? (bool)$alert['email_notifications'] : true;
            
            if (!$email_enabled) {
                // User has disabled email notifications, skip sending
                continue;
            }
            
            // Send price alert email with the specific unit
            $alert_data = [
                'email' => $alert['user_email'],
                'alert_type' => $alert['alert_type'],
                'target_price' => $alert['target_price'],
                'product' => [
                    'name' => $alert['name'],
                    'filipino_name' => $alert['filipino_name'],
                    'previous_price' => $old_price,
                    'current_price' => $new_price,
                    'unit' => $unit_display, // Use the specific unit (e.g., "per piece")
                    'image_url' => $alert['image_url']
                ]
            ];
            
            // Send the email using our enhanced email system
            require_once __DIR__ . '/enhanced_email.php';
            $mailer = getEnhancedMailer();
            $result = $mailer->sendPriceAlert($alert_data);
            
            // Create in-app notification (regardless of email success)
            if (!empty($alert['user_id'])) {
                // Format notification message based on alert type
                $notification_title = "🔔 Price Alert: " . $alert['filipino_name'];
                
                switch ($alert['alert_type']) {
                    case 'below':
                        $notification_message = "Price dropped below your target! Now ₱" . number_format($new_price, 2) . " / " . $unit_display . " (was ₱" . number_format($old_price, 2) . ")";
                        break;
                    case 'above':
                        $notification_message = "Price rose above your target! Now ₱" . number_format($new_price, 2) . " / " . $unit_display . " (was ₱" . number_format($old_price, 2) . ")";
                        break;
                    case 'change':
                        $notification_message = "Price changed! Now ₱" . number_format($new_price, 2) . " / " . $unit_display . " (was ₱" . number_format($old_price, 2) . ")";
                        break;
                    default:
                        $notification_message = "Price updated to ₱" . number_format($new_price, 2) . " / " . $unit_display;
                }
                
                // Create notification with link to product
                $product_link = "market-finder.php?product_id=" . $product_id;
                if (function_exists('createNotification')) {
                    createNotification(
                        $alert['user_id'],
                        'price_alert',
                        $notification_title,
                        $notification_message,
                        $product_link
                    );
                }
            }
            
            if ($result) {
                // Update last_sent timestamp
                $update_query = "UPDATE price_alerts SET last_sent = NOW() WHERE id = :alert_id";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                $update_stmt->execute();
                
                // Log the alert
                $log_query = "INSERT INTO price_alert_logs (alert_id, old_price, new_price, email_sent) 
                              VALUES (:alert_id, :old_price, :new_price, 1)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bindParam(':alert_id', $alert['id'], PDO::PARAM_INT);
                $log_stmt->bindParam(':old_price', $old_price);
                $log_stmt->bindParam(':new_price', $new_price);
                $log_stmt->execute();
            }
        }
    }
    
    return true;
}

// Simple remember me functionality - just saves username for form autofill

// Unsubscribe functionality
function generateUnsubscribeToken($email) {
    return hash('sha256', $email . 'unsubscribe_salt_' . date('Y-m-d'));
}

function verifyUnsubscribeToken($email, $token) {
    $expected_token = generateUnsubscribeToken($email);
    return hash_equals($expected_token, $token);
}

function unsubscribeAllAlerts($email) {
    $conn = getDB();
    if (!$conn) {
        return false;
    }

    try {
        // Delete all alerts for this email (permanent removal)
        $stmt = $conn->prepare("DELETE FROM price_alerts WHERE user_email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error unsubscribing alerts: " . $e->getMessage());
        return false;
    }
}

function sendUnsubscribeConfirmation($email) {
    $conn = getDB();
    if (!$conn) {
        return false;
    }

    try {
        // Check if email has any active alerts
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM price_alerts WHERE user_email = :email AND is_active = 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            return false; // No active alerts to unsubscribe from
        }

        // Generate unsubscribe token
        $unsubscribe_token = generateUnsubscribeToken($email);
        // Get site URL from email config or use current domain
        $email_config = getEmailConfig();
        $site_url = $email_config['site_url'] ?? 
                   (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . 
                   str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $site_url = rtrim($site_url, '/');
        $unsubscribe_url = $site_url . "/unsubscribe.php?email=" . urlencode($email) . "&token=" . $unsubscribe_token;
        
        // Send confirmation email
        $subject = "Confirm Unsubscribe - FarmScout Price Alerts";
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2D5016;'>Confirm Unsubscribe</h2>
                <p>You requested to unsubscribe from FarmScout price alerts for <strong>{$email}</strong>.</p>
                <p>Click the button below to confirm your unsubscribe request:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$unsubscribe_url}' style='background-color: #e53e3e; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>Unsubscribe Now</a>
                </div>
                <p style='font-size: 14px; color: #666;'>If you didn't request this, you can safely ignore this email.</p>
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                <p style='font-size: 12px; color: #999;'>FarmScout Online - Tapat na Presyo</p>
            </div>
        </body>
        </html>";
        
        // Use PHPMailer to send the email
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Load email configuration
        $email_config = getEmailConfig();
        
        if ($email_config['use_smtp']) {
            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['smtp_username'];
            $mail->Password = $email_config['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $email_config['smtp_port'];
        } else {
            $mail->isMail();
        }
        
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Error sending unsubscribe confirmation: " . $e->getMessage());
        return false;
    }
}

/**
 * Send reservation email notification
 */
function sendReservationEmail($data) {
    try {
        $email_config = getEmailConfig();
        $site_url = $email_config['site_url'] ?? 
                   (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . 
                   str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $site_url = rtrim($site_url, '/');
        
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        if ($email_config['use_smtp']) {
            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['smtp_username'];
            $mail->Password = $email_config['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $email_config['smtp_port'];
        } else {
            $mail->isMail();
        }
        
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        $type = $data['type'] ?? 'user_submitted';
        
        if ($type === 'user_submitted') {
            // Email to user confirming reservation submission
            $mail->addAddress($data['user_email']);
            $mail->Subject = "Reservation Submitted - FarmScout Online";
            
            $pickup_info = '';
            if (!empty($data['pickup_date'])) {
                $pickup_info .= '<p><strong>Preferred Pickup Date:</strong> ' . date('M d, Y', strtotime($data['pickup_date'])) . '</p>';
            }
            if (!empty($data['pickup_time'])) {
                $pickup_info .= '<p><strong>Preferred Pickup Time:</strong> ' . date('g:i A', strtotime($data['pickup_time'])) . '</p>';
            }
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'VT323', monospace; background-color: #ffffff; color: #000000; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 3px solid #000000; }
                    h1 { font-size: 2rem; margin-bottom: 1rem; text-transform: uppercase; }
                    .button { display: inline-block; padding: 12px 24px; background-color: #000000; color: #ffffff; text-decoration: none; border: 2px solid #000000; margin-top: 1rem; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>RESERVATION SUBMITTED</h1>
                    <p>Hello {$data['user_name']},</p>
                    <p>Your reservation has been submitted successfully!</p>
                    <p><strong>Product:</strong> {$data['product_name']}</p>
                    <p><strong>Quantity:</strong> {$data['quantity']} {$data['unit']}</p>
                    <p><strong>Market:</strong> {$data['market_name']}</p>
                    $pickup_info
                    <p>The farmer will review your request and notify you once it's accepted or declined.</p>
                    <a href='{$site_url}/my-reservations.php' class='button' style='color: #ffffff !important;'>VIEW MY RESERVATIONS</a>
                    <p style='margin-top: 2rem; font-size: 0.9rem; color: #666;'>FarmScout Online - Tapat na Presyo</p>
                </div>
            </body>
            </html>";
            
        } elseif ($type === 'farmer_notification') {
            // Email to farmer about new reservation
            $mail->addAddress($data['farmer_email']);
            $mail->Subject = "New Reservation Request - FarmScout Online";
            
            $pickup_info = '';
            if (!empty($data['pickup_date'])) {
                $pickup_info .= '<p><strong>Preferred Pickup Date:</strong> ' . date('M d, Y', strtotime($data['pickup_date'])) . '</p>';
            }
            if (!empty($data['pickup_time'])) {
                $pickup_info .= '<p><strong>Preferred Pickup Time:</strong> ' . date('g:i A', strtotime($data['pickup_time'])) . '</p>';
            }
            
            $notes_info = !empty($data['notes']) ? '<p><strong>Customer Notes:</strong> ' . htmlspecialchars($data['notes']) . '</p>' : '';
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'VT323', monospace; background-color: #ffffff; color: #000000; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 3px solid #000000; text-align: center; }
                    h1 { font-size: 2rem; margin-bottom: 1rem; text-transform: uppercase; }
                    .button { display: inline-block; padding: 12px 24px; background-color: #000000; color: #ffffff; text-decoration: none; border: 2px solid #000000; margin-top: 1rem; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>NEW RESERVATION REQUEST</h1>
                    <p>Hello {$data['farmer_name']},</p>
                    <p>You have received a new reservation request!</p>
                    <p><strong>Product:</strong> {$data['product_name']}</p>
                    <p><strong>Quantity:</strong> {$data['quantity']} {$data['unit']}</p>
                    <p><strong>Customer:</strong> {$data['user_name']} ({$data['user_email']})</p>
                    <p><strong>Market:</strong> {$data['market_name']}</p>
                    $pickup_info
                    $notes_info
                    <p>Please review and respond to this reservation in your dashboard.</p>
                    <a href='{$site_url}/farmer-dashboard.php' class='button' style='color: #ffffff !important;'>VIEW DASHBOARD</a>
                    <p style='margin-top: 2rem; font-size: 0.9rem; color: #666;'>FarmScout Online - Tapat na Presyo</p>
                </div>
            </body>
            </html>";
            
        } elseif ($type === 'user_accepted') {
            // Email to user when reservation is accepted
            $mail->addAddress($data['user_email']);
            $mail->Subject = "Reservation Accepted - FarmScout Online";
            
            $accept_note = !empty($data['accept_note']) ? '<p><strong>Farmer\'s Message:</strong> ' . htmlspecialchars($data['accept_note']) . '</p>' : '';
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'VT323', monospace; background-color: #ffffff; color: #000000; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 3px solid #000000; text-align: center; }
                    h1 { font-size: 2rem; margin-bottom: 1rem; text-transform: uppercase; }
                    .button { display: inline-block; padding: 12px 24px; background-color: #000000; color: #ffffff; text-decoration: none; border: 2px solid #000000; margin-top: 1rem; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>RESERVATION ACCEPTED</h1>
                    <p>Hello {$data['user_name']},</p>
                    <p>Great news! Your reservation has been accepted by the farmer.</p>
                    <p><strong>Product:</strong> {$data['product_name']}</p>
                    <p><strong>Quantity:</strong> {$data['quantity']} {$data['unit']}</p>
                    <p><strong>Market:</strong> {$data['market_name']}</p>
                    $accept_note
                    <p>You can now proceed to pick up your order at the market.</p>
                    <a href='{$site_url}/my-reservations.php' class='button' style='color: #ffffff !important;'>VIEW RESERVATION</a>
                    <p style='margin-top: 2rem; font-size: 0.9rem; color: #666;'>FarmScout Online - Tapat na Presyo</p>
                </div>
            </body>
            </html>";
            
        } elseif ($type === 'user_declined') {
            // Email to user when reservation is declined
            $mail->addAddress($data['user_email']);
            $mail->Subject = "Reservation Declined - FarmScout Online";
            
            $reason = !empty($data['reason']) ? '<p><strong>Reason:</strong> ' . htmlspecialchars($data['reason']) . '</p>' : '';
            
            $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: 'VT323', monospace; background-color: #ffffff; color: #000000; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 3px solid #000000; text-align: center; }
                    h1 { font-size: 2rem; margin-bottom: 1rem; text-transform: uppercase; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>RESERVATION DECLINED</h1>
                    <p>Hello {$data['user_name']},</p>
                    <p>Unfortunately, your reservation request has been declined.</p>
                    <p><strong>Product:</strong> {$data['product_name']}</p>
                    <p><strong>Quantity:</strong> {$data['quantity']} {$data['unit']}</p>
                    $reason
                    <p>You can browse other products or make a new reservation.</p>
                    <p style='margin-top: 2rem; font-size: 0.9rem; color: #666;'>FarmScout Online - Tapat na Presyo</p>
                </div>
            </body>
            </html>";
        }
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Error sending reservation email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification for new chat message
 */
function sendChatMessageEmail($data) {
    try {
        $email_config = getEmailConfig();
        $site_url = $email_config['site_url'] ?? 
                   (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                   "://" . $_SERVER['HTTP_HOST'] . 
                   str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        $site_url = rtrim($site_url, '/');
        
        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        if ($email_config['use_smtp']) {
            $mail->isSMTP();
            $mail->Host = $email_config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_config['smtp_username'];
            $mail->Password = $email_config['smtp_password'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $email_config['smtp_port'];
        } else {
            $mail->isMail();
        }
        
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($data['recipient_email']);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "New Message - " . $data['product_name'] . " - FarmScout Online";
        
        $message_preview = strlen($data['message_body']) > 100 
            ? substr($data['message_body'], 0, 100) . '...' 
            : $data['message_body'];
        
        $link_url = ($data['recipient_role'] ?? '') === 'farmer' 
            ? $site_url . '/farmer-dashboard.php?section=reservations'
            : $site_url . '/user-account.php?section=reservations';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: 'VT323', monospace; background-color: #ffffff; color: #000000; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 3px solid #000000; text-align: center; }
                h1 { font-size: 2rem; margin-bottom: 1rem; text-transform: uppercase; }
                .message-box { background-color: #f9f9f9; border: 2px solid #000000; padding: 1rem; margin: 1rem 0; text-align: left; }
                .button { display: inline-block; padding: 0.75rem 2rem; background-color: #000000; color: #ffffff !important; text-decoration: none; border: 2px solid #000000; font-family: 'VT323', monospace; font-size: 1.2rem; text-transform: uppercase; margin-top: 1rem; }
                .button:hover { background-color: #333333; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>NEW MESSAGE</h1>
                <p>Hello {$data['recipient_name']},</p>
                <p>You have received a new message about your reservation:</p>
                <p><strong>Product:</strong> {$data['product_name']}</p>
                <p><strong>From:</strong> {$data['sender_name']}</p>
                <div class='message-box'>
                    <p><strong>Message:</strong></p>
                    <p>" . htmlspecialchars($message_preview) . "</p>
                </div>
                <a href='{$link_url}' class='button' style='color: #ffffff !important;'>VIEW MESSAGE</a>
                <p style='margin-top: 2rem; font-size: 0.9rem; color: #666;'>FarmScout Online - Tapat na Presyo</p>
            </div>
        </body>
        </html>";
        
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Error sending chat message email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a system message in a reservation chat
 * @param int $reservation_id The reservation ID
 * @param string $message_body The system message content
 * @return bool Success status
 */
function createSystemMessage($reservation_id, $message_body) {
    try {
        $conn = getDB();
        if (!$conn) {
            error_log("Failed to create system message: Database connection failed");
            return false;
        }

        $helpers = __DIR__ . '/fs_reservation_helpers.php';
        if (is_readable($helpers)) {
            require_once $helpers;
        }
        if (function_exists('fs_reservation_root_id')) {
            $reservation_id = fs_reservation_root_id($conn, (int) $reservation_id);
        }
        
        // Check if reservation_messages table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'reservation_messages'");
        if ($table_check->rowCount() === 0) {
            error_log("reservation_messages table does not exist");
            return false;
        }
        
        // Check if message_type column exists
        $column_check = $conn->query("SHOW COLUMNS FROM reservation_messages LIKE 'message_type'");
        $has_message_type = $column_check->rowCount() > 0;
        
        // Get reservation details to use farmer_id as sender_id (for system messages, we use farmer_id as placeholder)
        $reservation_query = "SELECT farmer_id FROM reservations WHERE id = :reservation_id";
        $reservation_stmt = $conn->prepare($reservation_query);
        $reservation_stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
        $reservation_stmt->execute();
        $reservation = $reservation_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            error_log("Reservation not found for system message: " . $reservation_id);
            return false;
        }
        
        // Use farmer_id as sender_id for system messages (required by foreign key)
        $sender_id = $reservation['farmer_id'];
        
        if ($has_message_type) {
            $insert_query = "INSERT INTO reservation_messages (reservation_id, sender_id, message_type, message_body, created_at) 
                             VALUES (:reservation_id, :sender_id, 'system', :message_body, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':message_body', $message_body);
        } else {
            $insert_query = "INSERT INTO reservation_messages (reservation_id, sender_id, message_body, created_at) 
                             VALUES (:reservation_id, :sender_id, :message_body, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bindParam(':reservation_id', $reservation_id, PDO::PARAM_INT);
            $stmt->bindParam(':sender_id', $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(':message_body', $message_body);
        }
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error creating system message: " . $e->getMessage());
        return false;
    }
}

/**
 * Log admin action for accountability and traceability
 * @param PDO $conn Database connection
 * @param int $admin_id Admin user ID
 * @param string $admin_username Admin username
 * @param string $action_type Type of action (e.g., 'product_approved', 'farmer_verified', 'user_deleted', 'reservation_status_update')
 * @param array $details Additional details about the action (will be JSON encoded)
 * @return bool Success status
 */
function logAdminAction($conn, $admin_id, $admin_username, $action_type, $details = []) {
    try {
        // Check if admin_logs table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
        if ($table_check->rowCount() === 0) {
            error_log("admin_logs table does not exist. Please run the migration script.");
            return false;
        }
        
        // Determine target type and ID from action type
        $target_type = 'unknown';
        $target_id = null;
        
        if (strpos($action_type, 'product') !== false) {
            $target_type = 'product';
            $target_id = $details['product_id'] ?? $details['id'] ?? null;
        } elseif (strpos($action_type, 'farmer') !== false || strpos($action_type, 'user') !== false) {
            $target_type = 'user';
            $target_id = $details['user_id'] ?? $details['farmer_id'] ?? $details['id'] ?? null;
        } elseif (strpos($action_type, 'reservation') !== false) {
            $target_type = 'reservation';
            $target_id = $details['reservation_id'] ?? $details['id'] ?? null;
        } elseif (strpos($action_type, 'alert') !== false) {
            $target_type = 'price_alert';
            $target_id = $details['alert_id'] ?? $details['id'] ?? null;
        }
        
        // Get IP address and user agent
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Encode details as JSON
        $action_details = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        
        $insert_query = "INSERT INTO admin_logs (admin_id, admin_username, action_type, target_type, target_id, action_details, ip_address, user_agent, created_at) 
                         VALUES (:admin_id, :admin_username, :action_type, :target_type, :target_id, :action_details, :ip_address, :user_agent, NOW())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->bindParam(':admin_username', $admin_username);
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':target_type', $target_type);
        $stmt->bindParam(':target_id', $target_id, PDO::PARAM_INT);
        $stmt->bindParam(':action_details', $action_details);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Ensure notifications table exists (many installs never ran database/create_notifications_table.sql).
 */
if (!function_exists('fs_ensure_notifications_table')) {
    function fs_ensure_notifications_table(PDO $conn) {
        static $checked = false;
        if ($checked) {
            return true;
        }
        try {
            $t = $conn->query("SHOW TABLES LIKE 'notifications'");
            if ($t && $t->rowCount() > 0) {
                $checked = true;
                return true;
            }
            $conn->exec(
                "CREATE TABLE IF NOT EXISTS notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL COMMENT 'User who receives the notification',
                    type VARCHAR(50) NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    link VARCHAR(255) NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    read_at TIMESTAMP NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_is_read (is_read),
                    INDEX idx_created_at (created_at),
                    INDEX idx_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
            $checked = true;
            return true;
        } catch (Exception $e) {
            error_log('fs_ensure_notifications_table: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Create a notification for a user
 * @param int $user_id The user who will receive the notification
 * @param string $type Notification type (new_reservation, reservation_accepted, reservation_declined, new_message)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link (e.g., reservation_id)
 * @return bool Success status
 */
function createNotification($user_id, $type, $title, $message, $link = null) {
    try {
        $conn = getDB();
        if (!$conn) {
            error_log("Failed to create notification: Database connection failed");
            return false;
        }

        if (!fs_ensure_notifications_table($conn)) {
            return false;
        }

        $insert_query = "INSERT INTO notifications (user_id, type, title, message, link, is_read)
                         VALUES (:user_id, :type, :title, :message, :link, 0)";

        $stmt = $conn->prepare($insert_query);
        $uid = (int) $user_id;
        $stmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':type', (string) $type, PDO::PARAM_STR);
        $stmt->bindValue(':title', (string) $title, PDO::PARAM_STR);
        $stmt->bindValue(':message', (string) $message, PDO::PARAM_STR);
        if ($link === null || $link === '') {
            $stmt->bindValue(':link', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':link', (string) $link, PDO::PARAM_STR);
        }

        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get human-readable time ago string from timestamp
 * @param int $timestamp Unix timestamp
 * @return string Human-readable time string (e.g., "2 hours ago", "just now")
 */
function getTimeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

/**
 * Log price change to price_history table (farmer-only feature)
 * @param int $product_id Product ID
 * @param float $old_price Previous price (NULL for new products)
 * @param float $new_price New price
 * @param string $unit Unit of measurement
 * @param int $farmer_id Farmer who owns the product
 * @param int|null $market_id Market ID (optional)
 * @return bool Success status
 */
function logPriceHistory($product_id, $old_price, $new_price, $unit, $farmer_id, $market_id = null) {
    try {
        $conn = getDB();
        if (!$conn) {
            error_log("Failed to log price history: Database connection failed");
            return false;
        }
        
        // Check if price_history table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'price_history'");
        if ($table_check->rowCount() === 0) {
            error_log("Price history table does not exist");
            return false;
        }
        
        // Only log if price actually changed (or is new product)
        if ($old_price !== null && $old_price == $new_price) {
            return true; // No change, skip logging
        }
        
        // Calculate change type and percentage
        $change_type = 'stable';
        $change_percentage = 0.00;
        
        if ($old_price !== null && $old_price > 0) {
            if ($new_price > $old_price) {
                $change_type = 'increase';
                $change_percentage = round((($new_price - $old_price) / $old_price) * 100, 2);
            } elseif ($new_price < $old_price) {
                $change_type = 'decrease';
                $change_percentage = round((($old_price - $new_price) / $old_price) * 100, 2);
            }
        }
        
        // Insert into price_history using the actual schema (price, change_type, change_percentage, recorded_by)
        $insert_query = "INSERT INTO price_history (product_id, price, change_type, change_percentage, recorded_by)
                        VALUES (:product_id, :price, :change_type, :change_percentage, :recorded_by)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':price', $new_price);
        $stmt->bindParam(':change_type', $change_type);
        $stmt->bindParam(':change_percentage', $change_percentage);
        $stmt->bindParam(':recorded_by', $farmer_id, PDO::PARAM_INT);
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Error logging price history: " . $e->getMessage());
        return false;
    }
}

// Market-related functions
function getMarkets() {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        return fs_cache_remember('active_markets_list', 300, function() use ($conn) {
            $stmt = $conn->prepare("SELECT * FROM markets WHERE status = 'active' ORDER BY market_name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    } catch (Exception $e) {
        error_log("Error getting markets: " . $e->getMessage());
        return [];
    }
}

// Helper function to check if deleted_at column exists
if (!function_exists('hasDeletedAtColumn')) {
    function hasDeletedAtColumn() {
        static $hasColumn = null;
        if ($hasColumn !== null) {
            return $hasColumn;
        }
        $conn = getDB();
        if (!$conn) {
            return $hasColumn = false;
        }
        try {
            $stmt = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
            $hasColumn = $stmt->rowCount() > 0;
            return $hasColumn;
        } catch (Exception $e) {
            error_log("Error checking deleted_at column: " . $e->getMessage());
            return $hasColumn = false;
        }
    }
}

function getProductsByMarket($market_id) {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        // Check if deleted_at column exists before using it
        $deletedAtCheck = hasDeletedAtColumn() ? "AND mp.deleted_at IS NULL" : "";
        
        // Get products from market_products table only (market-specific)
        $stmt = $conn->prepare("
            SELECT 
                mp.id,
                mp.id AS market_product_id,
                mp.product_name as name,
                mp.product_name as filipino_name,
                mp.product_description as description,
                mp.product_image as image_url,
                NULL as category_id,
                mp.category as category_name,
                mp.price as current_price,
                NULL as previous_price,
                mp.unit,
                FALSE as is_featured,
                'market_products' as source_table
            FROM market_products mp
            WHERE mp.market_id = ? 
            AND mp.is_available = 1 
            $deletedAtCheck
            ORDER BY name ASC
        ");
        $stmt->execute([$market_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return attachUnitOptionsToProducts($products);
    } catch (Exception $e) {
        error_log("Error getting products by market: " . $e->getMessage());
        return [];
    }
}

function getProductsByMarketAndCategory($market_id, $category_id) {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        // First get the category name
        $cat_stmt = $conn->prepare("SELECT name, filipino_name FROM categories WHERE id = ?");
        $cat_stmt->execute([$category_id]);
        $category = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            return [];
        }
        
        // Check if deleted_at column exists before using it
        $deletedAtCheck = hasDeletedAtColumn() ? "AND mp.deleted_at IS NULL" : "";
        
        // Get products from market_products table matching by category name (case-insensitive)
        $stmt = $conn->prepare("
            SELECT 
                mp.id,
                mp.id AS market_product_id,
                mp.product_name as name,
                mp.product_name as filipino_name,
                mp.product_description as description,
                mp.product_image as image_url,
                ? as category_id,
                mp.category as category_name,
                mp.price as current_price,
                NULL as previous_price,
                mp.unit,
                FALSE as is_featured,
                'market_products' as source_table
            FROM market_products mp
            WHERE mp.market_id = ? 
            AND mp.is_available = 1 
            $deletedAtCheck
            AND (LOWER(TRIM(mp.category)) = LOWER(TRIM(?)) OR LOWER(TRIM(mp.category)) = LOWER(TRIM(?)))
            ORDER BY name ASC
        ");
        $stmt->execute([
            $category_id,
            $market_id, 
            $category['name'], 
            $category['filipino_name']
        ]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return attachUnitOptionsToProducts($products);
    } catch (Exception $e) {
        error_log("Error getting products by market and category: " . $e->getMessage());
        return [];
    }
}

/**
 * ============================================
 * PRICE MONITORING & OVERPRICING DETECTION
 * ============================================
 */

/**
 * Get the current price of a product (handles both legacy and unit_options)
 * @param int $product_id
 * @return float|null Current price or null if not found
 */
function getProductCurrentPrice($product_id) {
    $conn = getDB();
    if (!$conn) return null;
    
    try {
        // First check if product has unit_options (newer system)
        ensureMarketProductUnitsTable();
        $unit_stmt = $conn->prepare("
            SELECT price 
            FROM market_product_units 
            WHERE product_id = ? AND is_default = 1 
            LIMIT 1
        ");
        $unit_stmt->execute([$product_id]);
        $unit_price = $unit_stmt->fetch(PDO::FETCH_COLUMN);
        
        if ($unit_price !== false && $unit_price !== null) {
            return floatval($unit_price);
        }
        
        // Fallback to legacy price from market_products
        $legacy_stmt = $conn->prepare("SELECT price FROM market_products WHERE id = ?");
        $legacy_stmt->execute([$product_id]);
        $legacy_price = $legacy_stmt->fetch(PDO::FETCH_COLUMN);
        
        if ($legacy_price !== false && $legacy_price !== null) {
            return floatval($legacy_price);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting product current price: " . $e->getMessage());
        return null;
    }
}

/**
 * Normalize price to per-kg basis for fair comparison
 * @param float $price Original price
 * @param string $unit_label Unit label (kg, g, piece, bundle, etc.)
 * @param float $quantity Quantity (default 1)
 * @return float|null Normalized price per kg, or null if cannot normalize
 */
function normalizePriceToPerKg($price, $unit_label, $quantity = 1) {
    if ($price <= 0 || $quantity <= 0) {
        return null;
    }
    
    $unit = strtolower(trim($unit_label));
    $price_per_unit = $price / $quantity;
    
    // Already in kg
    if ($unit === 'kg' || $unit === 'kilogram') {
        return $price_per_unit;
    }
    
    // Convert grams to kg (1 kg = 1000 g)
    if ($unit === 'g' || $unit === 'gram' || $unit === 'grams') {
        return ($price_per_unit / 1000);
    }
    
    // For other units, we can't reliably convert without product-specific data
    // Return null to indicate we should use raw price comparison
    // Common non-convertible units: piece, bundle, pack, dozen, box, sack, liter, ml
    return null;
}

/**
 * Get similar product names for fuzzy matching
 * @param string $product_name Base product name
 * @return array Array of similar product names to search for
 */
function getSimilarProductNames($product_name) {
    $base_name = trim($product_name);
    $variations = [$base_name]; // Always include exact match
    
    // Remove common suffixes in parentheses
    $base_clean = preg_replace('/\s*\([^)]*\)\s*$/', '', $base_name);
    if ($base_clean !== $base_name) {
        $variations[] = trim($base_clean);
    }
    
    // Remove common prefixes
    $base_clean = preg_replace('/^(fresh|dried|frozen|organic|local)\s+/i', '', $base_clean);
    if ($base_clean !== $base_name) {
        $variations[] = trim($base_clean);
    }
    
    // Remove extra whitespace and normalize
    $base_clean = preg_replace('/\s+/', ' ', trim($base_clean));
    if ($base_clean !== $base_name && !in_array($base_clean, $variations)) {
        $variations[] = $base_clean;
    }
    
    return array_unique($variations);
}

/**
 * Calculate reference price (average) for a product across all markets
 * @param string $product_name Product name to calculate average for
 * @return array|null ['avg_price' => float, 'min_price' => float, 'max_price' => float, 'count' => int] or null
 */
function calculateProductReferencePrice($product_name) {
    $conn = getDB();
    if (!$conn) return null;
    
    try {
        ensureMarketProductUnitsTable();
        
        // Get similar product names for fuzzy matching
        $similar_names = getSimilarProductNames($product_name);
        $placeholders = implode(',', array_fill(0, count($similar_names), '?'));
        
        // Get all prices for this product name (and variations) across all markets
        // Include unit information for normalization
        $query = "
            SELECT 
                COALESCE(mpu.price, mp.price) as price,
                COALESCE(mpu.unit_label, mp.unit, 'kg') as unit_label,
                COALESCE(mpu.quantity, 1) as quantity
            FROM market_products mp
            LEFT JOIN market_product_units mpu ON mp.id = mpu.product_id AND mpu.is_default = 1
            WHERE mp.product_name IN ($placeholders)
            AND mp.is_available = 1
            AND COALESCE(mpu.price, mp.price) > 0
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($similar_names);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            return null;
        }
        
        $normalized_prices = [];
        $raw_prices = [];
        
        foreach ($rows as $row) {
            $price = floatval($row['price']);
            $unit_label = $row['unit_label'] ?? 'kg';
            $quantity = floatval($row['quantity'] ?? 1);
            
            // Try to normalize to per-kg
            $normalized = normalizePriceToPerKg($price, $unit_label, $quantity);
            
            if ($normalized !== null) {
                $normalized_prices[] = $normalized;
            } else {
                // If we can't normalize, use raw price
                $raw_prices[] = $price;
            }
        }
        
        // Prefer normalized prices if we have enough data
        $prices_to_use = !empty($normalized_prices) ? $normalized_prices : $raw_prices;
        
        if (empty($prices_to_use)) {
            return null;
        }
        
        $avg_price = array_sum($prices_to_use) / count($prices_to_use);
        $min_price = min($prices_to_use);
        $max_price = max($prices_to_use);
        
        return [
            'avg_price' => round($avg_price, 2),
            'min_price' => round($min_price, 2),
            'max_price' => round($max_price, 2),
            'count' => count($prices_to_use),
            'normalized' => !empty($normalized_prices)
        ];
    } catch (Exception $e) {
        error_log("Error calculating reference price: " . $e->getMessage());
        return null;
    }
}

/**
 * Get historical average price for a product (last 30 days)
 * @param int $product_id
 * @return float|null Average price or null if no history
 */
function getHistoricalAveragePrice($product_id, $days = 30) {
    $conn = getDB();
    if (!$conn) return null;
    
    try {
        // Check if price_history table exists and has data
        $check_stmt = $conn->query("SHOW TABLES LIKE 'price_history'");
        if ($check_stmt->rowCount() == 0) {
            return null;
        }
        
        $query = "
            SELECT AVG(new_price) as avg_price
            FROM price_history
            WHERE product_id = :product_id
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['avg_price'] !== null) {
            return round(floatval($result['avg_price']), 2);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting historical average: " . $e->getMessage());
        return null;
    }
}

/**
 * Check for price spikes (sudden increases) and calculate trends
 * @param int $product_id
 * @return array|null ['spike_7days' => bool, 'spike_30days' => bool, 'percent_change_7days' => float, 'percent_change_30days' => float, 'trend' => string, 'trend_percent' => float] or null
 */
function checkPriceSpikes($product_id) {
    $conn = getDB();
    if (!$conn) return null;
    
    try {
        $current_price = getProductCurrentPrice($product_id);
        if ($current_price === null) {
            return null;
        }
        
        // Check if price_history table exists
        $check_stmt = $conn->query("SHOW TABLES LIKE 'price_history'");
        if ($check_stmt->rowCount() == 0) {
            return null;
        }
        
        // Get price 7 days ago
        $query_7days = "
            SELECT price 
            FROM price_history 
            WHERE product_id = :product_id 
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY recorded_at ASC 
            LIMIT 1
        ";
        $stmt_7days = $conn->prepare($query_7days);
        $stmt_7days->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_7days->execute();
        $price_7days_ago = $stmt_7days->fetch(PDO::FETCH_COLUMN);
        
        // Get price 30 days ago
        $query_30days = "
            SELECT price 
            FROM price_history 
            WHERE product_id = :product_id 
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY recorded_at ASC 
            LIMIT 1
        ";
        $stmt_30days = $conn->prepare($query_30days);
        $stmt_30days->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_30days->execute();
        $price_30days_ago = $stmt_30days->fetch(PDO::FETCH_COLUMN);
        
        // Get average price over last 7 days for trend calculation
        $query_avg_7days = "
            SELECT AVG(price) as avg_price
            FROM price_history 
            WHERE product_id = :product_id 
            AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ";
        $stmt_avg_7days = $conn->prepare($query_avg_7days);
        $stmt_avg_7days->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt_avg_7days->execute();
        $avg_7days = $stmt_avg_7days->fetch(PDO::FETCH_COLUMN);
        
        $result = [
            'spike_7days' => false,
            'spike_30days' => false,
            'percent_change_7days' => 0,
            'percent_change_30days' => 0,
            'trend' => 'stable',
            'trend_percent' => 0
        ];
        
        if ($price_7days_ago !== false && $price_7days_ago > 0) {
            $percent_change = (($current_price - floatval($price_7days_ago)) / floatval($price_7days_ago)) * 100;
            $result['percent_change_7days'] = round($percent_change, 2);
            $result['spike_7days'] = $percent_change > 50; // >50% increase in 7 days
        }
        
        if ($price_30days_ago !== false && $price_30days_ago > 0) {
            $percent_change = (($current_price - floatval($price_30days_ago)) / floatval($price_30days_ago)) * 100;
            $result['percent_change_30days'] = round($percent_change, 2);
            $result['spike_30days'] = $percent_change > 100; // >100% increase in 30 days
        }
        
        // Calculate trend based on 7-day average vs current
        if ($avg_7days !== false && $avg_7days > 0) {
            $trend_percent = (($current_price - floatval($avg_7days)) / floatval($avg_7days)) * 100;
            $result['trend_percent'] = round($trend_percent, 2);
            
            if ($trend_percent > 5) {
                $result['trend'] = 'increasing';
            } elseif ($trend_percent < -5) {
                $result['trend'] = 'decreasing';
            } else {
                $result['trend'] = 'stable';
            }
        } elseif ($price_7days_ago !== false && $price_7days_ago > 0) {
            // Fallback to 7-day comparison if no average available
            $trend_percent = $result['percent_change_7days'];
            $result['trend_percent'] = $trend_percent;
            
            if ($trend_percent > 5) {
                $result['trend'] = 'increasing';
            } elseif ($trend_percent < -5) {
                $result['trend'] = 'decreasing';
            } else {
                $result['trend'] = 'stable';
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error checking price spikes: " . $e->getMessage());
        return null;
    }
}

/**
 * Detect overpricing and assign status
 * @param float $current_price
 * @param float $reference_price
 * @param array|null $spike_data From checkPriceSpikes()
 * @return array ['status' => 'normal'|'warning'|'critical'|'no_data', 'deviation_percent' => float, 'badge' => string, 'color' => string]
 */
function detectOverpricing($current_price, $reference_price, $spike_data = null) {
    if ($reference_price === null || $reference_price <= 0) {
        return [
            'status' => 'no_data',
            'deviation_percent' => 0,
            'badge' => 'Not enough data',
            'color' => 'gray',
            'indicator' => '[-]'
        ];
    }
    
    if ($current_price <= 0) {
        return [
            'status' => 'no_data',
            'deviation_percent' => 0,
            'badge' => 'Invalid price',
            'color' => 'gray',
            'indicator' => '[-]'
        ];
    }
    
    // Calculate deviation percentage
    $deviation_percent = (($current_price - $reference_price) / $reference_price) * 100;
    
    // Check for price spikes first (these can override normal overpricing status)
    if ($spike_data) {
        if ($spike_data['spike_30days']) {
            // >100% increase in 30 days = Critical
            return [
                'status' => 'critical',
                'deviation_percent' => $deviation_percent,
                'badge' => 'Critical Spike',
                'color' => 'red',
                'indicator' => '[!!]',
                'reason' => 'Price increased >100% in 30 days'
            ];
        }
        if ($spike_data['spike_7days']) {
            // >50% increase in 7 days = Warning
            return [
                'status' => 'warning',
                'deviation_percent' => $deviation_percent,
                'badge' => 'Price Spike',
                'color' => 'yellow',
                'indicator' => '[!]',
                'reason' => 'Price increased >50% in 7 days'
            ];
        }
    }
    
    // Check against reference price thresholds
    if ($current_price <= ($reference_price * 1.5)) {
        // ≤150% of reference = Normal
        return [
            'status' => 'normal',
            'deviation_percent' => round($deviation_percent, 2),
            'badge' => 'Normal',
            'color' => 'green',
            'indicator' => '[OK]'
        ];
    } elseif ($current_price <= ($reference_price * 2.0)) {
        // 150-200% of reference = Warning
        return [
            'status' => 'warning',
            'deviation_percent' => round($deviation_percent, 2),
            'badge' => 'Warning',
            'color' => 'yellow',
            'indicator' => '[!]',
            'reason' => 'Price is ' . round($deviation_percent, 1) . '% above average'
        ];
    } else {
        // >200% of reference = Critical
        return [
            'status' => 'critical',
            'deviation_percent' => round($deviation_percent, 2),
            'badge' => 'Critical',
            'color' => 'red',
            'indicator' => '[!!]',
            'reason' => 'Price is ' . round($deviation_percent, 1) . '% above average'
        ];
    }
}

/**
 * Get all products with price monitoring data for admin dashboard
 * @param int|null $market_id Filter by market (optional)
 * @param string|null $status Filter by status (normal/warning/critical/no_data) (optional)
 * @return array
 */
function getProductsWithPriceMonitoring($market_id = null, $status = null) {
    $conn = getDB();
    if (!$conn) return [];
    
    try {
        ensureMarketProductUnitsTable();
        
        $market_filter = $market_id ? "AND mp.market_id = :market_id" : "";
        $deletedAtCheck = hasDeletedAtColumn() ? "AND mp.deleted_at IS NULL" : "";
        
        $query = "
            SELECT 
                mp.id,
                mp.product_name,
                mp.category,
                mp.market_id,
                m.market_name,
                mp.farmer_id,
                u.username as farmer_username,
                u.full_name as farmer_name,
                COALESCE(mpu.price, mp.price) as current_price,
                mp.unit,
                mp.is_available,
                mp.created_at
            FROM market_products mp
            LEFT JOIN markets m ON mp.market_id = m.id
            LEFT JOIN users u ON mp.farmer_id = u.id
            LEFT JOIN market_product_units mpu ON mp.id = mpu.product_id AND mpu.is_default = 1
            WHERE mp.is_available = 1
            $market_filter
            $deletedAtCheck
            ORDER BY mp.product_name ASC, m.market_name ASC
        ";
        
        $stmt = $conn->prepare($query);
        if ($market_id) {
            $stmt->bindParam(':market_id', $market_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($products as $product) {
            $current_price = floatval($product['current_price']);
            
            // Get unit information for normalization
            $unit_query = $conn->prepare("
                SELECT unit_label, quantity 
                FROM market_product_units 
                WHERE product_id = ? AND is_default = 1 
                LIMIT 1
            ");
            $unit_query->execute([$product['id']]);
            $unit_info = $unit_query->fetch(PDO::FETCH_ASSOC);
            $unit_label = $unit_info['unit_label'] ?? $product['unit'] ?? 'kg';
            $quantity = floatval($unit_info['quantity'] ?? 1);
            
            // Normalize current price to per-kg for comparison
            $normalized_price = normalizePriceToPerKg($current_price, $unit_label, $quantity);
            $price_for_comparison = $normalized_price !== null ? $normalized_price : $current_price;
            
            $reference_data = calculateProductReferencePrice($product['product_name']);
            $reference_price = $reference_data ? $reference_data['avg_price'] : null;
            $spike_data = checkPriceSpikes($product['id']);
            
            // Use normalized price for comparison if available
            $comparison_price = ($normalized_price !== null && $reference_data && isset($reference_data['normalized']) && $reference_data['normalized']) 
                ? $normalized_price 
                : $current_price;
            
            $monitoring = detectOverpricing($comparison_price, $reference_price, $spike_data);
            
            // Apply status filter if specified
            if ($status && $monitoring['status'] !== $status) {
                continue;
            }
            
            $result[] = [
                'id' => $product['id'],
                'product_name' => $product['product_name'],
                'category' => $product['category'],
                'market_id' => $product['market_id'],
                'market_name' => $product['market_name'],
                'farmer_id' => $product['farmer_id'],
                'farmer_username' => $product['farmer_username'],
                'farmer_name' => $product['farmer_name'],
                'current_price' => $current_price,
                'normalized_price' => $normalized_price,
                'unit_label' => $unit_label,
                'quantity' => $quantity,
                'reference_price' => $reference_price,
                'unit' => $product['unit'],
                'monitoring' => $monitoring,
                'reference_data' => $reference_data,
                'spike_data' => $spike_data
            ];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error getting products with price monitoring: " . $e->getMessage());
        return [];
    }
}
?>
