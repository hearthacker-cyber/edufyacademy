-- =====================================================
-- NEW DATABASE TABLES FOR LMS PROJECT
-- =====================================================
-- IMPORTANT: These are NEW tables only. No existing tables are modified.
-- Execute these statements in order.
-- =====================================================

-- =====================================================
-- TABLE 1: user_certificates
-- Purpose: Store certificate metadata for completed courses
-- Requirement: #13 - Certificates repository access
-- =====================================================

CREATE TABLE IF NOT EXISTS `user_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `certificate_url` varchar(500) DEFAULT NULL,
  `certificate_code` varchar(50) DEFAULT NULL,
  `issued_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` datetime DEFAULT NULL,
  `status` enum('pending','issued','expired') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_enrollment_id` (`enrollment_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE 2: user_points
-- Purpose: Points system for future discount redemption
-- Requirement: #14 - Points system
-- =====================================================

CREATE TABLE IF NOT EXISTS `user_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `points` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE 3: points_transactions
-- Purpose: Transaction log for points system
-- Requirement: #14 - Points system (supporting table)
-- =====================================================

CREATE TABLE IF NOT EXISTS `points_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('earned','redeemed','expired') NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_transaction_type` (`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLES 4-6: E-commerce Structure (OPTIONAL - Future)
-- Purpose: Mini e-commerce for notebooks, e-PDFs, study materials
-- Requirement: #18 - Future e-commerce
-- =====================================================

-- TABLE 4: ecommerce_products
CREATE TABLE IF NOT EXISTS `ecommerce_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_type` enum('notebook','epdf','study_material') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_product_type` (`product_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 5: ecommerce_orders
CREATE TABLE IF NOT EXISTS `ecommerce_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_number` (`order_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABLE 6: ecommerce_order_items
CREATE TABLE IF NOT EXISTS `ecommerce_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- VERIFICATION QUERIES
-- Run these to verify table creation
-- =====================================================

-- Check if tables exist
-- SELECT TABLE_NAME FROM information_schema.TABLES 
-- WHERE TABLE_SCHEMA = 'u894882493_educourse' 
-- AND TABLE_NAME IN ('user_certificates', 'user_points', 'points_transactions', 'ecommerce_products', 'ecommerce_orders', 'ecommerce_order_items');

-- =====================================================
-- NOTES:
-- 1. All tables use InnoDB engine for transaction support
-- 2. All tables use utf8mb4 charset for emoji support
-- 3. Indexes are added for performance on frequently queried columns
-- 4. No foreign key constraints are enforced (as per project requirements)
-- 5. E-commerce tables are optional and can be created later if needed
-- =====================================================
