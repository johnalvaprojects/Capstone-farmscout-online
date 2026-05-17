<?php
require_once 'includes/enhanced_functions.php';

// Check if user is logged in and is a farmer
if (!isLoggedIn() || $_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php?error=' . urlencode('Access denied. Farmer access required.'));
    exit;
}

$page_title = 'Manage Products - FarmScout Online';
$page_description = 'Add, edit, and manage your products';

$success_message = '';
$error_message = '';

// Handle messages
if (isset($_GET['message'])) {
    $success_message = sanitizeInput($_GET['message']);
}
if (isset($_GET['error'])) {
    $error_message = sanitizeInput($_GET['error']);
}

$farmer_id = $_SESSION['user_id'];
                    $conn = getDB();

// This page is being retired as a standalone UI.
// It now acts as a controller endpoint for product actions and redirects back to the Farmer Dashboard.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirect_url = 'farmer-dashboard.php?section=products';
    if (isset($_GET['product_id'])) {
        $redirect_url .= '&product_id=' . intval($_GET['product_id']);
    }
    if ($success_message) {
        $redirect_url .= '&message=' . urlencode($success_message);
    }
    if ($error_message) {
        $redirect_url .= '&error=' . urlencode($error_message);
    }
    header('Location: ' . $redirect_url);
    exit;
}
                    
// Get farmer's markets
$markets_query = "SELECT m.*, mf.approval_status, mf.stall_number 
                  FROM markets m 
                  JOIN market_farmers mf ON m.id = mf.market_id 
                  WHERE mf.farmer_id = :farmer_id";
$markets_stmt = $conn->prepare($markets_query);
$markets_stmt->bindParam(':farmer_id', $farmer_id);
$markets_stmt->execute();
$farmer_markets = $markets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get markets owned by this farmer
$owned_markets_query = "SELECT * FROM markets WHERE vendor_id = :farmer_id";
$owned_markets_stmt = $conn->prepare($owned_markets_query);
$owned_markets_stmt->bindParam(':farmer_id', $farmer_id);
$owned_markets_stmt->execute();
$owned_markets = $owned_markets_stmt->fetchAll(PDO::FETCH_ASSOC);
$owned_market_ids = array_column($owned_markets, 'id');

$managed_market_ids = $_SESSION['managed_market_ids'] ?? [];
$active_market_id = $_SESSION['active_market_id'] ?? null;

$all_market_ids = array_unique(array_merge($owned_market_ids, $managed_market_ids));
if ($active_market_id && !in_array($active_market_id, $all_market_ids)) {
    $all_market_ids[] = $active_market_id;
}

