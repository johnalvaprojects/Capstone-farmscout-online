-- COMPLETE FIX: Make Balaoan the only market with products
-- All other markets will be dummy markets with 0 products

-- Step 1: Check current situation
SELECT 'BEFORE UPDATE - Product Count Per Market' as info;
SELECT 
    m.id,
    m.market_name,
    COUNT(mp.id) as product_count
FROM markets m
LEFT JOIN market_products mp ON m.id = mp.market_id
GROUP BY m.id, m.market_name
ORDER BY m.market_name;

-- Step 2: Get Balaoan Market ID (should be 1)
SELECT id, market_name FROM markets WHERE market_name LIKE '%Balaoan%';

-- Step 3: Move ALL products to Balaoan (market_id = 1)
-- This updates every product to belong to Balaoan
UPDATE market_products 
SET market_id = 1;

-- Step 4: Verify products are now all in Balaoan
SELECT 'AFTER UPDATE - Product Count Per Market' as info;
SELECT 
    m.id,
    m.market_name,
    COUNT(mp.id) as product_count
FROM markets m
LEFT JOIN market_products mp ON m.id = mp.market_id
GROUP BY m.id, m.market_name
ORDER BY product_count DESC;

-- Step 5: Show all products (confirm they're all market_id = 1)
SELECT 
    id,
    product_name,
    category,
    market_id,
    price
FROM market_products
ORDER BY category, product_name;

-- NOTE: Vendors are stored in a DIFFERENT table (market_farmers)
-- Vendors ≠ Products
-- If you want to remove vendors from other markets too, run this:
-- UPDATE market_farmers SET market_id = 1;
