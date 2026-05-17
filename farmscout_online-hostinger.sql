-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 07:09 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `farmscout_online`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL COMMENT 'Admin who performed the action',
  `admin_username` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `action_details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_config`
--

CREATE TABLE `app_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(255) NOT NULL,
  `config_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_config`
--

INSERT INTO `app_config` (`id`, `config_key`, `config_value`, `created_at`, `updated_at`) VALUES
(1, 'email_config', '{\"from_email\":\"noreply@farmscout-online.com\",\"from_name\":\"FarmScout Online\",\"use_smtp\":true,\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":587,\"smtp_username\":\"wompskiwompwomp@gmail.com\",\"smtp_password\":\"gxpk pafn lfyk tnmw\",\"smtp_secure\":\"tls\",\"site_url\":\"http:\\/\\/localhost\\/farmscout_online\",\"support_email\":\"support@farmscout.com\",\"test_mode\":false,\"test_email\":\"test@example.com\",\"log_emails\":true,\"email_log_file\":\"C:\\\\xampp\\\\htdocs\\\\farmscout_online\\\\includes\\/..\\/logs\\/email.log\"}', '2025-09-17 08:30:15', '2025-09-20 13:14:06'),
(3, 'last_price_check', '2025-10-20 15:47:13', '2025-09-17 08:55:08', '2025-10-20 13:47:13');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `filipino_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon_path` text DEFAULT NULL,
  `price_range` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `filipino_name`, `description`, `icon_path`, `price_range`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Vegetables', 'Gulay', 'Fresh vegetables and leafy greens from local farms', 'M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z', '₱25-₱120/kg', 1, 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(2, 'Fruits', 'Prutas', 'Fresh fruits and seasonal produce from local orchards', 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.293l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z', '₱40-₱200/kg', 2, 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(3, 'Meat', 'Karne', 'Fresh meat products from local butchers', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', '₱280-₱450/kg', 3, 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(4, 'Fish', 'Isda', 'Fresh fish and seafood from local fishermen', 'M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z', '₱150-₱350/kg', 4, 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(5, 'Grains / Rice', 'Bigas / Butil', 'Rice, grains, and staple goods', 'M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z', '₱35-₱180/kg', 5, 1, '2025-09-14 16:23:28', '2026-04-26 19:06:05'),
(6, 'Poultry / Eggs', 'Manok / Itlog', 'Chicken and eggs from local farms', NULL, '₱8-₱280', 6, 1, '2026-04-26 19:06:05', '2026-04-26 19:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `farmer_applications`
--

CREATE TABLE `farmer_applications` (
  `id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `application_message` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `markets`
--

