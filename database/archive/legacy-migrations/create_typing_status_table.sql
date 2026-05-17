-- Create Typing Status Table
-- Tracks when users are typing in chat conversations

CREATE TABLE IF NOT EXISTS typing_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL COMMENT 'Reservation/conversation ID',
    user_id INT NOT NULL COMMENT 'User who is typing',
    is_typing TINYINT(1) DEFAULT 1 COMMENT '1 = typing, 0 = stopped',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity timestamp',
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_reservation (reservation_id, user_id),
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
