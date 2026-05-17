<?php
/**
 * Price Alert Checker
 * This script should be run periodically (e.g., via cron job) to check for price changes
 * and trigger email alerts when conditions are met.
 */

require_once __DIR__ . '/../includes/enhanced_functions.php';

echo "=== FarmScout Price Alert Checker ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

$conn = getDB();
if (!$conn) {
    echo "❌ Database connection failed!\n";
    exit(1);
}

try {
    // Get all products that have active price alerts
    $query = "SELECT DISTINCT p.id, p.name, p.filipino_name, p.current_price, p.previous_price, p.unit, p.image_url,
                     p.updated_at, COUNT(pa.id) as alert_count
              FROM products p 
              JOIN price_alerts pa ON p.id = pa.product_id 
              WHERE pa.is_active = 1 
              GROUP BY p.id
              ORDER BY alert_count DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products_with_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($products_with_alerts) . " products with active price alerts\n\n";
    
    $alerts_sent = 0;
    $products_checked = 0;
    
    foreach ($products_with_alerts as $product) {
        $products_checked++;
        echo "Checking: {$product['filipino_name']} (ID: {$product['id']})\n";
        echo "  Current Price: ₱" . number_format($product['current_price'], 2) . "\n";
        echo "  Previous Price: ₱" . number_format($product['previous_price'], 2) . "\n";
        echo "  Active Alerts: {$product['alert_count']}\n";
        
        // Check if price has changed
        if ($product['current_price'] != $product['previous_price']) {
            echo "  ✅ Price changed! Checking alerts...\n";
            
            // Trigger price alert checking
            $result = checkAndTriggerPriceAlerts(
                $product['id'], 
                $product['previous_price'], 
                $product['current_price']
            );
            
            if ($result) {
                // Count how many alerts were actually triggered
                $alert_query = "SELECT COUNT(*) as triggered FROM price_alert_logs 
                               WHERE alert_id IN (
                                   SELECT id FROM price_alerts WHERE product_id = :product_id AND is_active = 1
                               ) AND DATE(triggered_at) = CURDATE()";
                $alert_stmt = $conn->prepare($alert_query);
                $alert_stmt->bindParam(':product_id', $product['id'], PDO::PARAM_INT);
                $alert_stmt->execute();
                $triggered_today = $alert_stmt->fetch(PDO::FETCH_ASSOC)['triggered'];
                
                echo "    📧 {$triggered_today} alerts triggered for this product today\n";
                $alerts_sent += $triggered_today;
            } else {
                echo "    ⚠️ No alerts triggered (conditions not met)\n";
            }
        } else {
            echo "  ➡️ No price change detected\n";
        }
        echo "\n";
    }
    
    // Summary
    echo "=== Summary ===\n";
    echo "Products checked: {$products_checked}\n";
    echo "Total alerts sent: {$alerts_sent}\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
    // Log the checker run
    $log_query = "INSERT INTO app_config (config_key, config_value, updated_at) 
                  VALUES ('last_price_check', :timestamp, NOW()) 
                  ON DUPLICATE KEY UPDATE config_value = :timestamp, updated_at = NOW()";
    $log_stmt = $conn->prepare($log_query);
    $timestamp = date('Y-m-d H:i:s');
    $log_stmt->bindParam(':timestamp', $timestamp);
    $log_stmt->execute();
    
} catch (Exception $e) {
    echo "❌ Error during price alert checking: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

/**
 * Manual Price Update Function (for testing)
 * This simulates price changes for testing the alert system
 */
function simulatePriceChange($product_id, $new_price) {
    echo "\n=== Manual Price Update (Testing) ===\n";
    
    $conn = getDB();
    if (!$conn) {
        echo "❌ Database connection failed!\n";
        return;
    }
    
    // Get current price
    $query = "SELECT id, name, filipino_name, current_price FROM products WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo "❌ Product not found!\n";
        return;
    }
    
    $old_price = $product['current_price'];
    echo "Product: {$product['filipino_name']}\n";
    echo "Old Price: ₱" . number_format($old_price, 2) . "\n";
    echo "New Price: ₱" . number_format($new_price, 2) . "\n";
    
    // Update price
    $update_query = "UPDATE products SET previous_price = current_price, current_price = :new_price, updated_at = NOW() WHERE id = :id";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bindParam(':new_price', $new_price);
    $update_stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
    
    if ($update_stmt->execute()) {
        echo "✅ Price updated successfully!\n";
        
        // Trigger alerts
        echo "🔔 Checking for alerts to trigger...\n";
        checkAndTriggerPriceAlerts($product_id, $old_price, $new_price);
        echo "✅ Alert checking completed!\n";
    } else {
        echo "❌ Failed to update price!\n";
    }
}

// Example usage (uncomment to test):
// simulatePriceChange(1, 42.50); // Change product ID 1 to ₱42.50

echo "\n💡 To test the alert system manually, uncomment the simulatePriceChange() call at the end of this script.\n";
echo "   Example: simulatePriceChange(1, 42.50) - changes product ID 1 to ₱42.50\n";
?>