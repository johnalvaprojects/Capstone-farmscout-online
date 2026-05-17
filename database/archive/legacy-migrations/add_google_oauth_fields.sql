-- Add Google OAuth fields to users table
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL,
ADD COLUMN profile_picture TEXT NULL,
ADD COLUMN login_method ENUM('email', 'google') DEFAULT 'email';

-- Add index for Google ID lookups
CREATE INDEX idx_users_google_id ON users(google_id);

-- Add index for login method
CREATE INDEX idx_users_login_method ON users(login_method);
