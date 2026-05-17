-- FarmScout Online Database Schema
-- Run this SQL to create the database structure

CREATE DATABASE IF NOT EXISTS farmscout_online;
USE farmscout_online;

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    filipino_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon_path TEXT,
    price_range VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    filipino_name VARCHAR(150) NOT NULL,
    description TEXT,
    category_id INT NOT NULL,
    current_price DECIMAL(10, 2) NOT NULL,
    previous_price DECIMAL(10, 2) DEFAULT 0,
    unit VARCHAR(20) NOT NULL DEFAULT 'kg',
    image_url TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Vendors table
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    stall_number VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Price history table for tracking price changes
CREATE TABLE price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Insert default categories
INSERT INTO categories (name, filipino_name, description, icon_path, price_range, sort_order) VALUES
('Vegetables', 'Gulay', 'Fresh vegetables and leafy greens', 'M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z', '₱25-₱120/kg', 1),
('Fruits', 'Prutas', 'Fresh fruits and seasonal produce', 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z', '₱40-₱200/kg', 2),
('Meat', 'Karne', 'Fresh meat products', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', '₱280-₱450/kg', 3),
('Fish', 'Isda', 'Fresh fish and seafood', 'M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z', '₱150-₱350/kg', 4),
('Processed Goods', 'Processed', 'Packaged and processed food items', 'M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z', '₱35-₱180/pack', 5);

-- Insert sample products
INSERT INTO products (name, filipino_name, description, category_id, current_price, previous_price, unit, image_url, is_featured) VALUES
('Tomatoes', 'Kamatis', 'Fresh red tomatoes', 1, 45.00, 50.00, 'kg', 'https://images.pexels.com/photos/1327838/pexels-photo-1327838.jpeg?auto=compress&cs=tinysrgb&w=400', TRUE),
('Regular Rice', 'Bigas', 'Regular white rice', 5, 52.00, 52.00, 'kg', 'https://images.pixabay.com/photo/2016/08/11/08/04/rice-1584109_960_720.jpg', TRUE),
('Milkfish', 'Bangus', 'Fresh milkfish', 4, 180.00, 170.00, 'kg', 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?auto=format&fit=crop&w=400&q=80', TRUE),
('Red Onions', 'Sibuyas Pula', 'Fresh red onions', 1, 85.00, 100.00, 'kg', 'https://images.pexels.com/photos/1435735/pexels-photo-1435735.jpeg?auto=compress&cs=tinysrgb&w=400', TRUE),
('Pork Shoulder', 'Baboy Kasim', 'Fresh pork shoulder', 3, 320.00, 315.00, 'kg', 'https://images.pexels.com/photos/1435735/pexels-photo-1435735.jpeg?auto=compress&cs=tinysrgb&w=400', FALSE),
('Cabbage', 'Repolyo', 'Fresh cabbage', 1, 35.00, 30.00, 'kg', 'https://images.unsplash.com/photo-1594282486552-05b4d80fbb9f?auto=format&fit=crop&w=400&q=80', FALSE),
('Bananas', 'Saging', 'Fresh bananas', 2, 60.00, 55.00, 'kg', 'https://images.unsplash.com/photo-1571771894821-ce9b6c11b08e?auto=format&fit=crop&w=400&q=80', FALSE),
('Chicken', 'Manok', 'Fresh chicken', 3, 180.00, 175.00, 'kg', 'https://images.unsplash.com/photo-1604503468506-a8da13d82791?auto=format&fit=crop&w=400&q=80', FALSE),
('Tilapia', 'Tilapia', 'Fresh tilapia fish', 4, 120.00, 115.00, 'kg', 'https://images.unsplash.com/photo-1544943910-4c1dc44aab44?auto=format&fit=crop&w=400&q=80', FALSE),
('Cooking Oil', 'Mantika', 'Cooking oil 1L', 5, 85.00, 80.00, 'piece', 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?auto=format&fit=crop&w=400&q=80', FALSE);

-- Insert sample vendors
INSERT INTO vendors (name, contact_person, phone, stall_number) VALUES
('Aling Maria\'s Vegetables', 'Maria Santos', '09123456789', 'A-12'),
('Kuya Ben\'s Fish Stall', 'Benjamin Cruz', '09234567890', 'B-05'),
('Tita Rosa\'s Meat Shop', 'Rosa Garcia', '09345678901', 'C-18'),
('Mang Pedro\'s Fruits', 'Pedro Reyes', '09456789012', 'A-22'),
('Sari-Sari Store ni Aling Carmen', 'Carmen Lopez', '09567890123', 'D-08');

-- Create indexes for better performance
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_featured ON products(is_featured);
CREATE INDEX idx_products_active ON products(is_active);
CREATE INDEX idx_price_history_product ON price_history(product_id);
CREATE INDEX idx_price_history_date ON price_history(recorded_at);

-- Insert sample price history
INSERT INTO price_history (product_id, price, recorded_at) VALUES
(1, 50.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(1, 48.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 45.00, NOW()),
(2, 52.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 52.00, NOW()),
(3, 170.00, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 175.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 180.00, NOW());