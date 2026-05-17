-- Add accept_note column to reservations table
-- This stores the note/message that the farmer provides when accepting a reservation

ALTER TABLE reservations 
ADD COLUMN accept_note TEXT NULL COMMENT 'Note/message provided by farmer when accepting the reservation' 
AFTER decline_reason;

