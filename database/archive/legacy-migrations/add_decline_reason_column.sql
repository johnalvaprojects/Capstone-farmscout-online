-- Add decline_reason column to reservations table
-- This stores the reason/note that the farmer provides when declining a reservation

ALTER TABLE reservations 
ADD COLUMN decline_reason TEXT NULL COMMENT 'Reason provided by farmer when declining the reservation' 
AFTER declined_at;

