-- Update categories:
-- - Rename "Processed Goods" -> "Grains / Rice"
-- - Add "Poultry / Eggs"
-- Also update existing market_products.category values so product counts still match.

START TRANSACTION;

-- 1) Rename category (if it exists)
UPDATE categories
SET
  name = 'Grains / Rice',
  filipino_name = 'Bigas / Butil',
  description = 'Rice, grains, and staple goods',
  price_range = '₱35-₱180/kg',
  updated_at = CURRENT_TIMESTAMP
WHERE LOWER(TRIM(name)) = 'processed goods';

-- Keep sort order where it was (usually 5)
UPDATE categories
SET sort_order = COALESCE(sort_order, 5)
WHERE LOWER(TRIM(name)) = 'grains / rice';

-- 2) Add Poultry / Eggs (only if not already present)
INSERT INTO categories (name, filipino_name, description, icon_path, price_range, sort_order, is_active)
SELECT
  'Poultry / Eggs',
  'Manok / Itlog',
  'Chicken and eggs from local farms',
  NULL,
  '₱8-₱280',
  6,
  1
WHERE NOT EXISTS (
  SELECT 1 FROM categories WHERE LOWER(TRIM(name)) = 'poultry / eggs'
);

-- 3) Update market_products.category strings so counts still align
-- (Your APIs count products by matching market_products.category to categories.name)
UPDATE market_products
SET category = 'Grains / Rice'
WHERE LOWER(TRIM(category)) IN ('processed goods', 'processed');

COMMIT;

