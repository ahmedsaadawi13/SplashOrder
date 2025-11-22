<?php
// FILE: /app/services/AnalyticsService.php

namespace App\Services;

/**
 * Analytics Service
 * Advanced analytics and reporting
 */
class AnalyticsService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get sales trends
     *
     * @param int $tenant_id Tenant ID
     * @param string $period Period (7days, 30days, 90days, year)
     * @return array
     */
    public function getSalesTrends($tenant_id, $period = '30days')
    {
        $days = [
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            'year' => 365
        ];

        $day_count = $days[$period] ?? 30;

        $sql = "SELECT DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(total) as revenue,
                AVG(total) as avg_order_value
                FROM orders
                WHERE tenant_id = :tenant_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND status != 'canceled'
                GROUP BY DATE(created_at)
                ORDER BY date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':days' => $day_count
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get peak hours analysis
     *
     * @param int $tenant_id Tenant ID
     * @param int $days Days to analyze
     * @return array
     */
    public function getPeakHours($tenant_id, $days = 30)
    {
        $sql = "SELECT HOUR(created_at) as hour,
                COUNT(*) as order_count,
                SUM(total) as revenue
                FROM orders
                WHERE tenant_id = :tenant_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY HOUR(created_at)
                ORDER BY order_count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':days' => $days
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get customer retention metrics
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getCustomerRetention($tenant_id)
    {
        // New vs returning customers
        $sql = "SELECT
                COUNT(DISTINCT CASE WHEN total_orders = 1 THEN id END) as new_customers,
                COUNT(DISTINCT CASE WHEN total_orders > 1 THEN id END) as returning_customers
                FROM customers
                WHERE tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetch();
    }

    /**
     * Get product performance
     *
     * @param int $tenant_id Tenant ID
     * @param int $days Days to analyze
     * @param int $limit Limit results
     * @return array
     */
    public function getProductPerformance($tenant_id, $days = 30, $limit = 10)
    {
        $sql = "SELECT
                oi.item_name,
                oi.menu_item_id,
                COUNT(*) as times_ordered,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total_price) as total_revenue,
                AVG(oi.unit_price) as avg_price
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.tenant_id = :tenant_id
                AND o.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                AND o.status != 'canceled'
                GROUP BY oi.item_name, oi.menu_item_id
                ORDER BY total_revenue DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':days', $days);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get revenue forecast
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getRevenueForecast($tenant_id)
    {
        // Simple moving average forecast
        $sql = "SELECT AVG(daily_revenue) as avg_daily_revenue
                FROM (
                    SELECT DATE(created_at) as date, SUM(total) as daily_revenue
                    FROM orders
                    WHERE tenant_id = :tenant_id
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND status != 'canceled'
                    GROUP BY DATE(created_at)
                ) as daily_sales";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $result = $stmt->fetch();

        $daily_avg = (float)($result['avg_daily_revenue'] ?? 0);

        return [
            'next_7_days' => $daily_avg * 7,
            'next_30_days' => $daily_avg * 30,
            'next_90_days' => $daily_avg * 90,
            'daily_average' => $daily_avg
        ];
    }

    /**
     * Update daily analytics
     *
     * @param int $tenant_id Tenant ID
     * @param string $date Date (Y-m-d)
     * @return bool
     */
    public function updateDailyAnalytics($tenant_id, $date)
    {
        $sql = "INSERT INTO analytics_daily
                (tenant_id, date, total_orders, total_revenue, avg_order_value, new_customers, returning_customers, delivery_orders, pickup_orders)
                SELECT
                    :tenant_id,
                    :date,
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total), 0) as total_revenue,
                    COALESCE(AVG(total), 0) as avg_order_value,
                    COUNT(DISTINCT CASE WHEN c.total_orders = 1 THEN o.customer_id END) as new_customers,
                    COUNT(DISTINCT CASE WHEN c.total_orders > 1 THEN o.customer_id END) as returning_customers,
                    SUM(CASE WHEN order_type = 'delivery' THEN 1 ELSE 0 END) as delivery_orders,
                    SUM(CASE WHEN order_type = 'pickup' THEN 1 ELSE 0 END) as pickup_orders
                FROM orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.tenant_id = :tenant_id2
                AND DATE(o.created_at) = :date2
                AND o.status != 'canceled'
                ON DUPLICATE KEY UPDATE
                    total_orders = VALUES(total_orders),
                    total_revenue = VALUES(total_revenue),
                    avg_order_value = VALUES(avg_order_value)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':tenant_id2' => $tenant_id,
            ':date' => $date,
            ':date2' => $date
        ]);
    }
}
