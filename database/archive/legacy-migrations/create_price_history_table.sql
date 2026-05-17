-- Create Price History Table
-- Tracks price changes for products (farmer-only feature)

CREATE TABLE IF NOT EXISTS price_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL COMMENT 'Product that had price change',
    old_price DECIMAL(10, 2) NULL COMMENT 'Previous price (NULL for new products)',
    new_price DECIMAL(10, 2) NOT NULL COMMENT 'New price',
    unit VARCHAR(50) NOT NULL COMMENT 'Unit of measurement (kg, piece, etc.)',
    market_id INT NULL COMMENT 'Market where product is sold',
    farmer_id INT NOT NULL COMMENT 'Farmer who owns the product',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When price was changed',
    FOREIGN KEY (product_id) REFERENCES market_products(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_product_id (product_id),
    INDEX idx_farmer_id (farmer_id),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_market_product (product_id, market_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

