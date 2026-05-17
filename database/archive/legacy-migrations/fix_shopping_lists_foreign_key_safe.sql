-- Safe fix for shopping_lists foreign key constraint
-- This script handles orphaned records before updating the foreign key

-- Step 1: Check for orphaned records (products in shopping_lists that don't exist in market_products)
SELECT 
    sl.id,
    sl.product_id,
    sl.user_session,
    sl.quantity
FROM shopping_lists sl
LEFT JOIN market_products mp ON sl.product_id = mp.id
WHERE mp.id IS NULL;

-- Step 2: Delete orphaned records (uncomment the line below to actually delete them)
-- DELETE sl FROM shopping_lists sl
-- LEFT JOIN market_products mp ON sl.product_id = mp.id
-- WHERE mp.id IS NULL;

-- Step 3: Drop the old foreign key constraint (if it exists)
ALTER TABLE `shopping_lists` 
DROP FOREIGN KEY IF EXISTS `shopping_lists_ibfk_1`;

-- Step 4: Add new foreign key constraint referencing market_products
ALTER TABLE `shopping_lists`
ADD CONSTRAINT `shopping_lists_ibfk_1` 
FOREIGN KEY (`product_id`) 
REFERENCES `market_products` (`id`) 
ON DELETE CASCADE;

-- Step 5: Verify the change
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

