<?php
// FILE: /app/models/CustomerLoyalty.php

namespace App\Models;

use App\Core\Model;

/**
 * CustomerLoyalty Model
 * Manages customer loyalty records
 */
class CustomerLoyalty extends Model
{
    protected $table = 'customer_loyalty';
    protected $fillable = [
        'customer_id',
        'tenant_id',
        'points_balance',
        'points_earned_lifetime',
        'points_redeemed_lifetime',
        'tier'
    ];

    /**
     * Get loyalty by customer
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @return array|null
     */
    public function getByCustomer($customer_id, $tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE customer_id = :customer_id
                AND tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':tenant_id' => $tenant_id
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get top customers by tier
     *
     * @param int $tenant_id Tenant ID
     * @param string $tier Tier
     * @param int $limit Limit
     * @return array
     */
    public function getByTier($tenant_id, $tier, $limit = 100)
    {
        $sql = "SELECT cl.*, c.name, c.email
                FROM {$this->table} cl
                JOIN customers c ON cl.customer_id = c.id
                WHERE cl.tenant_id = :tenant_id
                AND cl.tier = :tier
                ORDER BY cl.points_earned_lifetime DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':tier', $tier);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