// Check if farmer is verified (if verification_status column exists)
$is_farmer_verified = true; // Default to true for backward compatibility
if ($farmer_id) {
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'verification_status'");
        $hasVerificationStatus = $col_check->rowCount() > 0;
        if ($hasVerificationStatus) {
            $verify_stmt = $conn->prepare("SELECT verification_status FROM users WHERE id = ? AND user_role = 'farmer'");
            $verify_stmt->execute([$farmer_id]);
            $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
            $is_farmer_verified = ($verify_result && $verify_result['verification_status'] === 'verified');
        }
    } catch (Exception $e) {
        // Column doesn't exist, that's okay
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        // Check if farmer is verified
        if (!$is_farmer_verified) {
            $error_message = 'Your farmer account must be verified before adding products. Please wait for admin verification.';
        } else {
            $market_id = intval($_POST['market_id'] ?? 0);
            $product_name = fsCanonicalizeProductName(sanitizeInput($_POST['product_name'] ?? ''));
            $filipino_name = sanitizeInput($_POST['filipino_name'] ?? '');
            $category = sanitizeInput($_POST['category'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Validate required fields
        if (empty($product_name) || empty($market_id)) {
            $error_message = 'Product name and market are required.';
        } elseif (!fsIsStandardProductName($product_name)) {
            $error_message = 'Please select an existing product.';
        } else {
            // Handle image upload
            $image_url = '';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'assets/images/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('product_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                    $image_url = $file_path;
                }
            }
            
            // Extract unit options
                $unit_options = extractUnitOptionsFromRequest($_POST);
            
            // Check if approval_status column exists
            $hasApprovalStatus = false;
            try {
                $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
                $hasApprovalStatus = $col_check->rowCount() > 0;
                } catch (Exception $e) {
                $hasApprovalStatus = false;
            }
            
            // Insert product
            if ($hasApprovalStatus) {
                $insert_query = "INSERT INTO market_products (market_id, farmer_id, product_name, category, product_description, product_image, is_available, approval_status, created_at) 
                                VALUES (:market_id, :farmer_id, :product_name, :category, :product_description, :product_image, :is_available, 'pending', NOW())";
            } else {
                $insert_query = "INSERT INTO market_products (market_id, farmer_id, product_name, category, product_description, product_image, is_available, created_at) 
                                VALUES (:market_id, :farmer_id, :product_name, :category, :product_description, :product_image, :is_available, NOW())";
            }
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bindParam(':market_id', $market_id);
            $insert_stmt->bindParam(':farmer_id', $farmer_id);
            $insert_stmt->bindParam(':product_name', $product_name);
            $insert_stmt->bindParam(':category', $category);
            $insert_stmt->bindParam(':product_description', $description);
            $insert_stmt->bindParam(':product_image', $image_url);
            $insert_stmt->bindParam(':is_available', $is_available);
            
            if ($insert_stmt->execute()) {
                $product_id = $conn->lastInsertId();
                saveProductUnitOptions($product_id, $unit_options);
                // Check if approval_status column exists for success message
                $hasApprovalStatus = false;
                try {
                    $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
                    $hasApprovalStatus = $col_check->rowCount() > 0;
                } catch (Exception $e) {
                    $hasApprovalStatus = false;
                }
                if ($hasApprovalStatus) {
                    $success_message = 'Product added successfully! It is pending admin approval and will be visible once approved.';
                } else {
                    $success_message = 'Product added successfully!';
                }
            } else {
                $error_message = 'Failed to add product.';
            }
        }
        }
    } elseif ($action === 'update_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $market_id = intval($_POST['market_id'] ?? 0);
        $product_name = fsCanonicalizeProductName(sanitizeInput($_POST['product_name'] ?? ''));
        $filipino_name = sanitizeInput($_POST['filipino_name'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        if (empty($product_name) || empty($market_id) || empty($product_id)) {
            $error_message = 'Product name, market, and product ID are required.';
        } elseif (!fsIsStandardProductName($product_name)) {
            $error_message = 'Please select an existing product.';
        } else {
            // Handle image upload
            $image_url = '';
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'assets/images/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('product_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['product_image']['tmp_name'], $file_path)) {
                    $image_url = $file_path;
                }
            }
            
            // Get old unit options before update for price alert comparison
            $old_unit_options = [];
            try {
                $old_query = "SELECT unit_label, quantity, price FROM market_product_units WHERE product_id = :product_id";
                $old_stmt = $conn->prepare($old_query);
                $old_stmt->bindParam(':product_id', $product_id);
                $old_stmt->execute();
                $old_unit_options = $old_stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching old unit options: " . $e->getMessage());
            }
            
            // Extract unit options
            $unit_options = extractUnitOptionsFromRequest($_POST);
            
            // Update product
            $update_query = "UPDATE market_products SET 
                            market_id = :market_id,
                            product_name = :product_name,
                            category = :category,
                            product_description = :product_description,
                            is_available = :is_available";
            
            if (!empty($image_url)) {
                $update_query .= ", product_image = :product_image";
            }
            
            $update_query .= " WHERE id = :product_id AND farmer_id = :farmer_id";
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':market_id', $market_id);
            $update_stmt->bindParam(':product_name', $product_name);
            $update_stmt->bindParam(':category', $category);
            $update_stmt->bindParam(':product_description', $description);
            $update_stmt->bindParam(':is_available', $is_available);
            $update_stmt->bindParam(':product_id', $product_id);
            $update_stmt->bindParam(':farmer_id', $farmer_id);
            
            if (!empty($image_url)) {
                $update_stmt->bindParam(':product_image', $image_url);
            }
            
            if ($update_stmt->execute()) {
                saveProductUnitOptions($product_id, $unit_options);
                
                // Trigger price alerts for changed prices
                // Create maps of old and new prices by unit
                $old_prices = [];
                foreach ($old_unit_options as $old_option) {
                    $unit_key = ($old_option['unit_label'] ?? '') . '_' . ($old_option['quantity'] ?? 1);
                    $old_prices[$unit_key] = floatval($old_option['price'] ?? 0);
                }
                
                // Check each new unit option for price changes
                foreach ($unit_options as $new_option) {
                    $unit_label = $new_option['unit_label'] ?? '';
                    $quantity = floatval($new_option['quantity'] ?? 1);
                    $new_price = floatval($new_option['price'] ?? 0);
                    $unit_key = $unit_label . '_' . $quantity;
                    
                    $old_price = $old_prices[$unit_key] ?? null;
                    
                    // If price changed, trigger alerts and log price history
                    if ($old_price !== null && $old_price != $new_price) {
                        $unit_display = formatUnitDisplayText($unit_label, $quantity);
                        checkAndTriggerPriceAlertsForUnit($product_id, $old_price, $new_price, $unit_display);
                        // Log price history (farmer-only feature)
                        if (function_exists('logPriceHistory')) {
                            logPriceHistory($product_id, $old_price, $new_price, $unit_display, $farmer_id, $market_id);
                        }
                    } elseif ($old_price === null && $new_price > 0) {
                        // New unit option added with price
                        $unit_display = formatUnitDisplayText($unit_label, $quantity);
                        checkAndTriggerPriceAlertsForUnit($product_id, 0, $new_price, $unit_display);
                        // Log price history for new price (old_price is NULL)
                        if (function_exists('logPriceHistory')) {
                            logPriceHistory($product_id, null, $new_price, $unit_display, $farmer_id, $market_id);
                        }
                    }
                }
                
                $success_message = 'Product updated successfully!';
                        } else {
                $error_message = 'Failed to update product.';
            }
        }
    } elseif ($action === 'delete_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            // Check if deleted_at column exists
            $hasDeletedAt = false;
            try {
                $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
                $hasDeletedAt = $col_check->rowCount() > 0;
                } catch (Exception $e) {
                // Column doesn't exist, that's okay
            }
            
            if ($hasDeletedAt) {
                $delete_query = "UPDATE market_products SET deleted_at = NOW() WHERE id = :product_id AND farmer_id = :farmer_id";
            } else {
                // Fallback: set is_available to 0 if deleted_at doesn't exist
                $delete_query = "UPDATE market_products SET is_available = 0 WHERE id = :product_id AND farmer_id = :farmer_id";
            }
            
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bindParam(':product_id', $product_id);
            $delete_stmt->bindParam(':farmer_id', $farmer_id);
            
            if ($delete_stmt->execute()) {
                $success_message = 'Product deleted successfully!';
            } else {
                $error_message = 'Failed to delete product.';
            }
        }
    } elseif ($action === 'mark_out_of_stock') {
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            $update_query = "UPDATE market_products SET is_available = 0 WHERE id = :product_id AND farmer_id = :farmer_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':product_id', $product_id);
            $update_stmt->bindParam(':farmer_id', $farmer_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Product marked as not available.';
            } else {
                $error_message = 'Failed to update product.';
            }
        }
    } elseif ($action === 'restock_product') {
        $product_id = intval($_POST['product_id'] ?? 0);
        
        if ($product_id > 0) {
            $update_query = "UPDATE market_products SET is_available = 1 WHERE id = :product_id AND farmer_id = :farmer_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':product_id', $product_id);
            $update_stmt->bindParam(':farmer_id', $farmer_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Product restocked successfully!';
                } else {
                $error_message = 'Failed to update product.';
            }
        }
    }
    // Removed add_category action - categories should be managed by admins only
    
    // Redirect to prevent form resubmission
    if ($success_message || $error_message) {
        $redirect_url = 'farmer-dashboard.php?section=products';
        // Preserve edit context if present
        $pid = intval($_POST['product_id'] ?? ($_GET['product_id'] ?? 0));
        if ($pid > 0) {
            $redirect_url .= '&product_id=' . $pid;
        }
        if ($success_message) {
            $redirect_url .= '&message=' . urlencode($success_message);
        }
        if ($error_message) {
            $redirect_url .= '&error=' . urlencode($error_message);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Fallback: if we ever reach here, redirect safely.
header('Location: farmer-dashboard.php?section=products');
exit;

// Get product to edit
$edit_product = null;
$edit_product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
if ($edit_product_id > 0) {
    $product_query = "SELECT * FROM market_products WHERE id = :product_id AND farmer_id = :farmer_id";
    $product_stmt = $conn->prepare($product_query);
    $product_stmt->bindParam(':product_id', $edit_product_id);
    $product_stmt->bindParam(':farmer_id', $farmer_id);
    $product_stmt->execute();
    $edit_product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_product) {
        $edit_product = attachUnitOptionsToProducts([$edit_product], 'id')[0];
    }
}

// Get all products
$all_markets = array_merge($farmer_markets, $owned_markets);
$all_market_ids_for_query = array_column($all_markets, 'id');
if (empty($all_market_ids_for_query)) {
    $all_market_ids_for_query = [0]; // Prevent SQL error
}
        
        // Check if deleted_at column exists
        $hasDeletedAt = false;
        try {
            $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'deleted_at'");
            $hasDeletedAt = $col_check->rowCount() > 0;
        } catch (Exception $e) {
            // Column doesn't exist, that's okay
        }
$deletedAtCheck = $hasDeletedAt ? "AND (mp.deleted_at IS NULL OR mp.deleted_at = '0000-00-00 00:00:00')" : "";

$hasMarketProductApproval = false;
try {
    $col_check = $conn->query("SHOW COLUMNS FROM market_products LIKE 'approval_status'");
    $hasMarketProductApproval = $col_check && $col_check->rowCount() > 0;
} catch (Exception $e) {
    $hasMarketProductApproval = false;
}

$placeholders = str_repeat('?,', count($all_market_ids_for_query) - 1) . '?';
$products_query = "SELECT mp.*, m.market_name 
                  FROM market_products mp
                   JOIN markets m ON mp.market_id = m.id 
                   WHERE mp.market_id IN ($placeholders) AND (mp.farmer_id = ? OR mp.market_id IN ($placeholders))
                  $deletedAtCheck
                   ORDER BY mp.market_id, mp.product_name";
$products_stmt = $conn->prepare($products_query);
$products_stmt->execute(array_merge($all_market_ids_for_query, [$farmer_id], $all_market_ids_for_query));
$all_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
$all_products = attachUnitOptionsToProducts($all_products, 'id');

// Get categories
$categories = getCategories();

// Product-name suggestions to reduce mismatches with admin reference bands.
$product_name_suggestions = function_exists('fsGetStandardProductNames') ? fsGetStandardProductNames() : [];

// Set current page for navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php include 'includes/favicon.php'; ?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    :root {
        --bg-color: #ffffff;
        --text-color: #000000;
        --border-color: #000000;
        --box-bg: #ffffff;
    }
    
    body.manage-products-page {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        background-color: var(--bg-color) !important;
        color: var(--text-color) !important;
        padding: 0 !important;
        line-height: 1.4 !important;
        margin: 0 !important;
        min-height: 100vh;
        width: 100%;
        box-sizing: border-box;
    }
    
    /* Fixed Header - No Background (Consistent across Farmer Dashboard and Manage Products) */
    .header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        background: none !important;
        background-color: transparent !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        z-index: 1000 !important;
        padding: 1rem 2rem !important;
        margin-bottom: 0 !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
    }
    
    /* Ensure nav elements also have no background */
    .header .nav,
    .header .logo,
    .header .nav a {
        background: transparent !important;
        background-color: transparent !important;
    }
    
    body.manage-products-page {
        padding-top: 70px !important;
    }
    
    @media (max-width: 768px) {
        body.manage-products-page {
            padding-top: 60px !important;
        }
    }
    
    /* Notification Styles */
    .slide-notification {
        position: fixed;
        top: 20px;
        left: 20px;
        background: #000000;
        color: white;
        padding: 16px 20px;
        border: 3px solid #000000;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        max-width: 400px;
        animation: slideInFromLeft 0.3s ease-out forwards;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
    }
    
    .slide-notification .notification-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .slide-notification .notification-icon {
        width: 32px;
        height: 32px;
        object-fit: cover;
        flex-shrink: 0;
    }
    
    .slide-notification .notification-text {
        flex: 1;
        color: #ffffff;
        font-weight: 500;
        font-size: 1rem;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
    }
    
    .slide-notification .close-btn {
        margin-left: auto;
        background: none;
        border: 2px solid #ffffff;
        color: #ffffff;
        cursor: pointer;
        padding: 4px 8px;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        transition: all 0.3s ease;
    }
    
    .slide-notification .close-btn:hover {
        background: #ffffff;
        color: #000000;
    }
    
    @keyframes slideInFromLeft {
        0% {
            left: -400px;
            opacity: 0;
        }
        100% {
            left: 20px;
            opacity: 1;
        }
    }
    
    .slide-notification.auto-hide {
        animation: slideInFromLeft 0.3s ease-out forwards, slideOutToLeft 0.3s ease-in 4.7s forwards;
    }
    
    @keyframes slideOutToLeft {
        0% {
            left: 20px;
            opacity: 1;
        }
        100% {
            left: -400px;
        opacity: 0;
        }
    }
    
    /* Main Container - Fullscreen */
    .manage-products-container {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 2rem 1.5rem;
    }
    
    /* Hero Section */
    .manage-products-hero {
        text-align: center;
        margin-bottom: 1.5rem;
    }
    
    .manage-products-hero h1 {
        font-size: clamp(2rem, 5vw, 4rem) !important;
        font-weight: bold !important;
        letter-spacing: 0.1em !important;
        margin-bottom: 0.5rem !important;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        color: #000000 !important;
    }
    
    .manage-products-hero p {
        font-size: clamp(0.9rem, 1.5vw, 1.2rem) !important;
        letter-spacing: 0.05em !important;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        color: #000000 !important;
        opacity: 0.8;
    }
    
    /* Cards - Modernized Style */
    .manage-card {
        border: 3px solid #000000;
        border-radius: 8px;
        padding: 1.25rem;
        background-color: #ffffff;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
        box-shadow: 4px 4px 0 #000000;
    }
    
    .manage-card:hover {
        transform: translateY(-2px);
    }
    
    .manage-card h2 {
        font-size: clamp(1.2rem, 2.5vw, 1.5rem) !important;
        font-weight: bold !important;
        margin-bottom: 0.75rem !important;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        color: #000000 !important;
        letter-spacing: 0.05em;
    }
    
    /* Forms - Market Finder Style */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 1rem;
        margin-bottom: 0.5rem;
        letter-spacing: 0.05em;
        color: #000000;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 1rem;
        letter-spacing: 0.05em;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #000000;
    }
    
    .form-group input[type="checkbox"] {
        width: auto;
        margin-right: 0.5rem;
    }
    
    /* Custom File Input */
    .file-input-wrapper {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .file-input-label {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 0.9rem;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .file-input-label:hover {
        background-color: #000000;
        color: #ffffff;
    }
    
    .file-input-text {
        display: inline-block;
    }
    
    .file-input-icon {
        font-size: 1.2rem;
    }
    
    .file-name-display {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 0.85rem;
        color: #000000;
        padding: 0.4rem 0.75rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        flex: 1;
        min-width: 200px;
    }
    
    .file-name-display:empty {
        display: none;
    }
    
    /* Buttons - Market Finder Style */
    .manage-btn {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
        font-size: 1rem;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 0.75rem 1.5rem;
        border: 3px solid var(--border-color);
        background-color: var(--text-color);
        color: var(--bg-color);
        cursor: pointer;
        transition: all 0.3s ease 0.1s;
        text-decoration: none;
        display: inline-block;
    }
    
    .manage-btn:hover {
        background-color: var(--bg-color);
        color: var(--text-color);
        transition: all 0.3s ease 0s;
    }
    
    .manage-btn-secondary {
        background-color: var(--bg-color);
        color: var(--text-color);
    }
    
    .manage-btn-secondary:hover {
        background-color: var(--text-color);
        color: var(--bg-color);
    }
    
    .manage-btn-danger {
        background-color: var(--bg-color);
        color: #dc2626;
        border-color: #dc2626;
    }
    
    .manage-btn-danger:hover {
        background-color: #dc2626;
        color: var(--bg-color);
    }
    
    /* Product Grid */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    .product-card {
        border: 3px solid #000000;
        border-radius: 8px;
        padding: 1rem;
        background-color: #ffffff;
        transition: all 0.3s ease;
        box-shadow: 4px 4px 0 #000000;
    }
    
    .product-card:hover {
        transform: translateY(-2px);
    }
    
    .product-image {
        width: 100%;
        aspect-ratio: 4/3;
        object-fit: cover;
        border: 2px solid #000000;
        margin-bottom: 1rem;
    }
    
    .product-name {
        font-size: 1.2rem;
        font-weight: bold;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #000000;
        margin-bottom: 0.5rem;
    }
    
    .product-price {
        font-size: 1rem;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #000000;
        margin-bottom: 0.5rem;
    }
    
    .product-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid #000000;
        flex-wrap: wrap;
    }
    
    .product-actions .manage-btn {
        flex: 1;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        min-width: 120px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Unit Options */
    .unit-options-container {
        border: 2px solid #000000;
        padding: 0.75rem;
        margin-top: 0.75rem;
    }
    
    .unit-option-item {
        display: grid;
        grid-template-columns: 2fr 2fr auto auto;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        align-items: center;
        padding: 0.75rem;
        border: 2px solid #000000;
        background-color: #ffffff;
    }
    
    .unit-option-item select {
        padding: 0.5rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 1rem;
        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
        cursor: pointer;
    }
    
    .unit-option-item input[type="text"],
    .unit-option-item input[type="number"] {
        padding: 0.5rem;
        border: 2px solid #000000;
        background-color: #ffffff;
        color: #000000;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 1rem;
        width: 100%;
        box-sizing: border-box;
        min-height: 2.5rem;
    }
    
    .unit-option-item input[type="text"]:focus,
    .unit-option-item input[type="number"]:focus {
        outline: none;
        border-color: #000000;
    }
    
    .unit-option-item label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 1rem;
        cursor: pointer;
        white-space: nowrap;
        padding: 0.25rem 0;
    }
    
    .unit-option-item input[type="radio"] {
        width: 1.2rem;
        height: 1.2rem;
        margin: 0;
        cursor: pointer;
        accent-color: #000000;
    }
    
    .unit-option-item .custom-unit-input {
        grid-column: 1 / -1;
        margin-top: 0.5rem;
    }
    
    .add-unit-option-btn {
        margin-top: 0.5rem;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
    }
    
    .remove-unit-option-btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-weight: bold;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        background-color: #ffffff;
        color: #dc2626;
        border: 2px solid #dc2626;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        min-height: 2.5rem;
        align-self: center;
    }
    
    .remove-unit-option-btn:hover {
        background-color: #dc2626;
        color: #ffffff;
    }
    
    /* View Toggle - Modern Segmented Control Style */
    .view-toggle {
        display: inline-flex;
        gap: 0;
        margin-bottom: 2rem;
        padding: 0.4rem;
        border: 3px solid #000000;
        border-radius: 12px;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow-x: auto;
        width: 100%;
        max-width: 100%;
        justify-content: center;
    }
    
    .view-toggle::-webkit-scrollbar {
        height: 6px;
    }
    
    .view-toggle::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .view-toggle::-webkit-scrollbar-thumb {
        background: #000000;
        border-radius: 3px;
    }
    
    .view-toggle-btn {
        padding: 0.75rem 1.25rem;
        border: none;
        background-color: transparent;
        color: #000000;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        font-size: 0.95rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s ease;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        white-space: nowrap;
        position: relative;
        border-radius: 8px;
        flex-shrink: 0;
    }
    
    .view-toggle-btn:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 20%;
        height: 60%;
        width: 1px;
        background: #e0e0e0;
    }
    
    .view-toggle-btn:hover {
        background-color: #f5f5f5;
        color: #000000;
    }
    
    .view-toggle-btn.active {
        background-color: #000000;
        color: #ffffff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .view-toggle-btn.active::after {
        display: none;
    }
    
    /* Category Filter Boxes */
    .categories-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .category-filter-box {
        border: 3px solid #000000;
        padding: 1rem;
        background-color: #ffffff;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .category-filter-box:hover {
        transform: translateY(-2px);
        background-color: #f5f5f5;
    }
    
    .category-filter-box.active {
        background-color: #000000;
        color: #ffffff;
    }
    
    .category-filter-name {
        font-size: 1.1rem;
        font-weight: bold;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #000000;
        margin-bottom: 0.25rem;
    }
    
    .category-filter-box.active .category-filter-name {
        color: #ffffff;
    }
    
    .category-filter-filipino {
        font-size: 0.85rem;
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: #666;
        margin-top: 0.25rem;
    }
    
    .category-filter-box.active .category-filter-filipino {
        color: rgba(255, 255, 255, 0.8);
    }
    
    /* Image Preview */
    .image-preview {
        width: 100%;
        max-width: 300px;
        aspect-ratio: 4/3;
        object-fit: cover;
        border: 2px solid #000000;
        margin-top: 0.5rem;
        display: none;
    }
    
    .image-preview.show {
        display: block;
    }
    
    /* Two Column Layout */
    .two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    
    @media (max-width: 768px) {
        .two-column {
            grid-template-columns: 1fr;
        }
        
        .unit-option-item {
            grid-template-columns: 1fr;
        }
        
        .unit-option-item .custom-unit-input {
            grid-column: 1 / -1;
        }
    }

    /* Shared header uses VT323 elsewhere — readable sans on this page only */
    body.manage-products-page .header,
    body.manage-products-page .header a,
    body.manage-products-page .header button,
    body.manage-products-page .header .logo,
    body.manage-products-page .header .nav a,
    body.manage-products-page .account-dropdown-toggle,
    body.manage-products-page .account-dropdown-menu a,
    body.manage-products-page .notification-bell,
    body.manage-products-page .notification-badge,
    body.manage-products-page .notification-header h3,
    body.manage-products-page .mark-all-read-btn,
    body.manage-products-page .notification-loading,
    body.manage-products-page .notification-empty,
    body.manage-products-page .notification-item,
    body.manage-products-page .hamburger-menu,
    body.manage-products-page .mobile-menu,
    body.manage-products-page .mobile-menu a,
    body.manage-products-page .mobile-menu-nav a,
    body.manage-products-page .mobile-menu-nav button,
    body.manage-products-page .mobile-menu-footer,
    body.manage-products-page .mobile-menu-footer-brand,
    body.manage-products-page .mobile-menu-footer-meta {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif !important;
    }
