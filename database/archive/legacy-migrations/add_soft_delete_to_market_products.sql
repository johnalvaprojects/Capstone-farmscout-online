-- Add soft delete support to market_products table
-- This preserves all historical data (price history, alerts, reviews) when products are "deleted"
-- Run this migration to add the deleted_at column

USE farmscout_online;

-- Add deleted_at column (NULL = active, timestamp = soft deleted)
ALTER TABLE market_products 
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- Add index for faster queries filtering deleted products
CREATE INDEX idx_market_products_deleted_at ON market_products(deleted_at);

-- Add index for active products (deleted_at IS NULL)
CREATE INDEX idx_market_products_active ON market_products(market_id, deleted_at) WHERE deleted_at IS NULL;

-- Update existing queries will now need to filter: WHERE deleted_at IS NULL

