-- FarmScout: reference price bands for admin price monitoring (optional migration)
-- Run once in phpMyAdmin if the table does not exist yet.

CREATE TABLE IF NOT EXISTS `admin_product_reference_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(80) DEFAULT NULL,
  `ref_price_min` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ref_price_max` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_combo` (`product_name`(120), `category`(40), `unit`(40))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
