-- Phase 1 Admin Features Database Updates
-- Run this SQL to add approval_status and verification_status columns

-- Add approval_status to market_products table
ALTER TABLE market_products 
ADD COLUMN IF NOT EXISTS approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER is_available;

-- Set existing products to 'approved' status
UPDATE market_products SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = '';

-- Add verification_status to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending' AFTER user_role;

-- Set existing admins to 'verified'
UPDATE users SET verification_status = 'verified' WHERE user_role = 'admin';

-- Set existing farmers to 'pending' (they need verification)
UPDATE users SET verification_status = 'pending' WHERE user_role = 'farmer' AND (verification_status IS NULL OR verification_status = '');

-- Add approved_by column to market_products for tracking who approved
ALTER TABLE market_products 
ADD COLUMN IF NOT EXISTS approved_by INT NULL AFTER approval_status,
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL AFTER approved_by;

-- Add verified_by column to users for tracking who verified
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS verified_by INT NULL AFTER verification_status,
ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP NULL AFTER verified_by;

