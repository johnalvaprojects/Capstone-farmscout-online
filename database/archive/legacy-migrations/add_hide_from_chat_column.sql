-- Add hide_from_chat column to reservations table
-- This allows users/farmers to hide reservations from the chat view
-- without deleting the reservation or messages

ALTER TABLE reservations
ADD COLUMN hide_from_chat_user TINYINT(1) DEFAULT 0 COMMENT '1 = hidden from chat by user, 0 = visible' AFTER is_archived,
ADD COLUMN hide_from_chat_farmer TINYINT(1) DEFAULT 0 COMMENT '1 = hidden from chat by farmer, 0 = visible' AFTER hide_from_chat_user;

