<?php
// FILE: /app/models/Customer.php

namespace App\Models;

use App\Core\Model;

/**
 * Customer Model
 * Represents customers who place orders
 */
class Customer extends Model
{
    protected $table = 'customers';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'name', 'email', 'phone', 'password', 'address',
                    'city', 'area', 'notes', 'total_orders', 'total_spent', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Find customer by phone
     *
     * @param string $phone
     * @param int|null $tenant_id
     * @return array|null
     */
    public function findByPhone($phone, $tenant_id = null)
    {
        if ($tenant_id !== null) {
            $this->setTenantId($tenant_id);
        }

        return $this->findOne(['phone' => $phone]);
    }

    /**
     * Find customer by email
     *
     * @param string $email
     * @param int|null $tenant_id
     * @return array|null
     */
    public function findByEmail($email, $tenant_id = null)
    {
        if ($tenant_id !== null) {
            $this->setTenantId($tenant_id);
        }

        return $this->findOne(['email' => $email]);
    }

    /**
     * Find or create customer
     *
     * @param array $data
     * @param int|null $tenant_id
     * @return int Customer ID
     */
    public function findOrCreate($data, $tenant_id = null)
    {
        if ($tenant_id !== null) {
            $this->setTenantId($tenant_id);
            $data['tenant_id'] = $tenant_id;
        }

        // Try to find by phone
        $customer = $this->findByPhone($data['phone'], $tenant_id);

        if ($customer) {
            // Update customer info if provided
            $update_data = [];
            if (!empty($data['name'])) {
                $update_data['name'] = $data['name'];
            }
            if (!empty($data['email'])) {
                $update_data['email'] = $data['email'];
            }
            if (!empty($data['address'])) {
                $update_data['address'] = $data['address'];
            }

            if (!empty($update_data)) {
                $this->update($customer['id'], $update_data);
            }

            return $customer['id'];
        }

        // Create new customer
        return $this->insert($data);
    }

    /**
     * Get customer orders
     *
     * @param int $customer_id
     * @param int $limit
     * @return array
     */
    public function getCustomerOrders($customer_id, $limit = 10)
    {
        $sql = "SELECT * FROM orders
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':customer_id', $customer_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Update customer statistics
     *
     * @param int $customer_id
     * @return void
     */
    public function updateStatistics($customer_id)
    {
        $sql = "UPDATE {$this->table} SET
                total_orders = (SELECT COUNT(*) FROM orders WHERE customer_id = :customer_id),
                total_spent = (SELECT COALESCE(SUM(total), 0) FROM orders WHERE customer_id = :customer_id2)
                WHERE id = :customer_id3";

        $this->query($sql, [
            ':customer_id' => $customer_id,
            ':customer_id2' => $customer_id,
            ':customer_id3' => $customer_id
        ]);
    }

    /**
     * Get top customers by tenant
     *
     * @param int $tenant_id
     * @param int $limit
     * @return array
     */
    public function getTopCustomers($tenant_id, $limit = 10)
    {
        $sql = "SELECT c.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total), 0) as total_revenue
                FROM {$this->table} c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE c.tenant_id = :tenant_id OR o.tenant_id = :tenant_id2
                GROUP BY c.id
                ORDER BY total_revenue DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':tenant_id2', $tenant_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
