-- FILE: /database.sql
-- SplashOrder Database Schema
-- Multi-tenant Restaurant Online Ordering SaaS Platform
-- Compatible with MySQL 5.7+ and MariaDB 10.2+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `splashorder` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `splashorder`;

-- =====================================================
-- MULTI-TENANCY & SUBSCRIPTION TABLES
-- =====================================================

-- Tenants (Restaurant Brands)
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL UNIQUE,
  `logo` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text,
  `description` text,
  `website` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `api_key` varchar(64) NOT NULL UNIQUE,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `role` enum('platform_admin','tenant_admin','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscription Plans
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `billing_cycle` enum('monthly','yearly') DEFAULT 'monthly',
  `max_branches` int(11) DEFAULT NULL,
  `max_menu_items` int(11) DEFAULT NULL,
  `max_orders_per_month` int(11) DEFAULT NULL,
  `max_storage_mb` int(11) DEFAULT 1000,
  `features` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenant Subscriptions
CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` enum('active','canceled','expired','suspended') DEFAULT 'active',
  `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_plan_id` (`plan_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tenant_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL UNIQUE,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','overdue','canceled') DEFAULT 'pending',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoices_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT 'credit_card',
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usage Tracking
CREATE TABLE `usage_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `metric` varchar(50) NOT NULL,
  `value` int(11) NOT NULL DEFAULT 0,
  `period` varchar(20) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_metric` (`tenant_id`, `metric`, `period`),
  CONSTRAINT `fk_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- RESTAURANT MANAGEMENT TABLES
-- =====================================================

-- Branches
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `opening_hours` text,
  `delivery_radius_km` decimal(5,2) DEFAULT 5.00,
  `delivery_areas` text,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('open','closed','temporarily_closed') DEFAULT 'open',
  `is_active` tinyint(1) DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MENU MANAGEMENT TABLES
-- =====================================================

-- Categories
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu Items
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `preparation_time` int(11) DEFAULT 15,
  `calories` int(11) DEFAULT NULL,
  `is_vegetarian` tinyint(1) DEFAULT 0,
  `is_spicy` tinyint(1) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_available` (`is_available`),
  CONSTRAINT `fk_menu_items_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu Options (Sizes, Add-ons, Modifiers)
CREATE TABLE `menu_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('size','addon','modifier') DEFAULT 'addon',
  `selection_type` enum('single','multiple') DEFAULT 'single',
  `is_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_menu_options_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu Option Values
CREATE TABLE `menu_option_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `option_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price_modifier` decimal(10,2) DEFAULT 0.00,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_option_id` (`option_id`),
  CONSTRAINT `fk_option_values_option` FOREIGN KEY (`option_id`) REFERENCES `menu_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Menu Item Options (Which options are available for which items)
CREATE TABLE `menu_item_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_item_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_item_id` (`menu_item_id`),
  KEY `idx_option_id` (`option_id`),
  CONSTRAINT `fk_item_options_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_options_option` FOREIGN KEY (`option_id`) REFERENCES `menu_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CUSTOMER & ORDER TABLES
-- =====================================================

-- Customers
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `address` text,
  `city` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `notes` text,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL UNIQUE,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `delivery_address` text,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_area` varchar(100) DEFAULT NULL,
  `order_type` enum('delivery','pickup') DEFAULT 'delivery',
  `status` enum('new','confirmed','preparing','out_for_delivery','delivered','canceled') DEFAULT 'new',
  `payment_method` enum('cod','online') DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `notes` text,
  `estimated_delivery_time` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_order_number` (`order_number`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_orders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_menu_item_id` (`menu_item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Item Options (Selected options for each order item)
CREATE TABLE `order_item_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `option_value` varchar(255) NOT NULL,
  `price_modifier` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_order_item_id` (`order_item_id`),
  CONSTRAINT `fk_order_item_options_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COUPON & DISCOUNT TABLES
-- =====================================================

-- Coupons
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_code` (`code`),
  KEY `idx_is_active` (`is_active`),
  CONSTRAINT `fk_coupons_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEED DATA
-- =====================================================

-- Insert Subscription Plans
INSERT INTO `subscription_plans` (`name`, `slug`, `description`, `price`, `billing_cycle`, `max_branches`, `max_menu_items`, `max_orders_per_month`, `max_storage_mb`, `features`) VALUES
('Starter', 'starter', 'Perfect for single restaurant', 29.99, 'monthly', 1, 50, 500, 500, 'Basic support, Online ordering, Basic analytics'),
('Professional', 'professional', 'For growing restaurant chains', 79.99, 'monthly', 5, 200, 2000, 2000, 'Priority support, Multiple branches, Advanced analytics, Custom domain'),
('Enterprise', 'enterprise', 'For large restaurant groups', 199.99, 'monthly', NULL, NULL, NULL, 10000, 'Dedicated support, Unlimited branches, Unlimited items, API access, Custom features');

-- Insert Platform Admin
INSERT INTO `users` (`tenant_id`, `name`, `email`, `password`, `role`, `status`) VALUES
(NULL, 'Platform Admin', 'admin@splashorder.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'platform_admin', 'active');
-- Password: password

-- Insert Demo Tenants
INSERT INTO `tenants` (`name`, `slug`, `email`, `phone`, `address`, `description`, `status`, `api_key`) VALUES
('Pizza Paradise', 'pizza-paradise', 'info@pizzaparadise.com', '+1-555-0100', '123 Main St, New York, NY 10001', 'Premium pizza restaurant chain serving authentic Italian pizzas', 'active', 'pp_' || SUBSTR(MD5(RAND()), 1, 60)),
('Burger Palace', 'burger-palace', 'contact@burgerpalace.com', '+1-555-0200', '456 Oak Ave, Los Angeles, CA 90001', 'Gourmet burger restaurant with fresh ingredients', 'active', 'bp_' || SUBSTR(MD5(RAND()), 1, 60));

-- Set proper API keys (MySQL-compatible way)
UPDATE `tenants` SET `api_key` = CONCAT('pp_', MD5(CONCAT('pizza-paradise', NOW()))) WHERE `slug` = 'pizza-paradise';
UPDATE `tenants` SET `api_key` = CONCAT('bp_', MD5(CONCAT('burger-palace', NOW()))) WHERE `slug` = 'burger-palace';

-- Insert Tenant Admins
INSERT INTO `users` (`tenant_id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'John Pizza', 'john@pizzaparadise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active'),
(2, 'Sarah Burger', 'sarah@burgerpalace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant_admin', 'active');
-- Password: password

-- Insert Staff Users
INSERT INTO `users` (`tenant_id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'Mike Staff', 'mike@pizzaparadise.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active'),
(2, 'Lisa Staff', 'lisa@burgerpalace.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

-- Insert Tenant Subscriptions
INSERT INTO `tenant_subscriptions` (`tenant_id`, `plan_id`, `status`, `started_at`, `expires_at`) VALUES
(1, 2, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH)),
(2, 1, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH));

-- Insert Invoices
INSERT INTO `invoices` (`tenant_id`, `subscription_id`, `invoice_number`, `amount`, `tax`, `total`, `status`, `due_date`, `paid_at`) VALUES
(1, 1, 'INV-2025-0001', 79.99, 7.20, 87.19, 'paid', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW()),
(2, 2, 'INV-2025-0002', 29.99, 2.70, 32.69, 'paid', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW());

-- Insert Payments
INSERT INTO `payments` (`invoice_id`, `tenant_id`, `amount`, `payment_method`, `transaction_id`, `status`) VALUES
(1, 1, 87.19, 'credit_card', 'TXN_' || SUBSTR(MD5(RAND()), 1, 20), 'success'),
(2, 2, 32.69, 'credit_card', 'TXN_' || SUBSTR(MD5(RAND()), 1, 20), 'success');

-- Fix transaction IDs (MySQL-compatible)
UPDATE `payments` SET `transaction_id` = CONCAT('TXN_', MD5(CONCAT(id, NOW()))) WHERE `transaction_id` LIKE 'TXN_%';

-- Insert Branches for Pizza Paradise
INSERT INTO `branches` (`tenant_id`, `name`, `slug`, `address`, `city`, `area`, `phone`, `email`, `opening_hours`, `delivery_radius_km`, `delivery_fee`, `min_order_amount`, `status`) VALUES
(1, 'Pizza Paradise Downtown', 'pizza-paradise-downtown', '123 Main St', 'New York', 'Manhattan', '+1-555-0101', 'downtown@pizzaparadise.com', '10:00 AM - 11:00 PM', 5.00, 5.00, 15.00, 'open'),
(1, 'Pizza Paradise Uptown', 'pizza-paradise-uptown', '789 Broadway', 'New York', 'Bronx', '+1-555-0102', 'uptown@pizzaparadise.com', '11:00 AM - 10:00 PM', 4.00, 4.00, 12.00, 'open'),
(1, 'Pizza Paradise Brooklyn', 'pizza-paradise-brooklyn', '456 Brooklyn Ave', 'New York', 'Brooklyn', '+1-555-0103', 'brooklyn@pizzaparadise.com', '10:00 AM - 12:00 AM', 6.00, 5.50, 15.00, 'open');

-- Insert Branches for Burger Palace
INSERT INTO `branches` (`tenant_id`, `name`, `slug`, `address`, `city`, `area`, `phone`, `email`, `opening_hours`, `delivery_radius_km`, `delivery_fee`, `min_order_amount`, `status`) VALUES
(2, 'Burger Palace Hollywood', 'burger-palace-hollywood', '456 Oak Ave', 'Los Angeles', 'Hollywood', '+1-555-0201', 'hollywood@burgerpalace.com', '9:00 AM - 11:00 PM', 5.00, 6.00, 20.00, 'open'),
(2, 'Burger Palace Santa Monica', 'burger-palace-santa-monica', '789 Beach Blvd', 'Los Angeles', 'Santa Monica', '+1-555-0202', 'santamonica@burgerpalace.com', '10:00 AM - 10:00 PM', 4.50, 5.00, 18.00, 'open');

-- Insert Categories for Pizza Paradise
INSERT INTO `categories` (`tenant_id`, `name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
(1, 'Pizzas', 'pizzas', 'Authentic Italian pizzas with fresh ingredients', 1, 1),
(1, 'Appetizers', 'appetizers', 'Start your meal with our delicious appetizers', 2, 1),
(1, 'Salads', 'salads', 'Fresh and healthy salads', 3, 1),
(1, 'Desserts', 'desserts', 'Sweet treats to end your meal', 4, 1),
(1, 'Beverages', 'beverages', 'Refreshing drinks', 5, 1);

-- Insert Categories for Burger Palace
INSERT INTO `categories` (`tenant_id`, `name`, `slug`, `description`, `sort_order`, `is_active`) VALUES
(2, 'Burgers', 'burgers', 'Gourmet burgers made with premium beef', 1, 1),
(2, 'Sides', 'sides', 'Perfect sides for your burger', 2, 1),
(2, 'Salads', 'salads', 'Fresh garden salads', 3, 1),
(2, 'Drinks', 'drinks', 'Beverages and shakes', 4, 1);

-- Insert Menu Items for Pizza Paradise
INSERT INTO `menu_items` (`tenant_id`, `category_id`, `name`, `slug`, `description`, `base_price`, `preparation_time`, `is_vegetarian`, `is_available`, `is_featured`) VALUES
(1, 1, 'Margherita Pizza', 'margherita-pizza', 'Classic pizza with tomato sauce, mozzarella, and fresh basil', 12.99, 20, 1, 1, 1),
(1, 1, 'Pepperoni Pizza', 'pepperoni-pizza', 'Loaded with pepperoni and mozzarella cheese', 14.99, 20, 0, 1, 1),
(1, 1, 'Vegetarian Supreme', 'vegetarian-supreme', 'Bell peppers, mushrooms, onions, olives, and tomatoes', 15.99, 22, 1, 1, 0),
(1, 1, 'Meat Lovers', 'meat-lovers', 'Pepperoni, sausage, bacon, and ham', 17.99, 25, 0, 1, 1),
(1, 1, 'BBQ Chicken Pizza', 'bbq-chicken-pizza', 'Grilled chicken, BBQ sauce, onions, and cilantro', 16.99, 23, 0, 1, 0),
(1, 2, 'Garlic Bread', 'garlic-bread', 'Fresh baked bread with garlic butter', 5.99, 10, 1, 1, 0),
(1, 2, 'Mozzarella Sticks', 'mozzarella-sticks', 'Crispy mozzarella sticks with marinara sauce', 7.99, 12, 1, 1, 0),
(1, 2, 'Chicken Wings', 'chicken-wings', 'Spicy buffalo wings with ranch dressing', 9.99, 15, 0, 1, 0),
(1, 3, 'Caesar Salad', 'caesar-salad', 'Romaine lettuce, croutons, parmesan, caesar dressing', 8.99, 8, 1, 1, 0),
(1, 3, 'Greek Salad', 'greek-salad', 'Fresh vegetables, feta cheese, olives, greek dressing', 9.99, 8, 1, 1, 0),
(1, 4, 'Tiramisu', 'tiramisu', 'Classic Italian dessert', 6.99, 5, 1, 1, 0),
(1, 4, 'Chocolate Lava Cake', 'chocolate-lava-cake', 'Warm chocolate cake with molten center', 7.99, 10, 1, 1, 0),
(1, 5, 'Coca-Cola', 'coca-cola', 'Classic Coca-Cola', 2.99, 2, 1, 1, 0),
(1, 5, 'Fresh Lemonade', 'fresh-lemonade', 'Freshly squeezed lemonade', 3.99, 3, 1, 1, 0);

-- Insert Menu Items for Burger Palace
INSERT INTO `menu_items` (`tenant_id`, `category_id`, `name`, `slug`, `description`, `base_price`, `preparation_time`, `is_vegetarian`, `is_available`, `is_featured`) VALUES
(2, 6, 'Classic Burger', 'classic-burger', 'Beef patty, lettuce, tomato, onion, pickles, special sauce', 10.99, 15, 0, 1, 1),
(2, 6, 'Cheese Burger', 'cheese-burger', 'Classic burger with cheddar cheese', 11.99, 15, 0, 1, 1),
(2, 6, 'Bacon Burger', 'bacon-burger', 'Burger topped with crispy bacon and cheese', 13.99, 18, 0, 1, 1),
(2, 6, 'Veggie Burger', 'veggie-burger', 'Plant-based patty with fresh vegetables', 10.99, 15, 1, 1, 0),
(2, 6, 'Double Burger', 'double-burger', 'Two beef patties with double cheese', 15.99, 20, 0, 1, 1),
(2, 7, 'French Fries', 'french-fries', 'Crispy golden fries', 4.99, 8, 1, 1, 0),
(2, 7, 'Onion Rings', 'onion-rings', 'Crispy battered onion rings', 5.99, 10, 1, 1, 0),
(2, 7, 'Sweet Potato Fries', 'sweet-potato-fries', 'Sweet and crispy fries', 5.99, 10, 1, 1, 0),
(2, 8, 'Garden Salad', 'garden-salad', 'Fresh mixed greens with vegetables', 7.99, 7, 1, 1, 0),
(2, 9, 'Milkshake', 'milkshake', 'Creamy milkshake - Vanilla, Chocolate, or Strawberry', 5.99, 5, 1, 1, 0),
(2, 9, 'Iced Tea', 'iced-tea', 'Refreshing iced tea', 2.99, 2, 1, 1, 0);

-- Insert Menu Options
INSERT INTO `menu_options` (`tenant_id`, `name`, `type`, `selection_type`, `is_required`) VALUES
(1, 'Pizza Size', 'size', 'single', 1),
(1, 'Extra Toppings', 'addon', 'multiple', 0),
(1, 'Crust Type', 'modifier', 'single', 1),
(2, 'Burger Size', 'size', 'single', 1),
(2, 'Add-ons', 'addon', 'multiple', 0),
(2, 'Fries Size', 'size', 'single', 1);

-- Insert Option Values
INSERT INTO `menu_option_values` (`option_id`, `name`, `price_modifier`, `is_default`) VALUES
-- Pizza sizes
(1, 'Small (10 inch)', 0.00, 0),
(1, 'Medium (12 inch)', 3.00, 1),
(1, 'Large (14 inch)', 6.00, 0),
(1, 'X-Large (16 inch)', 9.00, 0),
-- Pizza toppings
(2, 'Extra Cheese', 2.00, 0),
(2, 'Mushrooms', 1.50, 0),
(2, 'Pepperoni', 2.50, 0),
(2, 'Olives', 1.50, 0),
(2, 'Bell Peppers', 1.50, 0),
-- Crust types
(3, 'Regular', 0.00, 1),
(3, 'Thin Crust', 0.00, 0),
(3, 'Thick Crust', 2.00, 0),
(3, 'Stuffed Crust', 4.00, 0),
-- Burger sizes
(4, 'Regular', 0.00, 1),
(4, 'Large', 3.00, 0),
-- Burger add-ons
(5, 'Extra Patty', 4.00, 0),
(5, 'Bacon', 2.50, 0),
(5, 'Avocado', 2.00, 0),
(5, 'Fried Egg', 1.50, 0),
(5, 'Extra Cheese', 1.50, 0),
-- Fries sizes
(6, 'Regular', 0.00, 1),
(6, 'Large', 2.00, 0);

-- Link options to menu items (Pizza items get pizza options)
INSERT INTO `menu_item_options` (`menu_item_id`, `option_id`) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 2), (2, 3),
(3, 1), (3, 2), (3, 3),
(4, 1), (4, 2), (4, 3),
(5, 1), (5, 2), (5, 3);

-- Link options to burger items
INSERT INTO `menu_item_options` (`menu_item_id`, `option_id`) VALUES
(15, 4), (15, 5),
(16, 4), (16, 5),
(17, 4), (17, 5),
(18, 4), (18, 5),
(19, 4), (19, 5);

-- Link size option to fries
INSERT INTO `menu_item_options` (`menu_item_id`, `option_id`) VALUES
(20, 6), (21, 6), (22, 6);

-- Insert Sample Customers
INSERT INTO `customers` (`tenant_id`, `name`, `email`, `phone`, `address`, `city`, `area`) VALUES
(1, 'Alice Johnson', 'alice@example.com', '+1-555-1001', '321 Park Ave', 'New York', 'Manhattan'),
(1, 'Bob Smith', 'bob@example.com', '+1-555-1002', '654 5th Ave', 'New York', 'Brooklyn'),
(1, 'Carol White', 'carol@example.com', '+1-555-1003', '987 Madison Ave', 'New York', 'Queens'),
(2, 'David Brown', 'david@example.com', '+1-555-2001', '123 Sunset Blvd', 'Los Angeles', 'Hollywood'),
(2, 'Emma Davis', 'emma@example.com', '+1-555-2002', '456 Venice Beach', 'Los Angeles', 'Santa Monica');

-- Insert Sample Orders for Pizza Paradise
INSERT INTO `orders` (`tenant_id`, `branch_id`, `customer_id`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `delivery_address`, `delivery_city`, `delivery_area`, `order_type`, `status`, `payment_method`, `payment_status`, `subtotal`, `delivery_fee`, `discount`, `tax`, `total`) VALUES
(1, 1, 1, 'ORD-PP-0001', 'Alice Johnson', '+1-555-1001', 'alice@example.com', '321 Park Ave', 'New York', 'Manhattan', 'delivery', 'delivered', 'cod', 'paid', 38.97, 5.00, 0.00, 3.52, 47.49),
(1, 1, 2, 'ORD-PP-0002', 'Bob Smith', '+1-555-1002', 'bob@example.com', '654 5th Ave', 'New York', 'Brooklyn', 'delivery', 'delivered', 'online', 'paid', 45.96, 5.00, 4.60, 4.04, 50.40),
(1, 2, 3, 'ORD-PP-0003', 'Carol White', '+1-555-1003', 'carol@example.com', '987 Madison Ave', 'New York', 'Queens', 'delivery', 'preparing', 'cod', 'pending', 52.95, 4.00, 0.00, 4.57, 61.52),
(1, 1, 1, 'ORD-PP-0004', 'Alice Johnson', '+1-555-1001', 'alice@example.com', '321 Park Ave', 'New York', 'Manhattan', 'pickup', 'confirmed', 'online', 'paid', 28.98, 0.00, 0.00, 2.32, 31.30),
(1, 3, 2, 'ORD-PP-0005', 'Bob Smith', '+1-555-1002', 'bob@example.com', NULL, 'New York', 'Brooklyn', 'pickup', 'new', 'cod', 'pending', 35.97, 0.00, 0.00, 2.88, 38.85);

-- Insert Sample Orders for Burger Palace
INSERT INTO `orders` (`tenant_id`, `branch_id`, `customer_id`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `delivery_address`, `delivery_city`, `delivery_area`, `order_type`, `status`, `payment_method`, `payment_status`, `subtotal`, `delivery_fee`, `discount`, `tax`, `total`) VALUES
(2, 4, 4, 'ORD-BP-0001', 'David Brown', '+1-555-2001', 'david@example.com', '123 Sunset Blvd', 'Los Angeles', 'Hollywood', 'delivery', 'delivered', 'online', 'paid', 32.97, 6.00, 0.00, 3.12, 42.09),
(2, 5, 5, 'ORD-BP-0002', 'Emma Davis', '+1-555-2002', 'emma@example.com', '456 Venice Beach', 'Los Angeles', 'Santa Monica', 'delivery', 'out_for_delivery', 'cod', 'pending', 28.97, 5.00, 0.00, 2.72, 36.69),
(2, 4, 4, 'ORD-BP-0003', 'David Brown', '+1-555-2001', 'david@example.com', NULL, 'Los Angeles', 'Hollywood', 'pickup', 'confirmed', 'cod', 'pending', 22.98, 0.00, 0.00, 1.84, 24.82);

-- Insert Order Items for Pizza Paradise orders
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `item_name`, `quantity`, `unit_price`, `total_price`) VALUES
-- Order 1
(1, 1, 'Margherita Pizza', 2, 15.99, 31.98),
(1, 13, 'Coca-Cola', 2, 2.99, 5.98),
-- Order 2
(2, 2, 'Pepperoni Pizza', 1, 17.99, 17.99),
(2, 4, 'Meat Lovers', 1, 20.99, 20.99),
(2, 6, 'Garlic Bread', 1, 5.99, 5.99),
-- Order 3
(3, 3, 'Vegetarian Supreme', 2, 18.99, 37.98),
(3, 7, 'Mozzarella Sticks', 1, 7.99, 7.99),
(3, 9, 'Caesar Salad', 1, 8.99, 8.99),
-- Order 4
(4, 1, 'Margherita Pizza', 1, 15.99, 15.99),
(4, 11, 'Tiramisu', 1, 6.99, 6.99),
(4, 13, 'Coca-Cola', 2, 2.99, 5.98),
-- Order 5
(5, 2, 'Pepperoni Pizza', 1, 17.99, 17.99),
(5, 8, 'Chicken Wings', 1, 9.99, 9.99),
(5, 14, 'Fresh Lemonade', 2, 3.99, 7.98);

-- Insert Order Items for Burger Palace orders
INSERT INTO `order_items` (`order_id`, `menu_item_id`, `item_name`, `quantity`, `unit_price`, `total_price`) VALUES
-- Order 6
(6, 16, 'Cheese Burger', 2, 11.99, 23.98),
(6, 20, 'French Fries', 2, 4.99, 9.98),
-- Order 7
(7, 17, 'Bacon Burger', 1, 13.99, 13.99),
(7, 19, 'Double Burger', 1, 15.99, 15.99),
(7, 24, 'Milkshake', 1, 5.99, 5.99),
-- Order 8
(8, 15, 'Classic Burger', 1, 10.99, 10.99),
(8, 20, 'French Fries', 1, 4.99, 4.99),
(8, 25, 'Iced Tea', 1, 2.99, 2.99);

-- Insert Sample Order Item Options
INSERT INTO `order_item_options` (`order_item_id`, `option_name`, `option_value`, `price_modifier`) VALUES
-- Pizza orders with options
(1, 'Pizza Size', 'Medium (12 inch)', 3.00),
(1, 'Crust Type', 'Regular', 0.00),
(3, 'Pizza Size', 'Large (14 inch)', 6.00),
(3, 'Extra Toppings', 'Extra Cheese', 2.00),
(4, 'Pizza Size', 'Large (14 inch)', 6.00),
(4, 'Extra Toppings', 'Mushrooms', 1.50),
(4, 'Extra Toppings', 'Pepperoni', 2.50),
-- Burger orders with options
(11, 'Burger Size', 'Regular', 0.00),
(13, 'Burger Size', 'Large', 3.00),
(13, 'Add-ons', 'Bacon', 2.50);

-- Insert Sample Coupons
INSERT INTO `coupons` (`tenant_id`, `code`, `description`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `usage_limit`, `usage_count`, `valid_from`, `valid_until`, `is_active`) VALUES
(1, 'PIZZA10', '10% off on orders above $30', 'percentage', 10.00, 30.00, 10.00, 100, 5, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1),
(1, 'WELCOME20', '$20 off on first order', 'fixed', 20.00, 50.00, NULL, NULL, 12, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 1),
(2, 'BURGER15', '15% off on all burgers', 'percentage', 15.00, 25.00, 15.00, 50, 8, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1),
(2, 'FREEFRIES', '$5 off - Free fries with burger', 'fixed', 5.00, 15.00, NULL, 200, 45, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 1);

-- Insert Usage Tracking Data
INSERT INTO `usage_tracking` (`tenant_id`, `metric`, `value`, `period`, `recorded_at`) VALUES
(1, 'orders', 45, '2025-01', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 'orders', 52, '2025-02', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'branches', 3, '2025-02', NOW()),
(1, 'menu_items', 14, '2025-02', NOW()),
(1, 'storage_mb', 245, '2025-02', NOW()),
(2, 'orders', 28, '2025-01', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 'orders', 35, '2025-02', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'branches', 2, '2025-02', NOW()),
(2, 'menu_items', 11, '2025-02', NOW()),
(2, 'storage_mb', 156, '2025-02', NOW());

-- Update customer statistics
UPDATE `customers` c SET
  `total_orders` = (SELECT COUNT(*) FROM `orders` WHERE `customer_id` = c.id),
  `total_spent` = (SELECT COALESCE(SUM(`total`), 0) FROM `orders` WHERE `customer_id` = c.id);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional composite indexes for common queries
ALTER TABLE `orders` ADD INDEX `idx_tenant_status_created` (`tenant_id`, `status`, `created_at`);
ALTER TABLE `menu_items` ADD INDEX `idx_tenant_category_available` (`tenant_id`, `category_id`, `is_available`);
ALTER TABLE `branches` ADD INDEX `idx_tenant_status` (`tenant_id`, `status`);

-- =====================================================
-- COMPLETED
-- =====================================================
