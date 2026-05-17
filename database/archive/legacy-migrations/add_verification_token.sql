-- Add verification_token column to users table for email verification
USE farmscout_online;

-- Add verification_token column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL 
AFTER is_active;

-- Add index for verification token lookups
CREATE INDEX IF NOT EXISTS idx_verification_token ON users(verification_token);
