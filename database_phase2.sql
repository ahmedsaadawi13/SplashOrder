-- FILE: /database_phase2.sql
-- SplashOrder Phase 2 Database Schema
-- Additional tables for advanced features

USE `splashorder`;

-- =====================================================
-- INVENTORY MANAGEMENT
-- =====================================================

CREATE TABLE `ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL COMMENT 'kg, liters, pieces, etc',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `supplier` varchar(255) DEFAULT NULL,
  `last_restocked` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_low_stock` (`tenant_id`, `current_stock`),
  CONSTRAINT `fk_ingredients_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_item_ingredients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_item_id` int(11) NOT NULL,
  `ingredient_id` int(11) NOT NULL,
  `quantity_required` decimal(10,2) NOT NULL COMMENT 'Quantity needed per menu item',
  PRIMARY KEY (`id`),
  KEY `idx_menu_item_id` (`menu_item_id`),
  KEY `idx_ingredient_id` (`ingredient_id`),
  CONSTRAINT `fk_item_ingredients_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_ingredients_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ingredient_id` int(11) NOT NULL,
  `type` enum('purchase','usage','waste','adjustment') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'order, purchase_order, etc',
  `reference_id` int(11) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ingredient_id` (`ingredient_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_stock_movements_ingredient` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- LOYALTY & REWARDS
-- =====================================================

CREATE TABLE `loyalty_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `points_per_dollar` decimal(5,2) NOT NULL DEFAULT 1.00,
  `dollars_per_point` decimal(5,2) NOT NULL DEFAULT 0.01,
  `min_points_redemption` int(11) DEFAULT 100,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_loyalty_programs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_loyalty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `points_balance` int(11) NOT NULL DEFAULT 0,
  `points_earned_lifetime` int(11) NOT NULL DEFAULT 0,
  `points_redeemed_lifetime` int(11) NOT NULL DEFAULT 0,
  `tier` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_customer_tenant` (`customer_id`, `tenant_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_customer_loyalty_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_customer_loyalty_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `loyalty_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_loyalty_id` int(11) NOT NULL,
  `type` enum('earned','redeemed','expired','adjusted') NOT NULL,
  `points` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `balance_after` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customer_loyalty_id` (`customer_loyalty_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_loyalty_trans_customer_loyalty` FOREIGN KEY (`customer_loyalty_id`) REFERENCES `customer_loyalty` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loyalty_trans_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DELIVERY MANAGEMENT
-- =====================================================

CREATE TABLE `delivery_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `vehicle_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','busy') DEFAULT 'active',
  `current_latitude` decimal(10,8) DEFAULT NULL,
  `current_longitude` decimal(11,8) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 5.00,
  `total_deliveries` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_drivers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `delivery_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `picked_up_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `estimated_delivery_time` int(11) DEFAULT 30,
  `actual_delivery_time` int(11) DEFAULT NULL,
  `distance_km` decimal(5,2) DEFAULT NULL,
  `driver_notes` text,
  `customer_rating` tinyint(1) DEFAULT NULL COMMENT '1-5 stars',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_driver_id` (`driver_id`),
  KEY `idx_assigned_at` (`assigned_at`),
  CONSTRAINT `fk_delivery_assignments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_delivery_assignments_driver` FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `driver_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_driver_recorded` (`driver_id`, `recorded_at`),
  CONSTRAINT `fk_driver_locations_driver` FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REVIEWS & RATINGS
-- =====================================================

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `rating` tinyint(1) NOT NULL COMMENT '1-5 stars',
  `review_text` text,
  `images` text COMMENT 'JSON array of image URLs',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `helpful_count` int(11) DEFAULT 0,
  `response` text COMMENT 'Restaurant response',
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_menu_item_id` (`menu_item_id`),
  KEY `idx_status_rating` (`status`, `rating`),
  CONSTRAINT `fk_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reviews_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MARKETING AUTOMATION
-- =====================================================