</style>

<body class="manage-products-page">
<?php include 'includes/header-market-finder.php'; ?>
<div class="manage-products-container">

    <!-- Success Notification -->
    <?php if ($success_message): ?>
    <div class="slide-notification auto-hide" id="notification">
        <div class="notification-content">
            <img src="assets/images/demi doggu.gif" alt="Success!" class="notification-icon">
            <div class="notification-text"><?php echo htmlspecialchars($success_message); ?></div>
            <button class="close-btn" onclick="document.getElementById('notification').remove()">×</button>
                                </div>
                            </div>
                            <?php endif; ?>

    <!-- Error Notification -->
    <?php if ($error_message): ?>
    <div class="slide-notification auto-hide" id="notification-error">
        <div class="notification-content">
            <svg class="notification-icon" style="background: #DC2626; padding: 6px;" fill="white" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
            <div class="notification-text"><?php echo htmlspecialchars($error_message); ?></div>
            <button class="close-btn" onclick="document.getElementById('notification-error').remove()">×</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="manage-products-hero">
        <h1>MANAGE PRODUCTS</h1>
        <p>Add, edit, and manage your products</p>
</div>

    <!-- View Toggle -->
    <div class="view-toggle">
        <button class="view-toggle-btn active" onclick="showView('form')">ADD/EDIT PRODUCT</button>
        <button class="view-toggle-btn" onclick="showView('products')">VIEW PRODUCTS</button>
            </div>
            
    <!-- Add/Edit Product Form -->
    <div id="form-view" class="view-section">
        <div class="manage-card">
            <h2><?php echo $edit_product ? 'EDIT PRODUCT' : 'ADD NEW PRODUCT'; ?></h2>
            <form method="POST" enctype="multipart/form-data" id="product-form">
                <input type="hidden" name="action" value="<?php echo $edit_product ? 'update_product' : 'add_product'; ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <?php 
                    // If farmer has only one market, auto-select it and hide dropdown
                    $single_market = count($all_markets) === 1;
                    $default_market_id = $single_market ? $all_markets[0]['id'] : ($edit_product ? $edit_product['market_id'] : ($active_market_id ?? null));
                    ?>
                    
                    <?php if ($single_market): ?>
                        <!-- Single market: Show as read-only text -->
                        <label for="market_id">MARKET</label>
                        <input type="text" value="<?php echo htmlspecialchars($all_markets[0]['market_name']); ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                        <input type="hidden" name="market_id" value="<?php echo $all_markets[0]['id']; ?>">
                    <?php else: ?>
                        <!-- Multiple markets: Show dropdown -->
                        <label for="market_id">MARKET *</label>
                        <select name="market_id" id="market_id" required>
                            <option value="">Select Market</option>
                            <?php foreach ($all_markets as $market): ?>
                                <option value="<?php echo $market['id']; ?>" <?php 
                                    $is_selected = false;
                                    if ($edit_product && $edit_product['market_id'] == $market['id']) {
                                        $is_selected = true;
                                    } elseif (!$edit_product && $default_market_id && $market['id'] == $default_market_id) {
                                        $is_selected = true;
                                    }
                                    echo $is_selected ? 'selected' : '';
                                ?>>
                                    <?php echo htmlspecialchars($market['market_name']); ?>
                                </option>
            <?php endforeach; ?>
                        </select>
        <?php endif; ?>
                    </div>
                    
                <div class="form-group">
                    <label for="product_name">PRODUCT NAME *</label>
                    <input type="text" name="product_name" id="product_name" list="fsProductNameList" value="<?php echo $edit_product ? htmlspecialchars($edit_product['product_name']) : ''; ?>" required>
                    <datalist id="fsProductNameList">
                        <?php foreach ($product_name_suggestions as $pn): ?>
                            <option value="<?php echo htmlspecialchars((string)$pn); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="category">CATEGORY</label>
                    <select name="category" id="category">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>" <?php echo ($edit_product && $edit_product['category'] == $cat['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            
                <div class="form-group">
                    <label for="description">DESCRIPTION</label>
                    <textarea name="description" id="description" rows="4"><?php echo $edit_product ? htmlspecialchars($edit_product['product_description'] ?? '') : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="product_image">PRODUCT IMAGE</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="product_image" id="product_image" accept="image/*" onchange="previewImage(this); updateFileName(this);" style="display: none;">
                        <label for="product_image" class="file-input-label">
                            <span class="file-input-text" id="file-input-text">CHOOSE FILE</span>
                            <span class="file-input-icon">📁</span>
                </label>
                        <span class="file-name-display" id="file-name-display"></span>
                            </div>
                    <?php if ($edit_product && !empty($edit_product['product_image'])): ?>
                        <img src="<?php echo htmlspecialchars($edit_product['product_image']); ?>" alt="Current Image" class="image-preview show" id="current-image-preview">
                            <?php else: ?>
                        <img src="" alt="Preview" class="image-preview" id="image-preview">
    <?php endif; ?>
                </div>
                
                <!-- Unit Options -->
                <div class="unit-options-container">
                    <h3 style="font-size: 1rem; font-weight: bold; font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; margin-bottom: 0.75rem;">PRICING OPTIONS</h3>
                    <div id="unit-options-list">
                        <?php
                        $unit_options = $edit_product && isset($edit_product['unit_options']) ? $edit_product['unit_options'] : [];
                        if (empty($unit_options)) {
                            $unit_options = [['unit_label' => '', 'quantity' => 1, 'price' => '', 'is_default' => true]];
                        }
                        foreach ($unit_options as $index => $option):
                        ?>
                            <div class="unit-option-item" data-index="<?php echo $index; ?>">
                                <select name="unit_option_label[]" required onchange="handleUnitSelectChange(this)">
                                    <option value="">Select Unit</option>
                                    <option value="kg" <?php echo (($option['unit_label'] ?? '') === 'kg') ? 'selected' : ''; ?>>kg (Kilogram)</option>
                                    <option value="g" <?php echo (($option['unit_label'] ?? '') === 'g') ? 'selected' : ''; ?>>g (Gram)</option>
                                    <option value="piece" <?php echo (($option['unit_label'] ?? '') === 'piece') ? 'selected' : ''; ?>>piece</option>
                                    <option value="bundle" <?php echo (($option['unit_label'] ?? '') === 'bundle') ? 'selected' : ''; ?>>bundle</option>
                                    <option value="pack" <?php echo (($option['unit_label'] ?? '') === 'pack') ? 'selected' : ''; ?>>pack</option>
                                    <option value="dozen" <?php echo (($option['unit_label'] ?? '') === 'dozen') ? 'selected' : ''; ?>>dozen</option>
                                    <option value="box" <?php echo (($option['unit_label'] ?? '') === 'box') ? 'selected' : ''; ?>>box</option>
                                    <option value="sack" <?php echo (($option['unit_label'] ?? '') === 'sack') ? 'selected' : ''; ?>>sack</option>
                                    <option value="liter" <?php echo (($option['unit_label'] ?? '') === 'liter') ? 'selected' : ''; ?>>liter</option>
                                    <option value="ml" <?php echo (($option['unit_label'] ?? '') === 'ml') ? 'selected' : ''; ?>>ml (Milliliter)</option>
                                    <option value="custom" <?php echo (!in_array($option['unit_label'] ?? '', ['kg', 'g', 'piece', 'bundle', 'pack', 'dozen', 'box', 'sack', 'liter', 'ml']) && !empty($option['unit_label'])) ? 'selected' : ''; ?>>Custom...</option>
                                </select>
                                <input type="text" name="unit_option_custom_label[]" placeholder="Enter custom unit" value="<?php echo (!in_array($option['unit_label'] ?? '', ['kg', 'g', 'piece', 'bundle', 'pack', 'dozen', 'box', 'sack', 'liter', 'ml']) && !empty($option['unit_label'])) ? htmlspecialchars($option['unit_label']) : ''; ?>" class="custom-unit-input" style="display: <?php echo (!in_array($option['unit_label'] ?? '', ['kg', 'g', 'piece', 'bundle', 'pack', 'dozen', 'box', 'sack', 'liter', 'ml']) && !empty($option['unit_label'])) ? 'block' : 'none'; ?>;">
                                <input type="number" name="unit_option_price[]" placeholder="Price per unit" step="0.01" min="0" value="<?php echo htmlspecialchars($option['price'] ?? ''); ?>" required>
                                <input type="hidden" name="unit_option_quantity[]" value="1">
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="radio" name="unit_option_default_index" value="<?php echo $index; ?>" <?php echo (!empty($option['is_default'])) ? 'checked' : ''; ?>>
                                    Default
                    </label>
                                <?php if (count($unit_options) > 1): ?>
                                    <button type="button" class="remove-unit-option-btn" onclick="removeUnitOption(this)">Remove</button>
                <?php endif; ?>
                </div>
                        <?php endforeach; ?>
            </div>
                    <button type="button" class="manage-btn manage-btn-secondary add-unit-option-btn" onclick="addUnitOption()">ADD PRICING OPTION</button>
