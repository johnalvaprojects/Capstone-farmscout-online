-- Phase 1: Market Hours Management & Status Toggle
-- Add new fields to markets table for better control

-- Add market hours and status fields
ALTER TABLE markets 
ADD COLUMN IF NOT EXISTS opening_time TIME DEFAULT '06:00:00',
ADD COLUMN IF NOT EXISTS closing_time TIME DEFAULT '18:00:00',
ADD COLUMN IF NOT EXISTS is_open BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS operating_days VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun';

-- Set vendor for Balaoan Market (skibiditoilet = user_id 2)
UPDATE markets SET vendor_id = 2 WHERE id = 1;

-- Create test farmer application (using chillguy06 = farmer_id 3)
-- This allows you to test approve/reject functionality
INSERT INTO farmer_applications 
(farmer_id, market_id, status, application_message, applied_at)
VALUES 
(3, 1, 'pending', 'Hello! I am chillguy06 and I would like to sell fresh vegetables, fruits, and meat products in Balaoan Public Market. I have experience farming and can provide quality products to customers.', NOW())
ON DUPLICATE KEY UPDATE status = 'pending';

-- Verify the changes
SELECT id, market_name, vendor_id, opening_time, closing_time, is_open, status, operating_days 
FROM markets;

-- Check the farmer application was created
SELECT fa.id, fa.farmer_id, u.username, fa.market_id, m.market_name, fa.status, fa.applied_at
FROM farmer_applications fa
JOIN users u ON fa.farmer_id = u.id
JOIN markets m ON fa.market_id = m.id
WHERE fa.farmer_id = 3;
