-- Fix Price Alerts Foreign Key Constraint
-- This script fixes the incorrect foreign key constraint in price_alerts table
-- The constraint incorrectly references products(id) but should reference market_products(id)
-- OR be removed entirely since the code handles validation

-- Step 1: Remove the incorrect foreign key constraint if it exists
SET FOREIGN_KEY_CHECKS = 0;

-- Drop the incorrect foreign key constraint
ALTER TABLE `price_alerts` 
DROP FOREIGN KEY IF EXISTS `price_alerts_ibfk_1`;

SET FOREIGN_KEY_CHECKS = 1;

-- Step 2: Clean up orphaned price_alerts records (optional)
-- Remove any price_alerts that reference product_ids that don't exist in market_products
DELETE pa FROM price_alerts pa
LEFT JOIN market_products mp ON pa.product_id = mp.id
WHERE mp.id IS NULL;

-- Step 3: (Optional) Add correct foreign key constraint to market_products
-- Uncomment the following if you want to enforce referential integrity
-- Note: This will prevent inserting alerts for products that don't exist
/*
ALTER TABLE `price_alerts`
ADD CONSTRAINT `price_alerts_ibfk_1` 
FOREIGN KEY (`product_id`) 
REFERENCES `market_products` (`id`) 
ON DELETE CASCADE;
*/

-- Verification: Check if there are any remaining orphaned records
SELECT 
    COUNT(*) as orphaned_alerts
FROM price_alerts pa
LEFT JOIN market_products mp ON pa.product_id = mp.id
WHERE mp.id IS NULL;