</div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="is_available" value="1" <?php echo ($edit_product && ($edit_product['is_available'] ?? 1) == 1) ? 'checked' : ''; ?>>
                        <span>Available for sale</span>
                    </label>
            </div>
            
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="manage-btn"><?php echo $edit_product ? 'UPDATE PRODUCT' : 'ADD PRODUCT'; ?></button>
                    <?php if ($edit_product): ?>
                        <a href="manage-products.php" class="manage-btn manage-btn-secondary">CANCEL</a>
                    <?php endif; ?>
                </div>
            </form>
    </div>
</div>

    <!-- View Products -->
    <div id="products-view" class="view-section" style="display: none;">
        <div class="manage-card">
            <h2 style="margin-bottom: 1.5rem;">ALL PRODUCTS</h2>
            <?php if ($hasMarketProductApproval): ?>
            <p style="font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 1rem; color: #444; margin-bottom: 1.25rem; line-height: 1.45; max-width: 52rem;">
                <strong>Market Finder</strong> only counts products <strong>approved</strong> for public listing. “Available” here means in stock for your stall—it can still be <strong>pending DTI/admin approval</strong>, which is why category counts on the public Market Finder page may show 0 until approved.
            </p>
            <?php endif; ?>
            
            <!-- Category Filter Boxes -->
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 0.9rem; margin-bottom: 0.75rem; letter-spacing: 0.05em; color: #000000;">FILTER BY CATEGORY</label>
                <div class="categories-filter-grid">
                    <div class="category-filter-box active" data-category="" onclick="filterProductsByCategoryBox('')">
                        <div class="category-filter-name">ALL</div>
                    </div>
                    <?php foreach ($categories as $cat): ?>
                        <div class="category-filter-box" data-category="<?php echo htmlspecialchars($cat['name']); ?>" onclick="filterProductsByCategoryBox('<?php echo htmlspecialchars($cat['name']); ?>')">
                            <div class="category-filter-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <?php if (!empty($cat['filipino_name'])): ?>
                                <div class="category-filter-filipino"><?php echo htmlspecialchars($cat['filipino_name']); ?></div>
                            <?php endif; ?>
                    </div>
                            <?php endforeach; ?>
                    </div>
                    </div>
            <?php if (empty($all_products)): ?>
                <p style="text-align: center; padding: 2rem; font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif; font-size: 1.2rem;">No products found. Add your first product!</p>
    <?php else: ?>
                <div class="products-grid" id="products-grid">
                    <?php foreach ($all_products as $product): ?>
                        <div class="product-card" data-category="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">
                            <?php if (!empty($product['product_image'])): ?>
                                <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image" style="background-color: #f5f5f5; display: flex; align-items: center; justify-content: center; color: #999;">
                                    No Image
                                </div>
                            <?php endif; ?>
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-price">
                                <?php
                                $unit_options = $product['unit_options'] ?? [];
                                $default_option = getDefaultUnitOption($unit_options);
                                
                                // Fallback to legacy price/unit fields if no unit options exist
                                if (!$default_option && isset($product['price']) && $product['price'] > 0) {
                                    $default_option = [
                                        'price' => $product['price'],
                                        'unit_label' => $product['unit'] ?? 'kg',
                                        'quantity' => 1
                                    ];
                                }
                                
                                if ($default_option):
                                    echo '₱' . number_format($default_option['price'], 2) . ' / ' . formatUnitDisplayText($default_option['unit_label'] ?? 'kg', $default_option['quantity'] ?? 1);
                                else:
                                    echo 'Price not set';
                                endif;
                                ?>
