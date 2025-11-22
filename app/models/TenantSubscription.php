<?php
// FILE: /app/models/TenantSubscription.php

namespace App\Models;

use App\Core\Model;

/**
 * TenantSubscription Model
 * Represents tenant subscriptions to plans
 */
class TenantSubscription extends Model
{
    protected $table = 'tenant_subscriptions';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'plan_id', 'status', 'started_at', 'expires_at', 'auto_renew', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get active subscription for tenant
     *
     * @param int $tenant_id
     * @return array|null
     */
    public function getActiveSubscription($tenant_id)
    {
        $sql = "SELECT ts.*, sp.*
                FROM {$this->table} ts
                JOIN subscription_plans sp ON ts.plan_id = sp.id
                WHERE ts.tenant_id = :tenant_id
                AND ts.status = 'active'
                ORDER BY ts.id DESC
                LIMIT 1";

        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get subscription history
     *
     * @param int $tenant_id
     * @return array
     */
    public function getSubscriptionHistory($tenant_id)
    {
        $sql = "SELECT ts.*, sp.name as plan_name
                FROM {$this->table} ts
                JOIN subscription_plans sp ON ts.plan_id = sp.id
                WHERE ts.tenant_id = :tenant_id
                ORDER BY ts.created_at DESC";

        return $this->query($sql, [':tenant_id' => $tenant_id]);
    }
}
