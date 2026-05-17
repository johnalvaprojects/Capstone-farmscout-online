-- Delete Admin Account (omniman)
-- Run this in phpMyAdmin or MySQL command line
-- WARNING: This will permanently delete the admin account and all associated data

USE farmscout_online;

-- First, let's see what we're about to delete
SELECT id, username, email, user_role, created_at 
FROM users 
WHERE username = 'omniman' OR email = 'livingtriburnal06@gmail.com';

-- Delete the admin account
DELETE FROM users 
WHERE username = 'omniman' OR email = 'livingtriburnal06@gmail.com';

-- Verify deletion
SELECT 'Account deleted. Remaining users:' as status;
SELECT id, username, email, user_role 
FROM users 
ORDER BY user_role, username;

