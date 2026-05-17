-- Complete fix for shopping_lists foreign key constraint
-- This will clean up orphaned records and update the foreign key

-- Step 1: Drop the old foreign key constraint first
ALTER TABLE `shopping_lists` 
DROP FOREIGN KEY IF EXISTS `shopping_lists_ibfk_1`;

-- Step 2: Delete orphaned records (shopping list items with product_ids that don't exist in market_products)
DELETE sl FROM shopping_lists sl
LEFT JOIN market_products mp ON sl.product_id = mp.id
WHERE mp.id IS NULL;

-- Step 3: Add new foreign key constraint referencing market_products
ALTER TABLE `shopping_lists`
ADD CONSTRAINT `shopping_lists_ibfk_1` 
FOREIGN KEY (`product_id`) 
REFERENCES `market_products` (`id`) 
ON DELETE CASCADE;

