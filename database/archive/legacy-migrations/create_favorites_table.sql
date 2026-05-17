-- Create favorites/wishlist table
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_session` varchar(100) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `market_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_favorite` (`user_id`, `product_id`, `market_id`),
  UNIQUE KEY `unique_favorite_session` (`user_session`, `product_id`, `market_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_session` (`user_session`),
  KEY `idx_product` (`product_id`),
  KEY `idx_market` (`market_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