</div>
                            <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                                Market: <?php echo htmlspecialchars($product['market_name']); ?>
            </div>
                            <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                                Status: <strong><?php echo ($product['is_available'] ?? 1) == 1 ? 'Available' : 'Not Available'; ?></strong>
        </div>
                            <?php if ($hasMarketProductApproval): ?>
                        <?php 
                                $ap = strtolower(trim((string)($product['approval_status'] ?? '')));
                                if ($ap === 'approved' || $ap === '' || $product['approval_status'] === null) {
                                    $apLabel = 'Approved (shown on Market Finder when in stock)';
                                } elseif ($ap === 'rejected') {
                                    $apLabel = 'Not approved for listing';
                                } else {
                                    $apLabel = 'Pending admin/DTI approval — hidden on Market Finder';
                                }
                            ?>
                            <div style="font-size: 0.85rem; margin-bottom: 0.5rem; color: #333;">
                                Public listing: <strong><?php echo htmlspecialchars($apLabel); ?></strong>
                                    </div>
                            <?php endif; ?>
                            <div class="product-actions">
                                <a href="manage-products.php?product_id=<?php echo $product['id']; ?>" class="manage-btn manage-btn-secondary">EDIT</a>
                                <?php if (($product['is_available'] ?? 1) == 1): ?>
                                    <form method="POST" action="manage-products.php" style="flex: 1;" onsubmit="return confirm('Mark as not available?');">
                                    <input type="hidden" name="action" value="mark_out_of_stock">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="manage-btn manage-btn-secondary" style="width: 100%;">NOT AVAILABLE</button>
                                </form>
                                <?php else: ?>
                                    <form method="POST" action="manage-products.php" style="flex: 1;">
                                    <input type="hidden" name="action" value="restock_product">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="manage-btn">RESTOCK</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="manage-products.php" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="manage-btn manage-btn-danger" style="width: 100%;">DELETE</button>
                                </form>
                                </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
    <?php endif; ?>
                        </div>
                    </div>
                    
                </div>

