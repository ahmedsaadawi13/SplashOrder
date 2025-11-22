<?php
// FILE: /app/models/Order.php

namespace App\Models;

use App\Core\Model;

/**
 * Order Model
 * Represents customer orders
 */
class Order extends Model
{
    protected $table = 'orders';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'branch_id', 'customer_id', 'order_number', 'customer_name',
                    'customer_phone', 'customer_email', 'delivery_address', 'delivery_city', 'delivery_area',
                    'order_type', 'status', 'payment_method', 'payment_status', 'subtotal', 'delivery_fee',
                    'discount', 'tax', 'total', 'coupon_code', 'notes', 'estimated_delivery_time',
                    'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Generate unique order number
     *
     * @param string $prefix
     * @return string
     */
    public function generateOrderNumber($prefix = 'ORD')
    {
        return $prefix . '-' . strtoupper(substr(uniqid(), -8));
    }

    /**
     * Create order with items
     *
     * @param array $order_data
     * @param array $items
     * @return int|false Order ID
     */
    public function createOrderWithItems($order_data, $items)
    {
        $this->beginTransaction();

        try {
            // Create order
            $order_id = $this->insert($order_data);

            if (!$order_id) {
                throw new \Exception('Failed to create order');
            }

            // Create order items
            $order_item_model = new OrderItem();
            foreach ($items as $item) {
                $item['order_id'] = $order_id;
                $item_id = $order_item_model->insert($item);

                // Add item options if provided
                if (!empty($item['options']) && $item_id) {
                    $this->addItemOptions($item_id, $item['options']);
                }
            }

            $this->commit();
            return $order_id;

        } catch (\Exception $e) {
            $this->rollback();
            error_log('Order creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add options to order item
     *
     * @param int $order_item_id
     * @param array $options
     * @return void
     */
    private function addItemOptions($order_item_id, $options)
    {
        foreach ($options as $option) {
            $sql = "INSERT INTO order_item_options (order_item_id, option_name, option_value, price_modifier)
                    VALUES (:order_item_id, :option_name, :option_value, :price_modifier)";

            $this->query($sql, [
                ':order_item_id' => $order_item_id,
                ':option_name' => $option['name'],
                ':option_value' => $option['value'],
                ':price_modifier' => $option['price_modifier'] ?? 0
            ]);
        }
    }

    /**
     * Get order with details
     *
     * @param int $order_id
     * @param int|null $tenant_id
     * @return array|null
     */
    public function getOrderWithDetails($order_id, $tenant_id = null)
    {
        if ($tenant_id !== null) {
            $this->setTenantId($tenant_id);
        }

        $order = $this->findById($order_id);

        if (!$order) {
            return null;
        }

        // Get order items
        $sql = "SELECT oi.*, GROUP_CONCAT(oio.option_name, ': ', oio.option_value SEPARATOR ', ') as options_text
                FROM order_items oi
                LEFT JOIN order_item_options oio ON oi.id = oio.order_item_id
                WHERE oi.order_id = :order_id
                GROUP BY oi.id";

        $order['items'] = $this->query($sql, [':order_id' => $order_id]);

        // Get branch info if available
        if ($order['branch_id']) {
            $branch_model = new Branch();
            $order['branch'] = $branch_model->findById($order['branch_id']);
        }

        return $order;
    }

    /**
     * Find order by order number
     *
     * @param string $order_number
     * @param int|null $tenant_id
     * @return array|null
     */
    public function findByOrderNumber($order_number, $tenant_id = null)
    {
        if ($tenant_id !== null) {
            $this->setTenantId($tenant_id);
        }

        return $this->findOne(['order_number' => $order_number]);
    }

    /**
     * Update order status
     *
     * @param int $order_id
     * @param string $status
     * @return bool
     */
    public function updateStatus($order_id, $status)
    {
        return $this->update($order_id, ['status' => $status]);
    }

    /**
     * Get orders by status
     *
     * @param string $status
     * @param int $tenant_id
     * @param int $limit
     * @return array
     */
    public function getOrdersByStatus($status, $tenant_id, $limit = 50)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['status' => $status], 'created_at DESC', $limit);
    }

    /**
     * Get today's orders
     *
     * @param int $tenant_id
     * @return array
     */
    public function getTodaysOrders($tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND DATE(created_at) = CURDATE()
                ORDER BY created_at DESC";

        return $this->query($sql, [':tenant_id' => $tenant_id]);
    }

    /**
     * Get orders statistics
     *
     * @param int $tenant_id
     * @param string $period (today, week, month, year)
     * @return array
     */
    public function getOrdersStats($tenant_id, $period = 'today')
    {
        $date_condition = '';

        switch ($period) {
            case 'today':
                $date_condition = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $date_condition = 'YEARWEEK(created_at) = YEARWEEK(NOW())';
                break;
            case 'month':
                $date_condition = 'MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())';
                break;
            case 'year':
                $date_condition = 'YEAR(created_at) = YEAR(NOW())';
                break;
            default:
                $date_condition = 'DATE(created_at) = CURDATE()';
        }

        $sql = "SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(AVG(total), 0) as average_order_value,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'canceled' THEN 1 END) as canceled_orders
                FROM {$this->table}
                WHERE tenant_id = :tenant_id AND {$date_condition}";

        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        return !empty($result) ? $result[0] : [];
    }

    /**
     * Get best selling items
     *
     * @param int $tenant_id
     * @param int $limit
     * @return array
     */
    public function getBestSellingItems($tenant_id, $limit = 10)
    {
        $sql = "SELECT oi.item_name, SUM(oi.quantity) as total_quantity,
                COUNT(DISTINCT oi.order_id) as order_count,
                COALESCE(SUM(oi.total_price), 0) as total_revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.tenant_id = :tenant_id
                AND o.status != 'canceled'
                GROUP BY oi.item_name
                ORDER BY total_quantity DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get orders by date range
     *
     * @param int $tenant_id
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getOrdersByDateRange($tenant_id, $start_date, $end_date)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND DATE(created_at) BETWEEN :start_date AND :end_date
                ORDER BY created_at DESC";

        return $this->query($sql, [
            ':tenant_id' => $tenant_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
    }
}