CREATE TABLE `marketing_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('email','sms','push','all') NOT NULL DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `segment` varchar(100) DEFAULT NULL COMMENT 'all, new_customers, loyal, inactive',
  `status` enum('draft','scheduled','sent','canceled') DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `recipients_count` int(11) DEFAULT 0,
  `opened_count` int(11) DEFAULT 0,
  `clicked_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_campaigns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `campaign_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','failed','bounced') DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_campaign_recipients_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_campaign_recipients_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TWO-FACTOR AUTHENTICATION
-- =====================================================

CREATE TABLE `two_factor_auth` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `recovery_codes` text COMMENT 'JSON array of recovery codes',
  `is_enabled` tinyint(1) DEFAULT 0,
  `enabled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_2fa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` text,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_time` (`email`, `attempted_at`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- WEBHOOKS
-- =====================================================

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `events` text NOT NULL COMMENT 'JSON array of subscribed events',
  `secret` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_triggered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_webhooks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhook_deliveries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `webhook_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload` text NOT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_body` text,
  `success` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 1,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_webhook_deliveries_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADVANCED MENU FEATURES
-- =====================================================

ALTER TABLE `menu_items` ADD COLUMN `allergens` varchar(255) DEFAULT NULL COMMENT 'Comma-separated: nuts, dairy, gluten, etc';
ALTER TABLE `menu_items` ADD COLUMN `nutritional_info` text COMMENT 'JSON with nutritional details';
ALTER TABLE `menu_items` ADD COLUMN `ingredients_list` text COMMENT 'Human-readable ingredients list';
ALTER TABLE `menu_items` ADD COLUMN `is_seasonal` tinyint(1) DEFAULT 0;
ALTER TABLE `menu_items` ADD COLUMN `available_from` time DEFAULT NULL;
ALTER TABLE `menu_items` ADD COLUMN `available_until` time DEFAULT NULL;

-- =====================================================
-- ANALYTICS ENHANCEMENTS
-- =====================================================

CREATE TABLE `analytics_daily` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `avg_order_value` decimal(10,2) DEFAULT 0.00,
  `new_customers` int(11) DEFAULT 0,
  `returning_customers` int(11) DEFAULT 0,
  `delivery_orders` int(11) DEFAULT 0,
  `pickup_orders` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_date` (`tenant_id`, `date`),
  CONSTRAINT `fk_analytics_daily_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SEED DATA FOR PHASE 2
-- =====================================================

-- Insert Loyalty Programs for demo tenants
INSERT INTO `loyalty_programs` (`tenant_id`, `name`, `points_per_dollar`, `dollars_per_point`, `min_points_redemption`, `is_active`) VALUES
(1, 'Pizza Paradise Rewards', 1.00, 0.01, 100, 1),
(2, 'Burger Palace Points', 1.50, 0.01, 50, 1);

-- Insert Delivery Drivers
INSERT INTO `delivery_drivers` (`tenant_id`, `name`, `phone`, `email`, `vehicle_type`, `status`) VALUES
(1, 'John Driver', '+1-555-7001', 'john.driver@example.com', 'Motorcycle', 'active'),
(1, 'Sarah Delivery', '+1-555-7002', 'sarah.delivery@example.com', 'Car', 'active'),
(2, 'Mike Fast', '+1-555-7003', 'mike.fast@example.com', 'Bike', 'active');

-- Insert Sample Ingredients for Pizza Paradise
INSERT INTO `ingredients` (`tenant_id`, `name`, `unit`, `current_stock`, `min_stock`, `cost_per_unit`) VALUES
(1, 'Pizza Dough', 'kg', 50.00, 20.00, 2.50),
(1, 'Mozzarella Cheese', 'kg', 30.00, 10.00, 8.00),
(1, 'Tomato Sauce', 'liters', 20.00, 5.00, 3.50),
(1, 'Pepperoni', 'kg', 15.00, 5.00, 12.00),
(1, 'Mushrooms', 'kg', 10.00, 3.00, 5.00);

-- Insert Sample Reviews
INSERT INTO `reviews` (`tenant_id`, `order_id`, `customer_id`, `menu_item_id`, `rating`, `review_text`, `status`) VALUES
(1, 1, 1, 1, 5, 'Amazing pizza! Fresh ingredients and perfect crust.', 'approved'),
(1, 2, 2, 2, 4, 'Great taste but delivery was a bit slow.', 'approved'),
(2, 6, 4, 15, 5, 'Best burger in town! Highly recommended.', 'approved');

-- =====================================================
-- INDEXES FOR PHASE 2 PERFORMANCE
-- =====================================================

CREATE INDEX idx_reviews_approved_rating ON reviews(status, rating, created_at);
CREATE INDEX idx_loyalty_balance ON customer_loyalty(tenant_id, points_balance);
CREATE INDEX idx_ingredients_low_stock ON ingredients(tenant_id, current_stock, min_stock);
CREATE INDEX idx_campaigns_scheduled ON marketing_campaigns(status, scheduled_at);

-- =====================================================
-- COMPLETED PHASE 2 SCHEMA
-- =====================================================
