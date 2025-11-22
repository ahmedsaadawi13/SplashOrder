-- FILE: /database_phase4.sql
-- SplashOrder Phase 4 Database Schema
-- AI, Mobile Apps & Advanced Integrations

-- ============================================================================
-- 1. AI & MACHINE LEARNING
-- ============================================================================

CREATE TABLE IF NOT EXISTS ai_demand_forecasts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    branch_id INT,
    forecast_date DATE NOT NULL,
    forecast_type ENUM('daily', 'hourly', 'menu_item') NOT NULL,
    predicted_orders INT,
    predicted_revenue DECIMAL(10, 2),
    confidence_score DECIMAL(5, 2),
    actual_orders INT,
    actual_revenue DECIMAL(10, 2),
    accuracy_score DECIMAL(5, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_date (forecast_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    tenant_id INT NOT NULL,
    recommended_item_id INT NOT NULL,
    recommendation_type ENUM('collaborative', 'content_based', 'hybrid', 'trending') NOT NULL,
    confidence_score DECIMAL(5, 2),
    was_clicked TINYINT(1) DEFAULT 0,
    was_ordered TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_item (recommended_item_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dynamic_pricing_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    menu_item_id INT,
    rule_type ENUM('time_based', 'demand_based', 'inventory_based', 'weather_based') NOT NULL,
    conditions JSON NOT NULL,
    price_adjustment_type ENUM('percentage', 'fixed') NOT NULL,
    price_adjustment_value DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_menu_item (menu_item_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    tenant_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    platform ENUM('web', 'mobile', 'facebook', 'whatsapp') DEFAULT 'web',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    messages_count INT DEFAULT 0,
    was_helpful TINYINT(1),
    escalated_to_human TINYINT(1) DEFAULT 0,
    INDEX idx_customer (customer_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_session (session_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_chatbot_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer', 'bot', 'agent') NOT NULL,
    message_text TEXT NOT NULL,
    intent VARCHAR(100),
    confidence_score DECIMAL(5, 2),
    response_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (conversation_id) REFERENCES ai_chatbot_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sentiment_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('review', 'chatbot_message', 'social_media') NOT NULL,
    entity_id INT NOT NULL,
    sentiment_score DECIMAL(5, 2),
    sentiment_label ENUM('very_negative', 'negative', 'neutral', 'positive', 'very_positive') NOT NULL,
    key_phrases JSON,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_analyzed_at (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. MOBILE APPS
-- ============================================================================

CREATE TABLE IF NOT EXISTS mobile_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    device_type ENUM('ios', 'android') NOT NULL,
    device_token VARCHAR(500) NOT NULL UNIQUE,
    device_model VARCHAR(100),
    os_version VARCHAR(50),
    app_version VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    last_active_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_customer (customer_id),
    INDEX idx_device_token (device_token),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    notification_type ENUM('order_update', 'promotion', 'loyalty', 'general') NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    data JSON,
    target_type ENUM('all', 'segment', 'individual') NOT NULL,
    target_customer_id INT,
    target_segment_id INT,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    total_sent INT DEFAULT 0,
    total_delivered INT DEFAULT 0,
    total_opened INT DEFAULT 0,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'failed') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_at (scheduled_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (target_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (target_segment_id) REFERENCES customer_segments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_notification_deliveries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT NOT NULL,
    device_id INT NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed', 'opened') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    opened_at TIMESTAMP NULL,
    error_message TEXT,
    INDEX idx_notification (notification_id),
    INDEX idx_device (device_id),
    INDEX idx_status (status),
    FOREIGN KEY (notification_id) REFERENCES push_notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    customer_id INT,
    session_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_end TIMESTAMP NULL,
    duration_seconds INT,
    screens_viewed INT DEFAULT 0,
    actions_performed INT DEFAULT 0,
    app_version VARCHAR(50),
    INDEX idx_device (device_id),
    INDEX idx_customer (customer_id),
    INDEX idx_start (session_start),
    FOREIGN KEY (device_id) REFERENCES mobile_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. ADVANCED PAYMENT OPTIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    method_type ENUM('credit_card', 'paypal', 'apple_pay', 'google_pay', 'crypto', 'bank_transfer', 'cash') NOT NULL,
    provider VARCHAR(100),
    api_key_encrypted TEXT,
    api_secret_encrypted TEXT,
    webhook_secret_encrypted TEXT,
    configuration JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crypto_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    cryptocurrency VARCHAR(20) NOT NULL,
    amount_crypto DECIMAL(18, 8) NOT NULL,
    amount_usd DECIMAL(10, 2) NOT NULL,
    exchange_rate DECIMAL(18, 8) NOT NULL,
    wallet_address VARCHAR(200),
    transaction_hash VARCHAR(200),
    status ENUM('pending', 'confirming', 'confirmed', 'failed', 'expired') DEFAULT 'pending',
    confirmations INT DEFAULT 0,
    required_confirmations INT DEFAULT 3,
    expires_at TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_transaction_hash (transaction_hash),
    INDEX idx_status (status),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS split_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method_1 VARCHAR(50) NOT NULL,
    amount_1 DECIMAL(10, 2) NOT NULL,
    payment_method_2 VARCHAR(50),
    amount_2 DECIMAL(10, 2),
    payment_method_3 VARCHAR(50),
    amount_3 DECIMAL(10, 2),
    tip_amount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'partial', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 4. THIRD-PARTY INTEGRATIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS integrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    integration_type ENUM('pos', 'accounting', 'delivery_aggregator', 'social_media', 'email_marketing', 'sms_gateway', 'analytics', 'crm') NOT NULL,
    provider VARCHAR(100) NOT NULL,
    credentials_encrypted TEXT,
    configuration JSON,
    sync_frequency ENUM('realtime', 'hourly', 'daily', 'manual') DEFAULT 'manual',
    last_sync_at TIMESTAMP NULL,
    next_sync_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    status ENUM('connected', 'disconnected', 'error') DEFAULT 'disconnected',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_type (integration_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integration_sync_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    integration_id INT NOT NULL,
    sync_type ENUM('full', 'incremental', 'manual') NOT NULL,
    sync_direction ENUM('import', 'export', 'bidirectional') NOT NULL,
    records_processed INT DEFAULT 0,
    records_succeeded INT DEFAULT 0,
    records_failed INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    error_log TEXT,
    INDEX idx_integration (integration_id),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_media_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    platform ENUM('facebook', 'instagram', 'twitter', 'tiktok') NOT NULL,
    post_type ENUM('promotion', 'new_menu_item', 'event', 'general') NOT NULL,
    content TEXT NOT NULL,
    image_url VARCHAR(500),
    scheduled_at TIMESTAMP NULL,
    posted_at TIMESTAMP NULL,
    post_id_external VARCHAR(200),
    status ENUM('draft', 'scheduled', 'posted', 'failed') DEFAULT 'draft',
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_platform (platform),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. VOICE ORDERING
-- ============================================================================

CREATE TABLE IF NOT EXISTS voice_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    tenant_id INT NOT NULL,
    platform ENUM('alexa', 'google_assistant', 'siri', 'phone') NOT NULL,
    session_id VARCHAR(200),
    transcript TEXT,
    order_id INT,
    status ENUM('initiated', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'initiated',
    error_message TEXT,
    duration_seconds INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_order (order_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. ADVANCED BUSINESS INTELLIGENCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS custom_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    created_by INT NOT NULL,
    report_name VARCHAR(200) NOT NULL,
    report_type ENUM('sales', 'inventory', 'customers', 'staff', 'custom') NOT NULL,
    query_config JSON NOT NULL,
    visualization_config JSON,
    schedule ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none',
    recipients JSON,
    last_generated_at TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_type (report_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    export_format ENUM('pdf', 'excel', 'csv', 'json') NOT NULL,
    file_path VARCHAR(500),
    file_size_kb INT,
    generated_by INT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX idx_report (report_id),
    INDEX idx_generated_at (generated_at),
    FOREIGN KEY (report_id) REFERENCES custom_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_dashboards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    dashboard_name VARCHAR(200) NOT NULL,
    widgets_config JSON NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 7. IMAGE RECOGNITION & AI FEATURES
-- ============================================================================

CREATE TABLE IF NOT EXISTS ai_image_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('menu_item', 'review_photo', 'user_upload') NOT NULL,
    entity_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    detected_objects JSON,
    food_categories JSON,
    quality_score DECIMAL(5, 2),
    is_food TINYINT(1),
    is_appropriate TINYINT(1) DEFAULT 1,
    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_analyzed_at (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. GAMIFICATION & ENGAGEMENT
-- ============================================================================

CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    achievement_name VARCHAR(200) NOT NULL,
    description TEXT,
    achievement_type ENUM('orders', 'spending', 'reviews', 'referrals', 'social') NOT NULL,
    requirement_config JSON NOT NULL,
    reward_points INT DEFAULT 0,
    reward_coupon_id INT,
    badge_image_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_displayed TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_customer_achievement (customer_id, achievement_id),
    INDEX idx_customer (customer_id),
    INDEX idx_achievement (achievement_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    referrer_reward_type ENUM('points', 'discount', 'coupon') NOT NULL,
    referrer_reward_value DECIMAL(10, 2) NOT NULL,
    referee_reward_type ENUM('points', 'discount', 'coupon') NOT NULL,
    referee_reward_value DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    referrer_customer_id INT NOT NULL,
    referee_customer_id INT NOT NULL,
    referral_code VARCHAR(50),
    referee_first_order_id INT,
    referrer_rewarded TINYINT(1) DEFAULT 0,
    referee_rewarded TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_referrer (referrer_customer_id),
    INDEX idx_referee (referee_customer_id),
    INDEX idx_code (referral_code),
    FOREIGN KEY (program_id) REFERENCES referral_programs(id) ON DELETE CASCADE,
    FOREIGN KEY (referrer_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (referee_customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (referee_first_order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA FOR PHASE 4
-- ============================================================================

-- Sample Dynamic Pricing Rules
INSERT INTO dynamic_pricing_rules (tenant_id, rule_type, conditions, price_adjustment_type, price_adjustment_value) VALUES
(1, 'time_based', '{"hours": ["17:00-19:00"], "days": ["Friday", "Saturday"]}', 'percentage', -10.00),
(1, 'demand_based', '{"min_orders_per_hour": 20}', 'percentage', 5.00);

-- Sample Achievements
INSERT INTO achievements (tenant_id, achievement_name, description, achievement_type, requirement_config, reward_points) VALUES
(1, 'First Order', 'Complete your first order', 'orders', '{"order_count": 1}', 100),
(1, 'Loyal Customer', 'Place 10 orders', 'orders', '{"order_count": 10}', 500),
(1, 'Big Spender', 'Spend $500 total', 'spending', '{"total_spent": 500}', 1000);

-- Sample Payment Methods
INSERT INTO payment_methods (tenant_id, method_type, provider, is_active) VALUES
(1, 'credit_card', 'Stripe', 1),
(1, 'paypal', 'PayPal', 1),
(1, 'apple_pay', 'Stripe', 1),
(1, 'google_pay', 'Stripe', 1);
