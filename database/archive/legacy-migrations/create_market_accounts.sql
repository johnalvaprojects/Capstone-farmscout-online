-- Create Market Manager Accounts for Dummy Markets
-- Run this in phpMyAdmin's SQL tab

USE farmscout_online;

-- 1. San Fernando Market Account
INSERT INTO users (
    username,
    email,
    password_hash,
    full_name,
    user_role,
    is_active,
    login_method,
    is_verified,
    created_at,
    updated_at
) VALUES (
    'sanfernando_manager',
    'sanfernando.manager@example.com',
    '$2y$10$8X9dMMiYYF23VDBK5tpiieXRj5R0pIR9tJltu8cMewa7ff7aban9K',
    'San Fernando Market Admin',
    'farmer',
    1,
    'email',
    0,
    NOW(),
    NOW()
);

-- 2. Bauang Market Account
INSERT INTO users (
    username,
    email,
    password_hash,
    full_name,
    user_role,
    is_active,
    login_method,
    is_verified,
    created_at,
    updated_at
) VALUES (
    'bauang_manager',
    'bauang.manager@example.com',
    '$2y$10$NpvtTidFQUM7MDk3n.DiSOPPN3JGkOJQdspx7I1Leptqf1iGMCtg.',
    'Bauang Market Admin',
    'farmer',
    1,
    'email',
    0,
    NOW(),
    NOW()
);

-- 3. San Juan Market Account
INSERT INTO users (
    username,
    email,
    password_hash,
    full_name,
    user_role,
    is_active,
    login_method,
    is_verified,
    created_at,
    updated_at
) VALUES (
    'sanjuan_manager',
    'sanjuan.manager@example.com',
    '$2y$10$PROc/f7ag1LsZ8aU//cA8exBstGA9tkqXG87k9RbtssIjweTxVrCO',
    'San Juan Market Admin',
    'farmer',
    1,
    'email',
    0,
    NOW(),
    NOW()
);

-- 4. Agoo Market Account
INSERT INTO users (
    username,
    email,
    password_hash,
    full_name,
    user_role,
    is_active,
    login_method,
    is_verified,
    created_at,
    updated_at
) VALUES (
    'agoo_manager',
    'agoo.manager@example.com',
    '$2y$10$YrDj2YCYpCIhk9omNbVouOJztAnP6d.SPbSUHZZcfleUOf6x2dnmS',
    'Agoo Market Admin',
    'farmer',
    1,
    'email',
    0,
    NOW(),
    NOW()
);

-- Link accounts to their markets via vendor_id
-- San Fernando City Market (assuming market id = 2)
UPDATE markets 
SET vendor_id = (SELECT id FROM users WHERE username = 'sanfernando_manager' LIMIT 1)
WHERE market_name LIKE '%San Fernando%' OR id = 2;

-- Bauang Public Market (assuming market id = 3)
UPDATE markets 
SET vendor_id = (SELECT id FROM users WHERE username = 'bauang_manager' LIMIT 1)
WHERE market_name LIKE '%Bauang%' OR id = 3;

-- San Juan Market (assuming market id = 4)
UPDATE markets 
SET vendor_id = (SELECT id FROM users WHERE username = 'sanjuan_manager' LIMIT 1)
WHERE market_name LIKE '%San Juan%' OR id = 4;

-- Agoo Market (assuming market id = 5)
UPDATE markets 
SET vendor_id = (SELECT id FROM users WHERE username = 'agoo_manager' LIMIT 1)
WHERE market_name LIKE '%Agoo%' OR id = 5;

-- Optional: Add to market_farmers table (if it exists)
-- This ensures they show up in farmer listings
INSERT INTO market_farmers (market_id, farmer_id, stall_number, approval_status, approved_at, monthly_fee)
SELECT 
    m.id as market_id,
    u.id as farmer_id,
    'MGMT' as stall_number,
    'approved' as approval_status,
    NOW() as approved_at,
    0 as monthly_fee
FROM users u
JOIN markets m ON (
    (u.username = 'sanfernando_manager' AND (m.market_name LIKE '%San Fernando%' OR m.id = 2)) OR
    (u.username = 'bauang_manager' AND (m.market_name LIKE '%Bauang%' OR m.id = 3)) OR
    (u.username = 'sanjuan_manager' AND (m.market_name LIKE '%San Juan%' OR m.id = 4)) OR
    (u.username = 'agoo_manager' AND (m.market_name LIKE '%Agoo%' OR m.id = 5))
)
WHERE u.username IN ('sanfernando_manager', 'bauang_manager', 'sanjuan_manager', 'agoo_manager')
ON DUPLICATE KEY UPDATE
    approval_status = VALUES(approval_status),
    approved_at = VALUES(approved_at);

-- Verify the accounts were created
SELECT 
    u.id,
    u.username,
    u.email,
    u.user_role,
    u.is_active,
    m.market_name,
    m.vendor_id
FROM users u
LEFT JOIN markets m ON m.vendor_id = u.id
WHERE u.username IN ('sanfernando_manager', 'bauang_manager', 'sanjuan_manager', 'agoo_manager')
ORDER BY u.username;