CREATE TABLE `markets` (
  `id` int(11) NOT NULL,
  `market_name` varchar(255) NOT NULL,
  `market_description` text DEFAULT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `operating_hours` text DEFAULT NULL,
  `market_type` enum('public','private','cooperative') DEFAULT 'public',
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `vendor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `opening_time` time DEFAULT '06:00:00',
  `closing_time` time DEFAULT '18:00:00',
  `is_open` tinyint(1) DEFAULT 1,
  `operating_days` varchar(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `markets`
--

INSERT INTO `markets` (`id`, `market_name`, `market_description`, `address`, `latitude`, `longitude`, `contact_number`, `email`, `operating_hours`, `market_type`, `status`, `vendor_id`, `created_at`, `updated_at`, `opening_time`, `closing_time`, `is_open`, `operating_days`) VALUES
(1, 'Balaoan Public Market', 'Main public market in Balaoan, La Union featuring fresh local produce and traditional goods.', 'Balaoan, La Union', 16.82190000, 120.40420000, '+63 917 123 4567', 'balaoanmarket@email.com', 'Monday-Sunday: 5:00 AM - 8:00 PM', 'public', 'active', 10, '2025-10-22 13:24:18', '2026-04-28 12:32:19', '06:00:00', '18:00:00', 1, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'),
(2, 'San Fernando City Market', 'Large public market in San Fernando City with diverse agricultural products and local crafts.', 'San Fernando City, La Union', 16.61590000, 120.31660000, '+63 917 234 5678', 'sfmarket@email.com', 'Monday-Sunday: 4:00 AM - 9:00 PM', 'public', 'active', 11, '2025-10-22 13:24:18', '2025-11-13 15:50:15', '06:00:00', '18:00:00', 1, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'),
(3, 'Bauang Public Market', 'Coastal market in Bauang featuring fresh seafood and local vegetables.', 'Bauang, La Union', 16.53060000, 120.33310000, '+63 917 345 6789', 'bauangmarket@email.com', 'Monday-Sunday: 5:30 AM - 7:30 PM', 'public', 'active', 12, '2025-10-22 13:24:18', '2025-11-13 15:50:15', '06:00:00', '18:00:00', 1, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'),
(4, 'San Juan Market', 'Community market in San Juan known for organic produce and local specialties.', 'San Juan, La Union', 16.66810000, 120.34420000, '+63 917 456 7890', 'sanjuanmarket@email.com', 'Tuesday-Sunday: 6:00 AM - 8:00 PM', 'public', 'active', 13, '2025-10-22 13:24:18', '2025-11-13 15:50:15', '06:00:00', '18:00:00', 1, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'),
(5, 'Agoo Market', 'Traditional market in Agoo with local farmers and artisanal products.', 'Agoo, La Union', 16.32220000, 120.36610000, '+63 917 567 8901', 'agoomarket@email.com', 'Monday-Saturday: 5:00 AM - 8:00 PM', 'public', 'active', 14, '2025-10-22 13:24:18', '2025-11-13 15:50:15', '06:00:00', '18:00:00', 1, 'Mon,Tue,Wed,Thu,Fri,Sat,Sun');

-- --------------------------------------------------------

--
-- Table structure for table `market_amenities`
--

CREATE TABLE `market_amenities` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `amenity_name` varchar(100) NOT NULL,
  `amenity_description` text DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_amenities`
--

INSERT INTO `market_amenities` (`id`, `market_id`, `amenity_name`, `amenity_description`, `is_available`) VALUES
(1, 1, 'Parking Area', 'Free parking for customers', 1),
(2, 1, 'Restrooms', 'Clean public restrooms', 1),
(3, 1, 'ATM', 'ATM machine for cash withdrawal', 1),
(4, 2, 'Parking Area', 'Paid parking available', 1),
(5, 2, 'Food Court', 'Local food vendors and seating area', 1),
(6, 2, 'WiFi', 'Free WiFi for customers', 1),
(7, 3, 'Parking Area', 'Free parking near the market', 1),
(8, 3, 'Seafood Section', 'Dedicated area for fresh seafood', 1),
(9, 4, 'Organic Section', 'Special area for organic products', 1),
(10, 4, 'Parking Area', 'Free parking available', 1),
(11, 5, 'Traditional Crafts', 'Section for local handicrafts', 1),
(12, 5, 'Parking Area', 'Free parking for customers', 1);

-- --------------------------------------------------------

--
-- Table structure for table `market_farmers`
--

CREATE TABLE `market_farmers` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `stall_number` varchar(50) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `monthly_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_farmers`
--

INSERT INTO `market_farmers` (`id`, `market_id`, `farmer_id`, `stall_number`, `approval_status`, `approved_at`, `approved_by`, `monthly_fee`, `created_at`) VALUES
(1, 1, 3, 'B-01', 'approved', '2025-10-22 13:24:18', NULL, 500.00, '2025-10-22 13:24:18'),
(2, 2, 3, 'D-01', 'approved', '2025-10-22 13:24:18', NULL, 600.00, '2025-10-22 13:24:18'),
(3, 3, 3, 'F-01', 'approved', '2025-10-22 13:24:18', NULL, 450.00, '2025-10-22 13:24:18'),
(11, 2, 11, 'MGMT', 'approved', '2025-11-13 15:50:15', NULL, 0.00, '2025-11-13 15:50:15'),
(12, 3, 12, 'MGMT', 'approved', '2025-11-13 15:50:15', NULL, 0.00, '2025-11-13 15:50:15'),
(13, 4, 13, 'MGMT', 'approved', '2025-11-13 15:50:15', NULL, 0.00, '2025-11-13 15:50:15'),
(14, 5, 14, 'MGMT', 'approved', '2025-11-13 15:50:15', NULL, 0.00, '2025-11-13 15:50:15');

-- --------------------------------------------------------

--
-- Table structure for table `market_images`
--

CREATE TABLE `market_images` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_type` enum('banner','interior','exterior','stall') DEFAULT 'interior',
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_products`
--

CREATE TABLE `market_products` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT 'per kg',
  `stock_quantity` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `product_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_products`
--

INSERT INTO `market_products` (`id`, `market_id`, `farmer_id`, `product_name`, `product_description`, `category`, `price`, `unit`, `stock_quantity`, `is_available`, `product_image`, `created_at`, `updated_at`) VALUES
(1, 1, 10, 'Tomatoes', '', 'Vegetables', 259.00, 'kg', 0, 1, 'https://upload.wikimedia.org/wikipedia/bcl/thumb/1/14/Kamatis_.jpg/1280px-Kamatis_.jpg', '2025-11-14 10:45:01', '2025-11-14 12:42:58'),
(2, 1, 10, 'Kangkong(Water Spaniah)', 'iasdjmasidjnasidjasidsjasdiasdsasdasda', 'Vegetables', 22.00, 'per kg', 0, 1, 'assets/images/products/product_69d566aa07a3f.JPG', '2026-04-07 20:18:50', '2026-04-07 20:19:07'),
(3, 1, 10, 'Repolyo(Cabbage))', 'adasdasdas', 'Vegetables', 130.00, 'per kg', 0, 1, 'assets/images/products/product_69d6232f63d66.webp', '2026-04-08 09:43:11', '2026-04-08 09:44:18'),
(4, 1, 10, 'Pulang Sibuyas(Red Onion)', 'assdsadssasdssssadsadadada', 'Vegetables', 150.00, 'per kg', 0, 1, 'assets/images/products/product_69d623434309d.jpg', '2026-04-08 09:43:31', '2026-04-28 12:31:17'),
(5, 2, 11, 'Kamatis(Tomatoes)', '', 'Fruits', 120.00, 'per kg', 0, 0, 'assets/images/products/product_69f1d27fec29c.jpg', '2026-04-29 09:42:23', '2026-04-29 14:19:43'),
(6, 2, 11, 'Banana(Saging)', 'asdasdadadasdadas', 'Vegetables', 124.00, 'per kg', 0, 0, 'assets/images/products/product_69f1d48611cd3.jpg', '2026-04-29 09:51:02', '2026-04-29 13:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `market_product_units`
--

CREATE TABLE `market_product_units` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `unit_label` varchar(100) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `price` decimal(10,2) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_product_units`
--

INSERT INTO `market_product_units` (`id`, `product_id`, `unit_label`, `quantity`, `price`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 2, 'bundle', 1.00, 22.00, 1, '2026-04-07 20:18:50', '2026-04-07 20:18:50'),
(2, 3, 'kg', 1.00, 130.00, 1, '2026-04-08 09:43:11', '2026-04-08 09:43:11'),
(3, 4, 'kg', 1.00, 150.00, 1, '2026-04-08 09:43:31', '2026-04-08 09:43:31'),
(4, 5, 'kg', 1.00, 120.00, 1, '2026-04-29 09:42:23', '2026-04-29 09:42:23'),
(7, 6, 'bundle', 1.00, 124.00, 1, '2026-04-29 13:52:03', '2026-04-29 13:52:03');

-- --------------------------------------------------------

--
-- Table structure for table `market_reviews`
--

CREATE TABLE `market_reviews` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_statistics`
--

CREATE TABLE `market_statistics` (
  `id` int(11) NOT NULL,
  `market_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_visitors` int(11) DEFAULT 0,
  `total_products` int(11) DEFAULT 0,
  `total_farmers` int(11) DEFAULT 0,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `market_stats`
--

CREATE TABLE `market_stats` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_products` int(11) DEFAULT 0,
  `active_vendors` int(11) DEFAULT 0,
  `avg_price_change` decimal(5,2) DEFAULT 0.00,
  `most_searched_product` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `market_stats`
--

INSERT INTO `market_stats` (`id`, `date`, `total_products`, `active_vendors`, `avg_price_change`, `most_searched_product`, `created_at`) VALUES
(1, '2025-09-15', 10, 5, -2.50, 'Kamatis', '2025-09-14 16:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who receives the notification',
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`, `read_at`) VALUES
(1, 10, 'new_reservation', 'New Reservation Request', 'gorr godbutcher wants to reserve 1 kg of Repolyo(Cabbage))', 'farmer-dashboard.php?section=reservations', 1, '2026-04-11 18:35:31', '2026-04-11 18:40:01'),
(2, 15, 'reservation_accepted', 'Reservation Accepted', 'Your reservation for 1.00 kg of Repolyo(Cabbage)) has been accepted!', 'user-account.php?section=reservations', 1, '2026-04-11 18:39:55', '2026-04-11 19:05:12'),
(3, 10, 'new_reservation', 'New Reservation Request', 'gorr godbutcher wants to reserve 1 kg of Pulang Sibuyas(Red Onion)', 'farmer-dashboard.php?section=reservations', 1, '2026-04-11 19:09:42', '2026-04-11 19:10:21'),
(4, 15, 'reservation_accepted', 'Reservation Accepted', 'Your reservation for 1.00 kg of Pulang Sibuyas(Red Onion) has been accepted!', 'user-account.php?section=reservations', 1, '2026-04-11 19:10:28', '2026-04-29 11:48:37'),
(5, 15, 'reservation_accepted', 'Reservation Accepted', 'Your reservation for 1.00 kg of Pulang Sibuyas(Red Onion) has been accepted!', 'user-account.php?section=reservations', 1, '2026-04-11 19:10:32', '2026-04-29 11:48:35'),
(6, 15, 'new_message', 'New Message', 'Balaoan Market Admin sent you a message about your reservation.', 'user-account.php?section=reservations', 1, '2026-04-11 19:17:58', '2026-04-29 11:48:33'),
(7, 10, 'new_reservation', 'New Reservation Request', 'gorr godbutcher wants to reserve 1 per kg of Kangkong(Water Spaniah)', 'farmer-dashboard.php?section=reservations', 0, '2026-04-28 12:49:34', NULL),
(8, 15, 'new_message', 'New Message', 'Balaoan Market Admin sent you a message about your reservation.', 'user-account.php?section=reservations', 1, '2026-04-28 14:01:44', '2026-04-29 11:48:32'),
(9, 10, 'new_reservation', 'New Reservation Request', 'gorr godbutcher wants to reserve 2 per kg of Kangkong(Water Spaniah)', 'farmer-dashboard.php?section=reservations', 0, '2026-04-28 14:07:38', NULL),
(10, 15, 'reservation_confirmed', 'Reservation Confirmed', 'Your reservation for 2.00 per kg of Kangkong(Water Spaniah) has been confirmed by the farmer.', 'user-account.php?section=reservations', 1, '2026-04-28 14:08:49', '2026-04-29 11:48:30'),
(11, 11, 'new_reservation', 'New Reservation Request', 'gorr godbutcher wants to reserve 2 per kg of Banana(Saging)', 'farmer-dashboard.php?section=reservations', 0, '2026-04-29 13:49:59', NULL),
(12, 15, 'reservation_confirmed', 'Reservation Confirmed', 'Your reservation for 2.00 per kg of Banana(Saging) has been confirmed by the farmer.', 'user-account.php?section=reservations', 0, '2026-04-29 13:50:42', NULL),
(13, 15, 'price_alert', '???? Price Alert: Banana(Saging)', 'Price changed! Now ₱122.00 / bundle (was ₱0.00)', 'market-finder.php?product_id=6', 0, '2026-04-29 13:51:36', NULL),
(14, 15, 'price_alert', '???? Price Alert: Banana(Saging)', 'Price changed! Now ₱124.00 / bundle (was ₱0.00)', 'market-finder.php?product_id=6', 0, '2026-04-29 13:52:07', NULL),
(15, 15, 'new_message', 'New Message', 'Balaoan Market Admin sent you a message about your reservation.', 'user-account.php?section=reservations', 0, '2026-04-29 14:47:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `price_alerts`
--

CREATE TABLE `price_alerts` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `product_id` int(11) NOT NULL,
  `target_price` decimal(10,2) NOT NULL,
  `alert_type` enum('below','above','change') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_sent` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `price_alerts`
--

INSERT INTO `price_alerts` (`id`, `user_email`, `product_id`, `target_price`, `alert_type`, `is_active`, `created_at`, `updated_at`, `last_sent`) VALUES
(1, 'gorrmeetthor06@gmail.com', 6, 0.00, 'change', 1, '2026-04-29 12:30:30', '2026-04-29 13:52:07', '2026-04-29 13:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `price_alert_logs`
--

CREATE TABLE `price_alert_logs` (
  `id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `triggered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `old_price` decimal(10,2) NOT NULL,
  `new_price` decimal(10,2) NOT NULL,
  `email_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `price_alert_logs`
--

INSERT INTO `price_alert_logs` (`id`, `alert_id`, `triggered_at`, `old_price`, `new_price`, `email_sent`) VALUES
(14, 1, '2026-04-29 13:51:36', 0.00, 122.00, 1),
(15, 1, '2026-04-29 13:52:07', 0.00, 124.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `price_history`
--

CREATE TABLE `price_history` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `change_type` enum('increase','decrease','stable') DEFAULT 'stable',
  `change_percentage` decimal(5,2) DEFAULT 0.00,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `filipino_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `current_price` decimal(10,2) NOT NULL,
  `previous_price` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) NOT NULL DEFAULT 'kg',
  `image_url` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_summary`
-- (See below for the actual view)
--
CREATE TABLE `product_summary` (
`id` int(11)
,`name` varchar(150)
,`filipino_name` varchar(150)
,`current_price` decimal(10,2)
,`previous_price` decimal(10,2)
,`unit` varchar(20)
,`is_featured` tinyint(1)
,`category_name` varchar(100)
,`category_filipino` varchar(100)
,`price_change_percentage` decimal(17,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Consumer who made the reservation',
  `farmer_id` int(11) NOT NULL COMMENT 'Farmer who owns the product',
  `product_id` int(11) NOT NULL COMMENT 'Product being reserved',
  `market_id` int(11) NOT NULL COMMENT 'Market where product is sold',
  `quantity` decimal(10,2) NOT NULL COMMENT 'Quantity requested (e.g., 5.00)',
  `unit` varchar(50) DEFAULT NULL COMMENT 'Unit of measurement (e.g., kg, piece)',
  `preferred_pickup_date` date DEFAULT NULL COMMENT 'Preferred date for pickup',
  `preferred_pickup_time` time DEFAULT NULL COMMENT 'Preferred time for pickup',
  `notes` text DEFAULT NULL COMMENT 'Optional message from user',
  `payment_method` enum('cash','gcash') DEFAULT NULL,
  `gcash_reference` varchar(120) DEFAULT NULL,
  `status` enum('pending','confirmed','paid','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted_at` timestamp NULL DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = archived (hidden from user view)',
  `hide_from_chat_user` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'hidden from chat by user',
  `hide_from_chat_farmer` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'hidden from chat by farmer',
  `declined_at` timestamp NULL DEFAULT NULL,
  `decline_reason` text DEFAULT NULL COMMENT 'Farmer reason when declining',
  `accept_note` text DEFAULT NULL COMMENT 'Farmer note when accepting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `farmer_id`, `product_id`, `market_id`, `quantity`, `unit`, `preferred_pickup_date`, `preferred_pickup_time`, `notes`, `payment_method`, `gcash_reference`, `status`, `created_at`, `updated_at`, `accepted_at`, `confirmed_at`, `paid_at`, `completed_at`, `is_archived`, `hide_from_chat_user`, `hide_from_chat_farmer`, `declined_at`, `decline_reason`, `accept_note`) VALUES
(1, 15, 10, 3, 1, 1.00, 'kg', '2026-04-12', '10:35:00', NULL, NULL, NULL, '', '2026-04-11 18:35:23', '2026-04-27 09:40:08', '2026-04-11 18:39:52', NULL, NULL, NULL, 0, 0, 0, NULL, NULL, NULL),
(2, 15, 10, 4, 1, 1.00, 'kg', '2026-04-12', '09:09:00', NULL, NULL, NULL, '', '2026-04-11 19:09:34', '2026-04-27 10:41:57', '2026-04-11 19:10:28', NULL, NULL, NULL, 1, 0, 0, NULL, NULL, NULL),
(3, 15, 10, 2, 1, 1.00, 'per kg', '2026-04-28', '02:48:00', NULL, 'cash', NULL, 'cancelled', '2026-04-28 12:49:26', '2026-04-28 14:06:52', NULL, '2026-04-28 12:49:41', NULL, NULL, 0, 0, 0, NULL, NULL, NULL),
(4, 15, 10, 2, 1, 2.00, 'per kg', '2026-04-29', '12:09:00', NULL, 'cash', NULL, 'cancelled', '2026-04-28 14:07:30', '2026-04-29 13:38:46', NULL, '2026-04-28 14:08:45', NULL, NULL, 1, 0, 0, NULL, NULL, ''),
(5, 15, 11, 6, 2, 2.00, 'per kg', '2026-04-30', '12:49:00', NULL, 'cash', NULL, 'confirmed', '2026-04-29 13:49:50', '2026-04-29 13:50:38', NULL, '2026-04-29 13:50:38', NULL, NULL, 0, 0, 0, NULL, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_messages`
--

CREATE TABLE `reservation_messages` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL COMMENT 'Reservation this message belongs to',
  `sender_id` int(11) NOT NULL COMMENT 'User who sent the message',
  `message_type` enum('user','farmer','system') NOT NULL DEFAULT 'user' COMMENT 'message origin',
  `message_body` text NOT NULL COMMENT 'Message content',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'When the recipient read the message',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservation_messages`
--

INSERT INTO `reservation_messages` (`id`, `reservation_id`, `sender_id`, `message_type`, `message_body`, `read_at`, `created_at`) VALUES
(1, 2, 10, 'farmer', 'hello', '2026-04-11 19:18:24', '2026-04-11 19:17:58'),
(2, 3, 10, 'system', 'You reserved 1 per kg of Kangkong(Water Spaniah)', NULL, '2026-04-28 12:49:34'),
(3, 3, 10, 'farmer', 'hello', '2026-04-28 14:01:58', '2026-04-28 14:01:44'),
(4, 3, 10, 'system', 'You cancelled this reservation', NULL, '2026-04-28 14:06:52'),
(5, 4, 10, 'system', 'You reserved 2 per kg of Kangkong(Water Spaniah)', NULL, '2026-04-28 14:07:38'),
(6, 4, 10, 'system', 'Reservation confirmed', NULL, '2026-04-28 14:08:49'),
(7, 4, 10, 'system', 'You cancelled this reservation', NULL, '2026-04-29 13:38:44'),
(8, 5, 11, 'system', 'You reserved 2 per kg of Banana(Saging)', NULL, '2026-04-29 13:49:59'),
(9, 5, 11, 'system', 'Reservation confirmed', NULL, '2026-04-29 13:50:42'),
(10, 4, 10, 'farmer', 'hello kiddo', '2026-04-29 14:48:30', '2026-04-29 14:47:55');

-- --------------------------------------------------------

--
-- Table structure for table `shopping_lists`
--

CREATE TABLE `shopping_lists` (
  `id` int(11) NOT NULL,
  `user_session` varchar(100) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `typing_status`
--

CREATE TABLE `typing_status` (
  `id` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL COMMENT 'Reservation/conversation ID',
  `user_id` int(11) NOT NULL COMMENT 'User who is typing',
  `is_typing` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = typing',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last activity'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `typing_status`
--

INSERT INTO `typing_status` (`id`, `reservation_id`, `user_id`, `is_typing`, `last_activity`) VALUES
(13, 4, 15, 1, '2026-04-29 14:48:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','vendor','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `verification_token` varchar(64) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `google_id` varchar(255) DEFAULT NULL,
  `profile_picture` text DEFAULT NULL,
  `login_method` enum('email','google') DEFAULT 'email',
  `is_verified` tinyint(1) DEFAULT 0,
  `password_reset_token` varchar(64) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `user_role` varchar(50) DEFAULT 'consumer',
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email_notifications` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `verification_token`, `last_login`, `created_at`, `updated_at`, `google_id`, `profile_picture`, `login_method`, `is_verified`, `password_reset_token`, `password_reset_expires`, `user_role`, `phone_number`, `address`, `profile_image`, `email_notifications`) VALUES
(3, 'chillguy06', 'wompskiwompwomp@gmail.com', '$2y$10$dC98dmOiFcuvRl9KAyNVMuq3Gs5Tnacg5IWIwhIt9azRZVahDvEpy', 'Jordan Fry', 'admin', 1, NULL, '2025-11-01 05:41:55', '2025-09-20 14:04:51', '2025-11-01 05:41:55', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(10, 'balaoan_manager', 'momohowu06@gmail.com', '$2y$10$OTKE99052KN9LOUQD0Un5OLY.IyKqFxTljB2KC.sW6rO.5VTMNRrq', 'Balaoan Market Admin', 'user', 1, NULL, '2026-04-29 14:47:25', '2025-11-13 13:32:52', '2026-04-29 14:47:25', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(11, 'sanfernando_manager', 'sanfernando.manager@example.com', '$2y$10$8X9dMMiYYF23VDBK5tpiieXRj5R0pIR9tJltu8cMewa7ff7aban9K', 'San Fernando Market Admin', 'user', 1, NULL, '2026-04-29 13:50:30', '2025-11-13 15:50:15', '2026-04-29 13:50:30', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(12, 'bauang_manager', 'bauang.manager@example.com', '$2y$10$NpvtTidFQUM7MDk3n.DiSOPPN3JGkOJQdspx7I1Leptqf1iGMCtg.', 'Bauang Market Admin', 'user', 1, NULL, NULL, '2025-11-13 15:50:15', '2025-11-13 15:50:15', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(13, 'sanjuan_manager', 'sanjuan.manager@example.com', '$2y$10$PROc/f7ag1LsZ8aU//cA8exBstGA9tkqXG87k9RbtssIjweTxVrCO', 'San Juan Market Admin', 'user', 1, NULL, NULL, '2025-11-13 15:50:15', '2025-11-13 15:50:15', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(14, 'agoo_manager', 'agoo.manager@example.com', '$2y$10$YrDj2YCYpCIhk9omNbVouOJztAnP6d.SPbSUHZZcfleUOf6x2dnmS', 'Agoo Market Admin', 'user', 1, NULL, NULL, '2025-11-13 15:50:15', '2025-11-13 15:50:15', NULL, NULL, 'email', 0, NULL, NULL, 'farmer', NULL, NULL, NULL, 1),
(15, 'gorrgodbutcher', 'gorrmeetthor06@gmail.com', '$2y$10$6zKghPSMekTlfJ5tx2Pzve6epa3YD7enrID7.puXs8Y4Yxd8Cr1hi', 'gorr godbutcher', 'user', 1, NULL, NULL, '2026-04-07 20:26:57', '2026-04-29 14:48:14', '113425104308483011224', 'https://lh3.googleusercontent.com/a/ACg8ocI6bVKQHAExpNk__HTcFg4iy3BpGfkpRg8mm3WY90SvNsPAQA=s96-c', 'google', 1, NULL, NULL, 'consumer', NULL, NULL, NULL, 1),
(16, 'dti_test_user', 'dti_test_user@example.com', '$2y$10$o/U3L8Hc6yduzbZcmcuQ1eAkcEwUfMfr4i1ZUuWkEBi9mP65QTG46', 'DTI Test Admin', 'admin', 1, NULL, NULL, '2026-04-11 17:48:52', '2026-04-11 17:48:52', NULL, NULL, 'email', 1, NULL, NULL, 'admin', NULL, NULL, NULL, 1),
(17, 'kiel2026', 'kiel2026@example.com', '$2y$10$8kiMzAIMXarcmiE7MRLLBeHUBlEqdC8Oz6SFAdqaQOSqSleVb6Lye', 'Kiel Ordinario', 'admin', 1, NULL, '2026-04-11 17:59:30', '2026-04-11 17:57:36', '2026-04-11 17:59:30', NULL, NULL, 'email', 1, NULL, NULL, 'admin', NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'consumer', 'Regular users who browse and buy products', '2025-10-22 13:23:02'),
(2, 'farmer', 'Users who grow/sell products at markets', '2025-10-22 13:23:02'),
(3, 'vendor', 'Market owners/managers who manage market facilities', '2025-10-22 13:23:02'),
(11, 'admin', 'DTI Admin — Department of Trade and Industry; platform oversight and approvals', '2026-04-11 17:53:06'),
(12, 'super_admin', 'Super Admin — full platform control, including creating and managing DTI Admin accounts', '2026-04-11 17:53:06');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `page_views` int(11) DEFAULT 0,
  `search_queries` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `session_id`, `ip_address`, `user_agent`, `page_views`, `search_queries`, `created_at`, `last_activity`) VALUES
(1, 'thqle8km2o0hpeck4mfh9vjjkc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 14, 0, '2025-09-14 16:27:56', '2025-09-14 16:38:09'),
(2, '6re6vu1jeqjsl6n0vteocqle7d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-14 16:39:46', '2025-09-14 16:39:46'),
(3, 'ape58a6gqclmee9jb9m6sm7aqd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 16, 0, '2025-09-14 16:39:58', '2025-09-14 17:01:15'),
(4, 'avep23j9u7rrepg056i9ok5pkm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 2, 0, '2025-09-14 17:01:23', '2025-09-14 17:02:36'),
(5, 'idhcj1n8ghbol2fka11qp13hkk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 7, 0, '2025-09-15 05:27:33', '2025-09-15 05:27:46'),
(6, 'qibfmbma4t1cj0joeji3ieg4n4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 25, 0, '2025-09-15 05:28:43', '2025-09-15 09:36:54'),
(7, '8oi0mbcol9a15a655ov84a2pbe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 19, 0, '2025-09-15 17:31:07', '2025-09-15 18:43:40'),
(8, 'aqu6qcksrr5fco6urhlu41dh29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-15 18:51:34', '2025-09-15 18:51:34'),
(9, 'l4nns93uv6a5pfbplpjmv6v0oa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 2, 0, '2025-09-15 18:52:56', '2025-09-15 18:53:17'),
(10, '863bpttr1vn2j1k80urujivkuq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 94, 0, '2025-09-15 18:55:19', '2025-09-15 19:46:58'),
(11, 'v18ps397daioic7pe0u87ukj50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 66, 0, '2025-09-15 19:47:20', '2025-09-15 20:23:16'),
(12, '7pkiqrtoa0htiqdhsbt3emoj3j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 155, 0, '2025-09-16 05:00:04', '2025-09-16 07:21:18'),
(13, 'a5hlp7d2c7atel1j49aep66km4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 18, 0, '2025-09-16 07:22:26', '2025-09-16 07:26:38'),
(14, '3m2hijfn54qce61sfkdab6t6i5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 201, 0, '2025-09-16 07:26:58', '2025-09-16 10:16:25'),
(15, '52iemb9l4rjtce3pvrm1m2krbs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 8, 0, '2025-09-16 10:31:13', '2025-09-16 10:32:14'),
(16, 'u95tjhn42arl9jdpltrpf70f2j', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 9, 0, '2025-09-16 10:34:37', '2025-09-16 10:35:23'),
(17, '14405lr9ohcva0nhjg1aovfa6f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 70, 1, '2025-09-16 10:44:18', '2025-09-16 12:08:48'),
(18, 'o9nou34t2dkh369rs7721r96eu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 31, 0, '2025-09-16 12:33:21', '2025-09-16 12:44:46'),
(19, 'siaug9lghtmbp6suo6f8e11j0m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 19, 0, '2025-09-16 12:54:38', '2025-09-16 12:58:40'),
(20, 'c5qhfk9pdv8mc3d35657thotva', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 4, 0, '2025-09-16 12:58:57', '2025-09-16 13:00:49'),
(21, 'k72l3plq6h9lg8b1n0r3u8vd5g', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 18, 0, '2025-09-16 13:05:41', '2025-09-16 13:11:43'),
(22, 'sefv9qfpmncmuv8g4u34dp8clu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 21, 0, '2025-09-16 13:14:41', '2025-09-16 13:17:49'),
(23, 's0j7v15j15u9m0ibu14qc0cm93', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 21, 0, '2025-09-16 13:21:20', '2025-09-16 13:24:30'),
(24, 'fefukf57r8ojkdpuhmfpe1gcup', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-16 16:48:11', '2025-09-16 16:48:11'),
(25, 'r9hssu7mc5gc5b4k1cdceg028i', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-17 07:35:42', '2025-09-17 07:35:42'),
(26, 'lp705pmrr8v12gb3pfn6mtq869', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 107, 0, '2025-09-17 07:40:05', '2025-09-17 10:56:24'),
(27, 'vrr2ljuvs775ekeit8pi2jh1sk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 162, 0, '2025-09-17 10:56:49', '2025-09-17 14:22:37'),
(28, '99va15itroh8ioh8k2orob88mm', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.19041.6328', 1, 0, '2025-09-17 11:16:19', '2025-09-17 11:16:19'),
(29, 'tr3ndd1hgo4ivfqjbtrirlii4o', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.19041.6328', 1, 0, '2025-09-17 11:27:16', '2025-09-17 11:27:16'),
(30, 'l6k6773d19ab94gbl8joso1qd2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 29, 0, '2025-09-17 14:23:16', '2025-09-17 14:33:08'),
(31, 'bhb2uj9mdo1jnogdr21fp4lb63', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 116, 0, '2025-09-17 14:36:51', '2025-09-17 17:59:27'),
(32, '6tr4vct93gq03r5jl2sje7a9f9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-17 18:08:56', '2025-09-17 18:08:56'),
(33, 'hrsvq13321qmh2ib6idj2eje33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-17 18:14:36', '2025-09-17 18:14:36'),
(34, '50g6mr7rk59b9p85j2k1bc8q0e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 12, 0, '2025-09-17 18:15:40', '2025-09-17 18:48:46'),
(35, 'a2tkl5mrnd2a22qop71frola0o', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 12, 0, '2025-09-17 18:48:56', '2025-09-17 18:58:17'),
(36, 'cjd4oihsucb25jau9aejgprc6q', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 6, 0, '2025-09-17 18:58:22', '2025-09-17 18:58:35'),
(37, 'pj93l4bnpf4bb17cuns5oj21o9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 24, 0, '2025-09-18 05:38:27', '2025-09-18 05:44:37'),
(38, 'o7hnhqqte1snpefriqlfqk0qf1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 52, 1, '2025-09-18 19:37:48', '2025-09-18 19:55:50'),
(39, 'v5q3p6q0vv1q224g0pcqmc8p0b', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 6, 0, '2025-09-19 03:11:00', '2025-09-19 04:09:01'),
(40, 'ef14d6e7rnrbkdqlbldn3t2v9h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 11, 0, '2025-09-19 04:09:08', '2025-09-19 04:09:32'),
(41, '4actgjegf1q29qqldule1f89el', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 22, 0, '2025-09-20 02:35:23', '2025-09-20 04:55:55'),
(42, '4lhtcmptlfamolcspiu5e3tffs', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 44, 0, '2025-09-20 12:51:57', '2025-09-20 14:08:02'),
(43, 'b2qaerttjupvhe53mi2t8qqepm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 82, 0, '2025-09-21 12:36:24', '2025-09-21 13:05:16'),
(44, 'o9nerq0e83731t03mj07bbkvhi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 14, 0, '2025-09-21 13:07:45', '2025-09-21 14:17:06'),
(45, '394rakuhi51b5s8235aove6o4l', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 3, 0, '2025-09-21 14:29:56', '2025-09-21 14:30:37'),
(46, 'e1vj15kss46njcebnrsgbqmvq6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 17, 0, '2025-09-21 14:30:50', '2025-09-21 17:35:15'),
(47, '7m8pgkeigrvo8el2cscptvgm5u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-21 17:43:35', '2025-09-21 17:43:35'),
(48, 'hefoj1gsm1sbga3ngdv1guvtas', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-21 17:44:43', '2025-09-21 17:44:43'),
(49, 'aogcuvdkhcpdnfn4bn95df1kt1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 2, 0, '2025-09-21 17:45:26', '2025-09-21 18:05:58'),
(50, 'gn1vi5ckv0cc9o16s5btj0clok', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-21 18:06:12', '2025-09-21 18:06:12'),
(51, 'de871jjktdi7mblq661093oqqd', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-21 18:07:08', '2025-09-21 18:07:08'),
(52, '7snn1sbsvgm8iarhujl8slotpu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-21 18:10:49', '2025-09-21 18:10:49'),
(53, 'n63mjefj2q9s4571o2f3q5gasf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 51, 0, '2025-09-22 08:38:24', '2025-09-22 16:02:57'),
(54, 'ihn7b5g0jadrsm6382leb9b71t', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-09-22 16:35:30', '2025-09-22 16:35:30'),
(55, 'iopbvpc44lflnn2h2n6lqdl78k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 56, 0, '2025-09-22 18:04:49', '2025-09-22 20:15:08'),
(56, '5n5hrem8rk0kkk5hm6sn23srb2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 25, 0, '2025-09-23 11:30:15', '2025-09-23 15:59:41'),
(57, '2vhtop05r7a3045k6iltkp3pnr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 3, 0, '2025-09-24 21:30:43', '2025-09-24 21:39:46'),
(58, 'spgi38jqfenub4agh1u07a1f9m', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 2, 0, '2025-09-25 20:18:55', '2025-09-25 20:18:56'),
(59, 'fsp9u499qhoc88jv5obsfop81s', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 8, 0, '2025-09-26 08:30:05', '2025-09-26 08:30:22'),
(60, 'gmjkh4gpn74cvdamesk9oqji4k', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 9, 0, '2025-09-26 11:34:07', '2025-09-26 11:36:23'),
(61, 'rgsl2v7mrfi973o5dfkmbtdg3u', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 11, 0, '2025-09-26 11:37:09', '2025-09-26 11:37:51'),
(62, '3pvi6e101jm5nv2039nabhmjg2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 11, 0, '2025-10-04 22:16:03', '2025-10-04 22:16:32'),
(63, '7i0s2j3lrltg0l20q2c94qklon', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 5, 0, '2025-10-04 23:15:14', '2025-10-04 23:15:42'),
(64, '1oqim8at7h9rbactr9be3ohdbo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 12, 0, '2025-10-04 23:15:45', '2025-10-04 23:15:55'),
(65, 'emupo0qdr2nl5eg95ii27kvrmr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 9, 0, '2025-10-05 16:44:55', '2025-10-05 16:45:17'),
(66, 'vhr0i0e2e898m2vrjm7eqq48fa', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 1, 0, '2025-10-06 13:10:03', '2025-10-06 13:10:03'),
(67, '2f382qts9sjqfihvbuhl5im79e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 5, 0, '2025-10-07 13:07:41', '2025-10-07 13:07:49'),
(68, 'fnm5belefh6o46d2re03as256h', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 14, 0, '2025-10-08 08:04:16', '2025-10-08 08:27:58'),
(69, '9gvoffunvjt4dsf640av873j31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-09 06:37:41', '2025-10-09 06:37:50'),
(70, 'monbk5f91c94k3jl83m8ii87sj', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-09 06:39:43', '2025-10-09 06:40:39'),
(71, 'hh5d1i3vepv94a6lsa14ug14qb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 89, 0, '2025-10-09 06:40:46', '2025-10-09 07:57:53'),
(72, '78cjvn0e186gr86rcft3pbdh57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 6, 0, '2025-10-09 08:01:08', '2025-10-09 08:04:43'),
(73, '8t20p3ir3urqbq4o3bl944nrqp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 11, 0, '2025-10-09 08:04:49', '2025-10-09 08:53:55'),
(74, 'rnnf0ddbcg2l22vmhj2jbsvmr1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 9, 0, '2025-10-09 09:04:11', '2025-10-09 09:05:37'),
(75, '5qk2s592o0t8j6djfp4okbib73', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, 0, '2025-10-09 09:40:40', '2025-10-09 09:40:40'),
(76, 'nsnto4e0k1itbel16l7vpj8doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 4, 0, '2025-10-09 09:43:47', '2025-10-09 09:49:45'),
(77, 'f3ebrm86q206la62bnh65mggad', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-09 09:50:24', '2025-10-09 09:50:26'),
(78, 'ee8og7pknqita21k7375ph7e82', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 26, 0, '2025-10-09 09:57:43', '2025-10-09 10:13:48'),
(79, 'togoq9cj0v3ncu9ffv8am6rggq', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 4, 0, '2025-10-09 10:13:51', '2025-10-09 10:14:53'),
(80, 'vg7nhgfte5f60g267gcrsgo3ts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 21, 0, '2025-10-09 10:15:09', '2025-10-09 18:19:38'),
(81, '3grv17ftvpcbij10fa70tidteo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, 0, '2025-10-10 05:59:29', '2025-10-10 05:59:29'),
(82, 'jijrrhpm3c2m9lahsh7e04hjap', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 11, 0, '2025-10-10 18:33:18', '2025-10-10 18:35:39'),
(83, 'ckc7pja5ab7ee680rn85rddaht', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 11, 0, '2025-10-10 19:18:12', '2025-10-10 19:56:39'),
(84, '45dpg0u18ajhc9nf4e1p0qbdo8', '::1', 'Mozilla/5.0 (Windows NT; Windows NT 10.0; en-PH) WindowsPowerShell/5.1.19041.6328', 1, 0, '2025-10-10 19:33:07', '2025-10-10 19:33:07'),
(85, 'g3t9rcq7137rcg3af3fnh1r0k7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 9, 0, '2025-10-10 19:56:48', '2025-10-10 20:10:43'),
(86, 'uc2lg5j6nrgk3h9ajijbq3i62o', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 6, 0, '2025-10-10 20:11:03', '2025-10-10 20:22:03'),
(87, 'llq633kh4v6eh48h8hq56ms6la', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 12, 0, '2025-10-10 20:25:58', '2025-10-10 21:31:53'),
(88, '2ipfns0lasfi3ea9cu269boc44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 6, 0, '2025-10-10 22:14:19', '2025-10-10 22:22:14'),
(89, 'rkmbntctva73j2icpkm0la11l6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 5, 0, '2025-10-10 22:22:49', '2025-10-10 22:58:04'),
(90, '9pc68fsl0mgi64h25f4tnqomgc', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 20, 0, '2025-10-10 22:58:29', '2025-10-11 01:00:05'),
(91, 'bj72odsrnqpgmjekae514igm8o', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-12 22:11:27', '2025-10-12 22:11:30'),
(92, 't5rfk5i19nl1psap6nqdst29l2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, 0, '2025-10-13 04:16:35', '2025-10-13 04:16:35'),
(93, '3ivoa3s4qc9kudhdqbkekunn4i', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 34, 0, '2025-10-13 20:09:43', '2025-10-13 21:07:57'),
(94, '8ha4jgjked8d9dj46v4ask6mfo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 1, 0, '2025-10-15 05:28:16', '2025-10-15 05:28:16'),
(95, 'gkkru39egabdqr3mb7imrm9h24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-17 07:11:27', '2025-10-17 07:11:28'),
(96, 'lrk5qotll3dlinivafcegggrbb', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 22, 0, '2025-10-20 10:09:58', '2025-10-20 13:26:05'),
(97, '51u5lgrd0lqgkvo4g0s0moa1c8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 14, 0, '2025-10-20 13:26:46', '2025-10-20 13:48:17'),
(98, 'o1lgqhs708djm2amkejih0j8lf', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 24, 0, '2025-10-20 13:48:23', '2025-10-20 13:49:52'),
(99, '2hoe5b98pgh1j3mr6hc36acvb1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 7, 0, '2025-10-22 08:14:35', '2025-10-22 08:15:36'),
(100, 'i5gkhi70pcjl1tbo79ag6c34qv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 26, 0, '2025-10-22 08:15:56', '2025-10-22 14:04:06'),
(101, '9c83fi4cf9sooe9ndiiu73h8bk', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 25, 0, '2025-10-22 14:38:47', '2025-10-22 15:07:25'),
(102, 'lopftt8ml8bdadcasu0a9ejouv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-22 15:09:45', '2025-10-22 15:12:33'),
(103, 'fls0o6119hvbi4a59sv393tcmu', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 9, 0, '2025-10-29 17:30:56', '2025-10-29 17:39:53'),
(104, '24f428badneecnslot9cd0fiqr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 14, 0, '2025-10-30 05:03:07', '2025-10-30 05:14:54'),
(105, 'mtp01sao38kpg99kec576cp41e', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 5, 0, '2025-10-30 05:23:33', '2025-10-30 05:28:02'),
(106, '3r01cibdlfembe93vcqb4267f5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 5, 0, '2025-10-30 05:28:30', '2025-10-30 05:33:56'),
(107, 'gq292k0l1pd8vcn4u910dglmgr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 2, 0, '2025-10-30 11:57:19', '2025-10-30 11:57:35'),
(108, 'r28ap9ga7gdna6f8apjo3lo4hp', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 5, 0, '2025-10-31 12:28:23', '2025-10-31 12:28:52'),
(109, 'pgurqj5ma3q908lt5b1srg5qcv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 5, 0, '2025-11-01 05:41:15', '2025-11-01 05:42:26'),
(110, '4po9l4i3ntkp379qud58olsbd3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 9, 0, '2025-11-12 01:18:15', '2025-11-12 01:18:54'),
(111, 'rejc07rk08sioec85066igpo51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 9, 0, '2025-11-12 15:11:58', '2025-11-12 18:33:23'),
(112, '98mol0oliag132sgdo9n1uhjdl', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 4, 0, '2025-11-13 04:30:15', '2025-11-13 07:39:14'),
(113, '69e9gi5k9hedpo0dt330prl0ve', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 2, 0, '2025-11-13 10:19:53', '2025-11-13 10:20:03'),
(114, 'g4lqihlqsu87e9ls4kr9r3br3l', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 1, 0, '2025-11-13 15:43:28', '2025-11-13 15:43:28'),
(115, 'b63kg1b8hafsauthgsg2d4hdqn', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 4, 0, '2025-11-13 15:50:55', '2025-11-13 15:55:24'),
(116, 's6pe4pmu1blpgde5cfs85p8cv7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 5, 0, '2025-11-13 16:20:46', '2025-11-13 16:21:19'),
(117, 'a4r51muv6knbc9b61homu97b2i', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 3, 0, '2025-11-14 05:32:07', '2025-11-14 05:32:15'),
(118, 'dtdmpovjbib5os2cevigl2csr6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 75, 0, '2025-11-14 06:07:46', '2025-11-14 11:44:46'),
(119, 'nmhcdjroer7ksptheeu2abbc82', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 7, 0, '2025-11-14 11:45:45', '2025-11-14 12:42:58'),
(120, '9ij5l9l6f9mqt94a4db76u0jlv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 0, '2026-04-11 18:51:17', '2026-04-11 18:51:17'),
(121, 'st3eb9er0itlfak49el8u52elm', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 1, 0, '2026-04-16 14:15:38', '2026-04-16 14:15:38'),
(122, 'ni40h9tt0rjodvmfhsj0tnliol', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 2, 0, '2026-04-27 10:30:31', '2026-04-27 10:30:41'),
(123, 'gmtba66nhsfd74rv1qrjm5oam4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 3, 0, '2026-04-29 11:49:06', '2026-04-29 11:51:04');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `stall_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `specialties` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `email`, `stall_number`, `address`, `specialties`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Aling Maria\'s Vegetables', 'Maria Santos', '09123456789', 'maria@example.com', 'A-12', 'Stall A-12, Baloan Public Market', 'Fresh vegetables, leafy greens', 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(2, 'Kuya Ben\'s Fish Stall', 'Benjamin Cruz', '09234567890', 'ben@example.com', 'B-05', 'Stall B-05, Baloan Public Market', 'Fresh fish, seafood', 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(3, 'Tita Rosa\'s Meat Shop', 'Rosa Garcia', '09345678901', 'rosa@example.com', 'C-18', 'Stall C-18, Baloan Public Market', 'Fresh meat, poultry', 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(4, 'Mang Pedro\'s Fruits', 'Pedro Reyes', '09456789012', 'pedro@example.com', 'A-22', 'Stall A-22, Baloan Public Market', 'Fresh fruits, seasonal produce', 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28'),
(5, 'Sari-Sari Store ni Aling Carmen', 'Carmen Lopez', '09567890123', 'carmen@example.com', 'D-08', 'Stall D-08, Baloan Public Market', 'Processed goods, pantry items', 1, '2025-09-14 16:23:28', '2025-09-14 16:23:28');

-- --------------------------------------------------------

--
-- Structure for view `product_summary`
--
DROP TABLE IF EXISTS `product_summary`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `product_summary`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`filipino_name` AS `filipino_name`, `p`.`current_price` AS `current_price`, `p`.`previous_price` AS `previous_price`, `p`.`unit` AS `unit`, `p`.`is_featured` AS `is_featured`, `c`.`name` AS `category_name`, `c`.`filipino_name` AS `category_filipino`, CASE WHEN `p`.`previous_price` = 0 THEN 0 ELSE round((`p`.`current_price` - `p`.`previous_price`) / `p`.`previous_price` * 100,2) END AS `price_change_percentage` FROM (`products` `p` left join `categories` `c` on(`p`.`category_id` = `c`.`id`)) WHERE `p`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_target_type` (`target_type`),
  ADD KEY `idx_target_id` (`target_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `farmer_applications`
--
ALTER TABLE `farmer_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `farmer_id` (`farmer_id`),
  ADD KEY `market_id` (`market_id`);

--
-- Indexes for table `markets`
--
ALTER TABLE `markets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `market_amenities`
--
ALTER TABLE `market_amenities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `market_id` (`market_id`);

--
-- Indexes for table `market_farmers`
--
ALTER TABLE `market_farmers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_market_farmer` (`market_id`,`farmer_id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `market_images`
--
ALTER TABLE `market_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `market_id` (`market_id`);

--
-- Indexes for table `market_products`
--
ALTER TABLE `market_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `market_id` (`market_id`),
  ADD KEY `farmer_id` (`farmer_id`);

--
-- Indexes for table `market_product_units`
--
ALTER TABLE `market_product_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_units_product` (`product_id`),
  ADD KEY `idx_product_units_default` (`is_default`);

--
-- Indexes for table `market_reviews`
--
ALTER TABLE `market_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `market_id` (`market_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `market_statistics`
--
ALTER TABLE `market_statistics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_market_date` (`market_id`,`date`);

--
-- Indexes for table `market_stats`
--
ALTER TABLE `market_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `price_alerts`
--
ALTER TABLE `price_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`user_email`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `price_alert_logs`
--
ALTER TABLE `price_alert_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_id` (`alert_id`),
  ADD KEY `idx_triggered_at` (`triggered_at`);

--
-- Indexes for table `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recorded_by` (`recorded_by`),
  ADD KEY `idx_product_date` (`product_id`,`recorded_at`),
  ADD KEY `idx_change_type` (`change_type`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_price` (`current_price`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_search` (`name`,`filipino_name`,`description`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_token` (`user_id`,`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `market_id` (`market_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_farmer_id` (`farmer_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_archived` (`is_archived`);

--
-- Indexes for table `reservation_messages`
--
ALTER TABLE `reservation_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reservation_id` (`reservation_id`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_read_at` (`read_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `shopping_lists`
--
ALTER TABLE `shopping_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`user_session`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `typing_status`
--
ALTER TABLE `typing_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_reservation` (`reservation_id`,`user_id`),
  ADD KEY `idx_reservation_id` (`reservation_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_verification_token` (`verification_token`),
  ADD KEY `idx_password_reset_token` (`password_reset_token`),
  ADD KEY `idx_password_reset_expires` (`password_reset_expires`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stall` (`stall_number`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `app_config`
--
ALTER TABLE `app_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `farmer_applications`
--
ALTER TABLE `farmer_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `markets`
--
ALTER TABLE `markets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `market_amenities`
--
ALTER TABLE `market_amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `market_farmers`
--
ALTER TABLE `market_farmers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `market_images`
--
ALTER TABLE `market_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_products`
--
ALTER TABLE `market_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `market_product_units`
--
ALTER TABLE `market_product_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `market_reviews`
--
ALTER TABLE `market_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `market_statistics`
--
ALTER TABLE `market_statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `market_stats`
--
ALTER TABLE `market_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `price_alerts`
--
ALTER TABLE `price_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `price_alert_logs`
--
ALTER TABLE `price_alert_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `price_history`
--
ALTER TABLE `price_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reservation_messages`
--
ALTER TABLE `reservation_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `shopping_lists`
--
ALTER TABLE `shopping_lists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `typing_status`
--
ALTER TABLE `typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `fk_admin_logs_user` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `farmer_applications`
--
ALTER TABLE `farmer_applications`
  ADD CONSTRAINT `farmer_applications_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `farmer_applications_ibfk_2` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_amenities`
--
ALTER TABLE `market_amenities`
  ADD CONSTRAINT `market_amenities_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_farmers`
--
ALTER TABLE `market_farmers`
  ADD CONSTRAINT `market_farmers_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `market_farmers_ibfk_2` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_images`
--
ALTER TABLE `market_images`
  ADD CONSTRAINT `market_images_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_products`
--
ALTER TABLE `market_products`
  ADD CONSTRAINT `market_products_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `market_products_ibfk_2` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_product_units`
--
ALTER TABLE `market_product_units`
  ADD CONSTRAINT `fk_market_product_units_product` FOREIGN KEY (`product_id`) REFERENCES `market_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_reviews`
--
ALTER TABLE `market_reviews`
  ADD CONSTRAINT `market_reviews_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `market_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `market_statistics`
--
ALTER TABLE `market_statistics`
  ADD CONSTRAINT `market_statistics_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_alert_logs`
--
ALTER TABLE `price_alert_logs`
  ADD CONSTRAINT `price_alert_logs_ibfk_1` FOREIGN KEY (`alert_id`) REFERENCES `price_alerts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_history`
--
ALTER TABLE `price_history`
  ADD CONSTRAINT `price_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `price_history_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `market_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_4` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_messages`
--
ALTER TABLE `reservation_messages`
  ADD CONSTRAINT `fk_rm_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shopping_lists`
--
ALTER TABLE `shopping_lists`
  ADD CONSTRAINT `shopping_lists_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `typing_status`
--
ALTER TABLE `typing_status`
  ADD CONSTRAINT `fk_typing_res` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
