-- Fix corrupted peso symbol (₱) in categories table
-- The corruption happens when UTF-8 BOM is incorrectly interpreted
-- This script fixes: Γé▒ → ₱

UPDATE categories 
SET price_range = REPLACE(price_range, 'Γé▒', '₱')
WHERE price_range LIKE '%Γé▒%';

-- Also fix any other variations of the corruption
UPDATE categories 
SET price_range = REPLACE(price_range, 'Γ', '₱')
WHERE price_range LIKE '%Γ%' AND price_range NOT LIKE '%₱%';

-- Verify the fix
SELECT id, name, filipino_name, price_range 
FROM categories 
WHERE is_active = 1
ORDER BY sort_order;

