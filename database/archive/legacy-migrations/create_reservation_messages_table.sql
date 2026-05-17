-- Create Reservation Messages Table
-- This table stores chat messages within reservation threads
-- Each reservation becomes a conversation between user and farmer

CREATE TABLE IF NOT EXISTS reservation_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT NOT NULL COMMENT 'Reservation this message belongs to',
    sender_id INT NOT NULL COMMENT 'User who sent the message (farmer or consumer)',
    message_body TEXT NOT NULL COMMENT 'Message content',
    read_at TIMESTAMP NULL COMMENT 'When the recipient read the message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reservation_id (reservation_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_read_at (read_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

