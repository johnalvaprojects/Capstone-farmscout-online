-- =============================================================================
-- FarmScout Online — install missing DTI / dashboard–related tables
-- =============================================================================
-- Run in phpMyAdmin:
--   1. Select database: farmscout_online
--   2. Open SQL tab, paste this file, click Go
--
-- Safe to run multiple times (idempotent column/index checks).
-- Fixes: missing `reservations` table (admin-dashboard.php analytics, chat APIs).
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1) reservations (core)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT 'Consumer who made the reservation',
    farmer_id INT NOT NULL COMMENT 'Farmer who owns the product',
    product_id INT NOT NULL COMMENT 'Product being reserved',
    market_id INT NOT NULL COMMENT 'Market where product is sold',
    quantity DECIMAL(10,2) NOT NULL COMMENT 'Quantity requested (e.g., 5.00)',
    unit VARCHAR(50) DEFAULT NULL COMMENT 'Unit of measurement (e.g., kg, piece)',
    preferred_pickup_date DATE DEFAULT NULL COMMENT 'Preferred date for pickup',
    preferred_pickup_time TIME DEFAULT NULL COMMENT 'Preferred time for pickup',
    notes TEXT COMMENT 'Optional message from user',
    status ENUM('pending', 'accepted', 'declined', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    declined_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_farmer_id (farmer_id),
    KEY idx_product_id (product_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_reservations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_farmer FOREIGN KEY (farmer_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_product FOREIGN KEY (product_id) REFERENCES market_products (id) ON DELETE CASCADE,
    CONSTRAINT fk_reservations_market FOREIGN KEY (market_id) REFERENCES markets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 2) Optional columns on reservations (chat / archive / farmer notes)
--    Uses INFORMATION_SCHEMA so re-running does not error.
-- -----------------------------------------------------------------------------
SET @db := DATABASE();

-- is_archived
SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'is_archived') > 0,
        'SELECT ''reservations.is_archived already exists'' AS msg',
        'ALTER TABLE reservations ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = archived (hidden from user view)'' AFTER completed_at'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND INDEX_NAME = 'idx_is_archived') > 0,
        'SELECT ''idx_is_archived exists'' AS msg',
        'CREATE INDEX idx_is_archived ON reservations (is_archived)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- hide_from_chat_user / hide_from_chat_farmer
SET @sql := (
 SELECT IF(
   (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'hide_from_chat_user') > 0,
   'SELECT ''hide_from_chat_user exists'' AS msg',
   'ALTER TABLE reservations ADD COLUMN hide_from_chat_user TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''hidden from chat by user'' AFTER is_archived, ADD COLUMN hide_from_chat_farmer TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''hidden from chat by farmer'' AFTER hide_from_chat_user'
 )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- decline_reason (after declined_at)
SET @sql := (
 SELECT IF(
   (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'decline_reason') > 0,
   'SELECT ''decline_reason exists'' AS msg',
   'ALTER TABLE reservations ADD COLUMN decline_reason TEXT NULL COMMENT ''Farmer reason when declining'' AFTER declined_at'
 )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- accept_note (after decline_reason)
SET @sql := (
 SELECT IF(
   (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'accept_note') > 0,
   'SELECT ''accept_note exists'' AS msg',
   'ALTER TABLE reservations ADD COLUMN accept_note TEXT NULL COMMENT ''Farmer note when accepting'' AFTER decline_reason'
 )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) reservation_messages (chat threads)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservation_messages (
    id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL COMMENT 'Reservation this message belongs to',
    sender_id INT NOT NULL COMMENT 'User who sent the message',
    message_body TEXT NOT NULL COMMENT 'Message content',
    read_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the recipient read the message',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reservation_id (reservation_id),
    KEY idx_sender_id (sender_id),
    KEY idx_read_at (read_at),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_rm_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    CONSTRAINT fk_rm_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @sql := (
 SELECT IF(
   (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reservation_messages' AND COLUMN_NAME = 'message_type') > 0,
   'SELECT ''reservation_messages.message_type exists'' AS msg',
   'ALTER TABLE reservation_messages ADD COLUMN message_type ENUM(''user'', ''farmer'', ''system'') NOT NULL DEFAULT ''user'' COMMENT ''message origin'' AFTER sender_id'
 )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 4) typing_status (chat typing indicator)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS typing_status (
    id INT NOT NULL AUTO_INCREMENT,
    reservation_id INT NOT NULL COMMENT 'Reservation/conversation ID',
    user_id INT NOT NULL COMMENT 'User who is typing',
    is_typing TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = typing',
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity',
    PRIMARY KEY (id),
    UNIQUE KEY unique_user_reservation (reservation_id, user_id),
    KEY idx_reservation_id (reservation_id),
    KEY idx_user_id (user_id),
    KEY idx_last_activity (last_activity),
    CONSTRAINT fk_typing_res FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE,
    CONSTRAINT fk_typing_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 5) admin_logs (optional — used by logAdminAction() when approving products etc.)
--     FK uses ON DELETE CASCADE (NOT NULL admin_id; avoids invalid ON DELETE SET NULL)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT NOT NULL AUTO_INCREMENT,
    admin_id INT NOT NULL COMMENT 'Admin who performed the action',
    admin_username VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT DEFAULT NULL,
    action_details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_id (admin_id),
    KEY idx_action_type (action_type),
    KEY idx_target_type (target_type),
    KEY idx_target_id (target_id),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_admin_logs_user FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Done. Refresh: http://localhost/farmscout_online/admin-dashboard.php
-- =============================================================================
