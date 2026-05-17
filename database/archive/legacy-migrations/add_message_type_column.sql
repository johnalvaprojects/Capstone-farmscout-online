-- Add message_type column to reservation_messages table
-- This allows distinguishing between user messages, farmer messages, and system messages

ALTER TABLE reservation_messages
ADD COLUMN message_type ENUM('user', 'farmer', 'system') DEFAULT 'user' 
COMMENT 'Type of message: user (from consumer), farmer (from farmer), or system (auto-generated)' 
AFTER sender_id;

-- Update existing messages to have correct type based on sender role
-- Note: This assumes sender_id corresponds to a user with a role
-- We'll set default to 'user' for existing messages, but they can be corrected if needed

