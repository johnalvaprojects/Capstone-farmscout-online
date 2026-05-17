-- Add is_archived column to reservations table
-- This allows users to hide reservations from their main view without deleting them

ALTER TABLE reservations
ADD COLUMN is_archived TINYINT(1) DEFAULT 0 COMMENT '1 = archived (hidden from user view), 0 = visible' AFTER completed_at;

CREATE INDEX idx_is_archived ON reservations(is_archived);

