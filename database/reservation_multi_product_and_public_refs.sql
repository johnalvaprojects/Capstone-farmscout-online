-- =============================================================================
-- FarmScout — Multi-product reservations + public IDs (RSV-YYYY-NNNN, CHAT-...)
-- Safe to run multiple times (checks INFORMATION_SCHEMA).
-- Run in phpMyAdmin after selecting your database.
-- =============================================================================

SET @db := DATABASE();

-- parent_reservation_id: NULL = root row (owns chat thread + public refs)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'parent_reservation_id') > 0,
    'SELECT ''parent_reservation_id exists'' AS msg',
    'ALTER TABLE reservations ADD COLUMN parent_reservation_id INT NULL COMMENT ''FK to root reservation for line items'' AFTER id'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_parent_reservation_id') > 0,
    'SELECT ''idx_parent_reservation_id exists'' AS msg',
    'CREATE INDEX idx_parent_reservation_id ON reservations (parent_reservation_id)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'reservations' AND CONSTRAINT_NAME = 'fk_reservations_parent') > 0,
    'SELECT ''fk_reservations_parent exists'' AS msg',
    'ALTER TABLE reservations ADD CONSTRAINT fk_reservations_parent FOREIGN KEY (parent_reservation_id) REFERENCES reservations(id) ON DELETE CASCADE'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- public_ref (RSV-2026-0001)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'public_ref') > 0,
    'SELECT ''public_ref exists'' AS msg',
    'ALTER TABLE reservations ADD COLUMN public_ref VARCHAR(32) NULL COMMENT ''Display reservation id e.g. RSV-2026-0001'' AFTER notes'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'uq_reservations_public_ref') > 0,
    'SELECT ''uq_reservations_public_ref exists'' AS msg',
    'CREATE UNIQUE INDEX uq_reservations_public_ref ON reservations (public_ref)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- chat_public_ref (CHAT-2026-0001)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'chat_public_ref') > 0,
    'SELECT ''chat_public_ref exists'' AS msg',
    'ALTER TABLE reservations ADD COLUMN chat_public_ref VARCHAR(32) NULL COMMENT ''Display conversation id'' AFTER public_ref'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'uq_reservations_chat_public_ref') > 0,
    'SELECT ''uq_reservations_chat_public_ref exists'' AS msg',
    'CREATE UNIQUE INDEX uq_reservations_chat_public_ref ON reservations (chat_public_ref)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill legacy root rows (parent IS NULL): deterministic unique refs from id + year
UPDATE reservations r
SET
  public_ref = CONCAT('RSV-', DATE_FORMAT(r.created_at, '%Y'), '-', LPAD(r.id, 4, '0')),
  chat_public_ref = CONCAT('CHAT-', DATE_FORMAT(r.created_at, '%Y'), '-', LPAD(r.id, 4, '0'))
WHERE r.parent_reservation_id IS NULL
  AND r.public_ref IS NULL
  AND r.chat_public_ref IS NULL;
