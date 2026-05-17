-- Add payment + new status system to reservations
-- Statuses required: Pending, Confirmed, Paid, Completed, Cancelled

START TRANSACTION;

-- 1) Add payment fields (safe if already exists is NOT guaranteed; run once)
ALTER TABLE reservations
  ADD COLUMN payment_method ENUM('cash','gcash') NULL AFTER notes,
  ADD COLUMN gcash_reference VARCHAR(120) NULL AFTER payment_method,
  ADD COLUMN confirmed_at TIMESTAMP NULL AFTER accepted_at,
  ADD COLUMN paid_at TIMESTAMP NULL AFTER confirmed_at;

-- 2) Normalize existing statuses from old enum
UPDATE reservations SET status = 'cancelled' WHERE status = 'declined';
UPDATE reservations SET status = 'confirmed' WHERE status = 'accepted';

-- 3) Update status enum to new set
ALTER TABLE reservations
  MODIFY COLUMN status ENUM('pending','confirmed','paid','completed','cancelled') NOT NULL DEFAULT 'pending';

COMMIT;

