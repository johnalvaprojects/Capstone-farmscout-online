-- Create Admin Logs Table
-- This table stores all admin actions for accountability and traceability

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL COMMENT 'ID of the admin who performed the action',
    admin_username VARCHAR(255) NOT NULL COMMENT 'Username of the admin',
    action_type VARCHAR(100) NOT NULL COMMENT 'Type of action (e.g., product_approved, farmer_verified, user_deleted, reservation_status_update)',
    target_type VARCHAR(50) NOT NULL COMMENT 'Type of entity affected (product, user, reservation, farmer, etc.)',
    target_id INT NULL COMMENT 'ID of the affected entity',
    action_details TEXT NULL COMMENT 'JSON or text details about the action',
    ip_address VARCHAR(45) NULL COMMENT 'IP address of the admin',
    user_agent TEXT NULL COMMENT 'User agent/browser info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_target_type (target_type),
    INDEX idx_target_id (target_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
