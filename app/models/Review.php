<?php
// FILE: /app/models/Review.php

namespace App\Models;

use App\Core\Model;

/**
 * Review Model
 * Manages customer reviews and ratings
 */
class Review extends Model
{
    protected $table = 'reviews';
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'order_id',
        'rating',
        'comment',
        'status',
        'reply',
        'reply_by',
        'replied_at',
        'approved_at',
        'rejection_reason'
    ];

    /**
     * Get reviews by status
     *
     * @param int $tenant_id Tenant ID
     * @param string $status Status
     * @param int $limit Limit
     * @return array
     */
    public function getByStatus($tenant_id, $status, $limit = 20)
    {
        $sql = "SELECT r.*, c.name as customer_name, o.order_number
                FROM {$this->table} r
                JOIN customers c ON r.customer_id = c.id
                JOIN orders o ON r.order_id = o.id
                WHERE r.tenant_id = :tenant_id
                AND r.status = :status
                ORDER BY r.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get review by order
     *
     * @param int $order_id Order ID
     * @return array|null
     */
    public function getByOrder($order_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE order_id = :order_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $order_id]);

        return $stmt->fetch() ?: null;
    }
}
