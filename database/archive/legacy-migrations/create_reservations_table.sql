-- Create Reservations Table
-- This table stores product reservations made by users for farmers' products

CREATE TABLE IF NOT EXISTS reservations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL COMMENT 'Consumer who made the reservation',
    farmer_id INT NOT NULL COMMENT 'Farmer who owns the product',
    product_id INT NOT NULL COMMENT 'Product being reserved',
    market_id INT NOT NULL COMMENT 'Market where product is sold',
    quantity DECIMAL(10,2) NOT NULL COMMENT 'Quantity requested (e.g., 5.00)',
    unit VARCHAR(50) COMMENT 'Unit of measurement (e.g., kg, piece)',
    preferred_pickup_date DATE COMMENT 'Preferred date for pickup',
    preferred_pickup_time TIME COMMENT 'Preferred time for pickup',
    notes TEXT COMMENT 'Optional message from user',
    payment_method ENUM('cash','gcash') NULL COMMENT 'cash=pay at market, gcash=simulated reference',
    gcash_reference VARCHAR(120) NULL COMMENT 'Simulated reference number',
    status ENUM('pending', 'confirmed', 'paid', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL COMMENT '(Legacy) old status timestamp',
    confirmed_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    declined_at TIMESTAMP NULL COMMENT '(Legacy) old status timestamp',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES market_products(id) ON DELETE CASCADE,
    FOREIGN KEY (market_id) REFERENCES markets(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_farmer_id (farmer_id),
    INDEX idx_product_id (product_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

