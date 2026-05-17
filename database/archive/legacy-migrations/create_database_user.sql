-- Create Dedicated Database User for FarmScout Online
-- Run this in phpMyAdmin or MySQL command line
-- Replace 'farmscout_user' and 'your_strong_password' with your values

-- Create user
CREATE USER IF NOT EXISTS 'farmscout_user'@'localhost' IDENTIFIED BY 'your_strong_password';

-- Grant privileges only to farmscout_online database
GRANT ALL PRIVILEGES ON farmscout_online.* TO 'farmscout_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify user was created
SELECT User, Host FROM mysql.user WHERE User = 'farmscout_user';

-- Note: For production, you may want to:
-- 1. Use a stronger password (12+ characters, mixed case, numbers, symbols)
-- 2. Restrict privileges further (e.g., only SELECT, INSERT, UPDATE, DELETE, no DROP)
-- 3. Use a different host if database is on a different server

