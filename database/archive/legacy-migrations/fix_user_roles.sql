-- Fix existing users to have correct user_role values
-- Run this in phpMyAdmin or MySQL command line

USE farmscout_online;

-- Update admin account
UPDATE users 
SET user_role = 'admin' 
WHERE username = 'omniman' OR email = 'livingtriburnal06@gmail.com';

-- Update farmer account
UPDATE users 
SET user_role = 'farmer' 
WHERE username = 'chillguy06' OR email = 'wompskiwompwomp@gmail.com';

-- Update vendor account
UPDATE users 
SET user_role = 'vendor' 
WHERE username = 'skibiditoilet' OR email = 'gorrmeetthor06@gmail.com';

-- Update consumer accounts
UPDATE users 
SET user_role = 'consumer' 
WHERE username IN ('miruivan', 'skibidi') 
   OR email IN ('sominiru0922@gmail.com', 'goblinmachine140@gmail.com');

-- Verify the changes
SELECT id, username, email, user_role, role 
FROM users 
ORDER BY user_role, username;
