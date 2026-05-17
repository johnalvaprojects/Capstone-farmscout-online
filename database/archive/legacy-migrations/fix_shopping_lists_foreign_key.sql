-- Fix shopping_lists foreign key to reference market_products instead of products
-- Run this script to update the foreign key constraint

-- Step 1: Drop the old foreign key constraint
ALTER TABLE `shopping_lists` 
DROP FOREIGN KEY `shopping_lists_ibfk_1`;

-- Step 2: Add new foreign key constraint referencing market_products
ALTER TABLE `shopping_lists`
ADD CONSTRAINT `shopping_lists_ibfk_1` 
FOREIGN KEY (`product_id`) 
REFERENCES `market_products` (`id`) 
ON DELETE CASCADE;

-- Verify the change
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'shopping_lists'
    AND CONSTRAINT_NAME = 'shopping_lists_ibfk_1';

