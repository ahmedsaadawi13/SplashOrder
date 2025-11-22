<?php
// FILE: /app/models/LoyaltyTransaction.php

namespace App\Models;

use App\Core\Model;

/**
 * LoyaltyTransaction Model
 * Manages loyalty points transactions
 */
class LoyaltyTransaction extends Model
{
    protected $table = 'loyalty_transactions';
    protected $fillable = [
        'customer_loyalty_id',
        'type',
        'points',
        'order_id',
        'description',
        'balance_after'
    ];

    /**
     * Get transactions by loyalty ID
     *
     * @param int $loyalty_id Loyalty ID
     * @param int $limit Limit
     * @return array
     */
    public function getByLoyalty($loyalty_id, $limit = 50)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE customer_loyalty_id = :loyalty_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':loyalty_id', $loyalty_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
