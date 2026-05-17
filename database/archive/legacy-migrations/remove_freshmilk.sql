-- Remove all Fresh Milk products from market_products table
-- This script deletes all products with "Fresh Milk" in the name

USE farmscout_online;

-- Show what will be deleted (for verification)
SELECT 'Products to be deleted:' as info;
SELECT 
    id,
    market_id,
    product_name,
    category,
    price,
    unit
FROM market_products
WHERE LOWER(product_name) LIKE '%fresh milk%' OR LOWER(product_name) LIKE '%freshmilk%';

-- Delete all Fresh Milk products
DELETE FROM market_products
WHERE LOWER(product_name) LIKE '%fresh milk%' OR LOWER(product_name) LIKE '%freshmilk%';

-- Show remaining products count
SELECT 'Remaining products count:' as info;
SELECT COUNT(*) as total_products FROM market_products;

-- Show products by market
SELECT 
    m.market_name,
    COUNT(mp.id) as product_count
FROM markets m
LEFT JOIN market_products mp ON m.id = mp.market_id
GROUP BY m.id, m.market_name
ORDER BY m.market_name;

