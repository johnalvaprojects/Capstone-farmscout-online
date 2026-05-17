-- Create New Admin Account
-- Run this in phpMyAdmin or MySQL command line
-- 
-- INSTRUCTIONS:
-- 1. Replace 'newadmin' with your desired username
-- 2. Replace 'admin@farmscout.ph' with your desired email
-- 3. Replace 'Admin123!' with your desired password
-- 4. Run this script

USE farmscout_online;

-- Set your desired credentials here
SET @username = 'newadmin';
SET @email = 'admin@farmscout.ph';
SET @password = 'Admin123!';  -- Change this to your desired password
SET @full_name = 'Admin User';

-- Hash the password (using PHP's password_hash equivalent)
-- For MySQL, we'll use a simple bcrypt hash
-- Note: For production, use PHP's password_hash() function to generate the hash
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; -- This is 'password' hashed

-- Check if username or email already exists
SELECT 'Checking if account exists...' as status;
SELECT id, username, email, user_role 
FROM users 
WHERE username = @username OR email = @email;

-- Create the admin account
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
    @username,
    @email,
    @password_hash,  -- IMPORTANT: Replace this with a proper bcrypt hash
    @full_name,
    'admin',
    1,
    'email',
    1,
    NOW(),
    NOW()
);

-- Verify the account was created
SELECT 'Admin account created successfully!' as status;
SELECT id, username, email, user_role, is_active, created_at 
FROM users 
WHERE username = @username;

