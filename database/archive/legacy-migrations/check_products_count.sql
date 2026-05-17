-- Check how many products exist in the database
-- This will tell us if there are more than 2 products total

-- Count total products in database
SELECT COUNT(*) as total_products FROM market_products;

-- Count products per market
SELECT 
    m.market_name,
    COUNT(mp.id) as product_count
FROM markets m
LEFT JOIN market_products mp ON m.id = mp.market_id
GROUP BY m.id, m.market_name
ORDER BY product_count DESC;

-- Show all products with their market assignments
SELECT 
    mp.id,
    mp.product_name,
    mp.category,
    m.market_name,
    mp.price
FROM market_products mp
JOIN markets m ON mp.market_id = m.id
ORDER BY m.market_name, mp.category;

-- Check if there are products without market assignment
SELECT COUNT(*) as products_without_market 
FROM market_products 
WHERE market_id IS NULL;
