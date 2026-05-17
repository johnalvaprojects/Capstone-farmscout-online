-- Assign All Products to Balaoan Public Market
-- This script moves all products from all markets to Balaoan Public Market
-- Since Balaoan is the only real beneficiary market

-- First, let's check which market_id is Balaoan
-- (Run this first to confirm the ID)
SELECT id, market_name FROM markets WHERE market_name LIKE '%Balaoan%';

-- Update all products to belong to Balaoan Public Market (ID = 1)
-- Change the market_id to 1 if Balaoan has a different ID
UPDATE market_products 
SET market_id = 1 
WHERE market_id != 1;

-- Verify the update
SELECT 
    m.market_name,
    COUNT(mp.id) as product_count
FROM markets m
LEFT JOIN market_products mp ON m.id = mp.market_id
GROUP BY m.id, m.market_name
ORDER BY m.market_name;

-- This should show all products now belong to Balaoan Public Market
