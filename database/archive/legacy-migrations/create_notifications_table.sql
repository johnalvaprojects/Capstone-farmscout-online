-- Create Notifications Table
-- This table stores in-app notifications for users and farmers

CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT 'User who receives the notification',
    type VARCHAR(50) NOT NULL COMMENT 'Type: new_reservation, reservation_accepted, reservation_declined, new_message',
    title VARCHAR(255) NOT NULL COMMENT 'Notification title',
    message TEXT NOT NULL COMMENT 'Notification message/description',
    link VARCHAR(255) NULL COMMENT 'Optional link to related page (e.g., reservation_id)',
    is_read TINYINT(1) DEFAULT 0 COMMENT '0 = unread, 1 = read',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

