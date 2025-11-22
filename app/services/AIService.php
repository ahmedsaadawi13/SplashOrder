<?php
// FILE: /app/services/AIService.php

namespace App\Services;

/**
 * AI Service
 * Artificial Intelligence & Machine Learning features
 */
class AIService
{
    private $db;
    private $cache_service;
    private $openai_api_key;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->cache_service = new CacheService();
        $this->openai_api_key = getenv('OPENAI_API_KEY') ?: null;
    }

    /**
     * Generate demand forecast
     *
     * @param int $tenant_id Tenant ID
     * @param string $date Forecast date
     * @param string $type Forecast type
     * @return array
     */
    public function generateDemandForecast($tenant_id, $date, $type = 'daily')
    {
        // Get historical data
        $historical = $this->getHistoricalData($tenant_id, 90);

        // Calculate moving average
        $predicted_orders = $this->calculateMovingAverage($historical, 'orders', 7);
        $predicted_revenue = $this->calculateMovingAverage($historical, 'revenue', 7);

        // Calculate confidence based on data variance
        $confidence = $this->calculateConfidence($historical);

        // Store forecast
        $sql = "INSERT INTO ai_demand_forecasts
                (tenant_id, forecast_date, forecast_type, predicted_orders, predicted_revenue, confidence_score)
                VALUES (:tenant_id, :forecast_date, :forecast_type, :predicted_orders, :predicted_revenue, :confidence)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':forecast_date' => $date,
            ':forecast_type' => $type,
            ':predicted_orders' => $predicted_orders,
            ':predicted_revenue' => $predicted_revenue,
            ':confidence' => $confidence
        ]);

        return [
            'date' => $date,
            'predicted_orders' => $predicted_orders,
            'predicted_revenue' => $predicted_revenue,
            'confidence' => $confidence
        ];
    }

    /**
     * Generate personalized recommendations
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @param int $limit Number of recommendations
     * @return array
     */
    public function getPersonalizedRecommendations($customer_id, $tenant_id, $limit = 5)
    {
        // Check cache first
        $cache_key = "recommendations:{$customer_id}:{$tenant_id}";
        $cached = $this->cache_service->get($cache_key);

        if ($cached) {
            return $cached;
        }

        // Get customer's order history
        $order_history = $this->getCustomerOrderHistory($customer_id);

        // Collaborative filtering: Find similar customers
        $similar_customers = $this->findSimilarCustomers($customer_id, $tenant_id);

        // Get popular items among similar customers
        $recommendations = $this->getPopularItemsAmongCustomers($similar_customers, $tenant_id, $limit);

        // Store recommendations
        foreach ($recommendations as $item) {
            $this->storeRecommendation($customer_id, $tenant_id, $item['id'], 'collaborative', $item['score']);
        }

        // Cache for 1 hour
        $this->cache_service->set($cache_key, $recommendations, 3600);

        return $recommendations;
    }

    /**
     * Analyze sentiment from text
     *
     * @param string $text Text to analyze
     * @param string $entity_type Entity type
     * @param int $entity_id Entity ID
     * @return array
     */
    public function analyzeSentiment($text, $entity_type, $entity_id)
    {
        // Simple sentiment analysis (in production, use OpenAI or similar)
        $positive_words = ['great', 'excellent', 'amazing', 'love', 'best', 'delicious', 'fantastic', 'wonderful'];
        $negative_words = ['bad', 'terrible', 'awful', 'worst', 'disgusting', 'horrible', 'poor', 'disappointing'];

        $text_lower = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;

        foreach ($positive_words as $word) {
            $positive_count += substr_count($text_lower, $word);
        }

        foreach ($negative_words as $word) {
            $negative_count += substr_count($text_lower, $word);
        }

        // Calculate sentiment score (-1 to 1)
        $total_count = $positive_count + $negative_count;
        $sentiment_score = $total_count > 0
            ? ($positive_count - $negative_count) / $total_count
            : 0;

        // Determine sentiment label
        if ($sentiment_score >= 0.6) {
            $sentiment_label = 'very_positive';
        } elseif ($sentiment_score >= 0.2) {
            $sentiment_label = 'positive';
        } elseif ($sentiment_score >= -0.2) {
            $sentiment_label = 'neutral';
        } elseif ($sentiment_score >= -0.6) {
            $sentiment_label = 'negative';
        } else {
            $sentiment_label = 'very_negative';
        }

        // Store sentiment analysis
        $sql = "INSERT INTO sentiment_analysis
                (entity_type, entity_id, sentiment_score, sentiment_label)
                VALUES (:entity_type, :entity_id, :sentiment_score, :sentiment_label)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':entity_type' => $entity_type,
            ':entity_id' => $entity_id,
            ':sentiment_score' => $sentiment_score,
            ':sentiment_label' => $sentiment_label
        ]);

        return [
            'score' => $sentiment_score,
            'label' => $sentiment_label
        ];
    }

    /**
     * Apply dynamic pricing
     *
     * @param int $menu_item_id Menu item ID
     * @param int $tenant_id Tenant ID
     * @return float|null Adjusted price or null
     */
    public function applyDynamicPricing($menu_item_id, $tenant_id)
    {
        // Get active pricing rules
        $sql = "SELECT * FROM dynamic_pricing_rules
                WHERE tenant_id = :tenant_id
                AND (menu_item_id = :menu_item_id OR menu_item_id IS NULL)
                AND is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':menu_item_id' => $menu_item_id
        ]);
        $rules = $stmt->fetchAll();

        if (empty($rules)) {
            return null;
        }

        // Get base price
        $base_price = $this->getItemPrice($menu_item_id);
        $adjusted_price = $base_price;

        // Apply applicable rules
        foreach ($rules as $rule) {
            if ($this->isRuleApplicable($rule)) {
                if ($rule['price_adjustment_type'] === 'percentage') {
                    $adjustment = $base_price * ($rule['price_adjustment_value'] / 100);
                } else {
                    $adjustment = $rule['price_adjustment_value'];
                }

                $adjusted_price += $adjustment;
            }
        }

        return $adjusted_price;
    }

    /**
     * Get historical data
     *
     * @param int $tenant_id Tenant ID
     * @param int $days Number of days
     * @return array
     */
    private function getHistoricalData($tenant_id, $days)
    {
        $sql = "SELECT
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(total) as revenue
                FROM orders
                WHERE tenant_id = :tenant_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':days' => $days
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Calculate moving average
     *
     * @param array $data Historical data
     * @param string $field Field to average
     * @param int $window Window size
     * @return float
     */
    private function calculateMovingAverage($data, $field, $window)
    {
        if (count($data) < $window) {
            $window = count($data);
        }

        $recent_data = array_slice($data, -$window);
        $sum = array_sum(array_column($recent_data, $field));

        return $sum / $window;
    }

    /**
     * Calculate forecast confidence
     *
     * @param array $data Historical data
     * @return float
     */
    private function calculateConfidence($data)
    {
        // Simple confidence based on data availability
        $data_points = count($data);

        if ($data_points >= 60) {
            return 0.9;
        } elseif ($data_points >= 30) {
            return 0.7;
        } elseif ($data_points >= 14) {
            return 0.5;
        } else {
            return 0.3;
        }
    }

    /**
     * Get customer order history
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    private function getCustomerOrderHistory($customer_id)
    {
        $sql = "SELECT oi.menu_item_id, COUNT(*) as order_count
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.customer_id = :customer_id
                GROUP BY oi.menu_item_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':customer_id' => $customer_id]);

        return $stmt->fetchAll();
    }

    /**
     * Find similar customers
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @return array
     */
    private function findSimilarCustomers($customer_id, $tenant_id)
    {
        // Simple similarity: customers who ordered similar items
        $sql = "SELECT DISTINCT o.customer_id
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE oi.menu_item_id IN (
                    SELECT menu_item_id FROM order_items
                    WHERE order_id IN (
                        SELECT id FROM orders WHERE customer_id = :customer_id
                    )
                )
                AND o.customer_id != :customer_id2
                AND o.tenant_id = :tenant_id
                LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':customer_id2' => $customer_id,
            ':tenant_id' => $tenant_id
        ]);

        return array_column($stmt->fetchAll(), 'customer_id');
    }

    /**
     * Get popular items among customers
     *
     * @param array $customer_ids Customer IDs
     * @param int $tenant_id Tenant ID
     * @param int $limit Limit
     * @return array
     */
    private function getPopularItemsAmongCustomers($customer_ids, $tenant_id, $limit)
    {
        if (empty($customer_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customer_ids), '?'));

        $sql = "SELECT mi.id, mi.name, COUNT(*) as popularity, AVG(mi.price) as avg_price
                FROM menu_items mi
                JOIN order_items oi ON mi.id = oi.menu_item_id
                JOIN orders o ON oi.order_id = o.id
                WHERE o.customer_id IN ($placeholders)
                AND mi.tenant_id = ?
                AND mi.is_active = 1
                GROUP BY mi.id
                ORDER BY popularity DESC
                LIMIT ?";

        $stmt = $this->db->prepare($sql);

        $params = array_merge($customer_ids, [$tenant_id, $limit]);
        $stmt->execute($params);

        $items = $stmt->fetchAll();

        // Add confidence score
        foreach ($items as &$item) {
            $item['score'] = min(0.9, $item['popularity'] / 10);
        }

        return $items;
    }

    /**
     * Store recommendation
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @param int $item_id Item ID
     * @param string $type Recommendation type
     * @param float $confidence Confidence score
     * @return bool
     */
    private function storeRecommendation($customer_id, $tenant_id, $item_id, $type, $confidence)
    {
        $sql = "INSERT INTO ai_recommendations
                (customer_id, tenant_id, recommended_item_id, recommendation_type, confidence_score)
                VALUES (:customer_id, :tenant_id, :item_id, :type, :confidence)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':customer_id' => $customer_id,
            ':tenant_id' => $tenant_id,
            ':item_id' => $item_id,
            ':type' => $type,
            ':confidence' => $confidence
        ]);
    }

    /**
     * Check if pricing rule is applicable
     *
     * @param array $rule Pricing rule
     * @return bool
     */
    private function isRuleApplicable($rule)
    {
        $conditions = json_decode($rule['conditions'], true);

        switch ($rule['rule_type']) {
            case 'time_based':
                $current_hour = date('H:00');
                $current_day = date('l');

                if (isset($conditions['hours'])) {
                    foreach ($conditions['hours'] as $time_range) {
                        list($start, $end) = explode('-', $time_range);
                        if ($current_hour >= $start && $current_hour <= $end) {
                            return true;
                        }
                    }
                }

                if (isset($conditions['days'])) {
                    return in_array($current_day, $conditions['days']);
                }
                break;

            case 'demand_based':
                // Check current demand
                // Simplified: always applicable
                return true;

            default:
                return false;
        }

        return false;
    }

    /**
     * Get item price
     *
     * @param int $menu_item_id Menu item ID
     * @return float
     */
    private function getItemPrice($menu_item_id)
    {
        $sql = "SELECT price FROM menu_items WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $menu_item_id]);
        $result = $stmt->fetch();

        return $result ? (float)$result['price'] : 0;
    }
}
