-- Merge Vendor Role into Farmer Role
-- This script converts all vendors to farmers and removes vendor role
-- Run this in phpMyAdmin or MySQL command line

USE farmscout_online;

-- Step 1: Convert all vendor users to farmers
UPDATE users 
SET user_role = 'farmer' 
WHERE user_role = 'vendor';

-- Step 2: Keep markets.vendor_id column (no rename needed)
-- Note: The vendor_id column keeps its name for backward compatibility
-- The code now treats vendor_id as "farmer who owns/manages this market"
-- No schema change needed - just semantic meaning changed
-- If you really want to rename the column (optional), uncomment:
-- ALTER TABLE markets CHANGE COLUMN vendor_id farmer_id INT;

-- Step 3: Update market_farmers table if needed (should already be using farmer_id)

-- Step 4: Remove vendor role from user_roles table (optional, for cleanup)
DELETE FROM user_roles WHERE role_name = 'vendor';

-- Step 5: Verify the changes
SELECT 'Users by Role:' as info;
SELECT user_role, COUNT(*) as count 
FROM users 
GROUP BY user_role;

SELECT 'Sample converted users:' as info;
SELECT id, username, email, user_role 
FROM users 
WHERE user_role = 'farmer' 
LIMIT 10;

-- Step 6: Show markets that are owned by farmers (vendor_id column now stores farmer IDs)
SELECT 'Markets owned by farmers (vendor_id column stores farmer IDs):' as info;
SELECT m.id, m.market_name, m.vendor_id, u.username, u.user_role
FROM markets m
LEFT JOIN users u ON m.vendor_id = u.id
WHERE m.vendor_id IS NOT NULL;