<script>
    function showView(view) {
        // Hide all views
        document.querySelectorAll('.view-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Remove active class from all buttons
        document.querySelectorAll('.view-toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Show selected view
        document.getElementById(view + '-view').style.display = 'block';
        
        // Add active class to clicked button
        event.target.classList.add('active');
    }
    
    function updateFileName(input) {
        const fileNameDisplay = document.getElementById('file-name-display');
        if (input.files && input.files[0]) {
            fileNameDisplay.textContent = input.files[0].name;
    } else {
            fileNameDisplay.textContent = '';
        }
    }
    
    function previewImage(input) {
        const preview = document.getElementById('image-preview');
        const currentPreview = document.getElementById('current-image-preview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.add('show');
                }
                if (currentPreview) {
                    currentPreview.style.display = 'none';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function addUnitOption() {
        const container = document.getElementById('unit-options-list');
        const index = container.children.length;
        const optionHtml = `
            <div class="unit-option-item" data-index="${index}">
                <select name="unit_option_label[]" required onchange="handleUnitSelectChange(this)">
                    <option value="">Select Unit</option>
                    <option value="kg">kg (Kilogram)</option>
                    <option value="g">g (Gram)</option>
                    <option value="piece">piece</option>
                    <option value="bundle">bundle</option>
                    <option value="pack">pack</option>
                    <option value="dozen">dozen</option>
                    <option value="box">box</option>
                    <option value="sack">sack</option>
                    <option value="liter">liter</option>
                    <option value="ml">ml (Milliliter)</option>
                    <option value="custom">Custom...</option>
                </select>
                <input type="text" name="unit_option_custom_label[]" placeholder="Enter custom unit" class="custom-unit-input" style="display: none;">
                <input type="number" name="unit_option_price[]" placeholder="Price per unit" step="0.01" min="0" required>
                <input type="hidden" name="unit_option_quantity[]" value="1">
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="radio" name="unit_option_default_index" value="${index}">
                Default
                </label>
                <button type="button" class="remove-unit-option-btn" onclick="removeUnitOption(this)">Remove</button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', optionHtml);
        updateDefaultRadios();
    }
    
    function removeUnitOption(button) {
        const item = button.closest('.unit-option-item');
        if (document.getElementById('unit-options-list').children.length > 1) {
            item.remove();
            updateDefaultRadios();
    } else {
            alert('At least one pricing option is required.');
        }
    }
    
    function updateDefaultRadios() {
        const items = document.querySelectorAll('.unit-option-item');
        items.forEach((item, index) => {
            const radio = item.querySelector('input[type="radio"]');
            if (radio) {
                radio.value = index;
                if (index === 0 && !document.querySelector('input[name="unit_option_default_index"]:checked')) {
                    radio.checked = true;
                }
            }
        });
    }
    
    // Filter products by category (using boxes)
    function filterProductsByCategoryBox(categoryName) {
        // Update active state on boxes
        document.querySelectorAll('.category-filter-box').forEach(box => {
            box.classList.remove('active');
        });
        
        // Find and activate the clicked box
        const clickedBox = document.querySelector(`.category-filter-box[data-category="${categoryName}"]`);
        if (clickedBox) {
            clickedBox.classList.add('active');
        }
        
        // Filter products
        const productCards = document.querySelectorAll('.product-card');
        productCards.forEach(card => {
            const cardCategory = card.getAttribute('data-category') || '';
            if (!categoryName || cardCategory.toLowerCase() === categoryName.toLowerCase()) {
                card.style.display = 'block';
        } else {
                card.style.display = 'none';
        }
    });
}

    // Handle unit select change - show/hide custom input
    function handleUnitSelectChange(selectElement) {
        const customInput = selectElement.parentElement.querySelector('.custom-unit-input');
        if (customInput) {
            if (selectElement.value === 'custom') {
                customInput.style.display = 'block';
                customInput.required = true;
    } else {
                customInput.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }
    }
    
    // Initialize default radio if none is checked and unit select handlers
    document.addEventListener('DOMContentLoaded', function() {
        updateDefaultRadios();
        
        // Add change handlers to all unit selects
        document.querySelectorAll('select[name="unit_option_label[]"]').forEach(select => {
            select.addEventListener('change', function() {
                handleUnitSelectChange(this);
            });
            // Check initial state
            handleUnitSelectChange(select);
        });
});
</script>

</body>
</html>

