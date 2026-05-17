-- Add password reset columns to users table
-- Run this script to enable forgot password functionality

-- Add password reset token column
ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(64) NULL;

-- Add password reset expiration column
ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL;

-- Add index for faster lookups
CREATE INDEX idx_password_reset_token ON users(password_reset_token);
CREATE INDEX idx_password_reset_expires ON users(password_reset_expires);

-- Optional: Clean up any existing expired tokens
UPDATE users SET password_reset_token = NULL, password_reset_expires = NULL 
WHERE password_reset_expires < NOW();
