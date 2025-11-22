<?php
// FILE: /app/models/Tenant.php

namespace App\Models;

use App\Core\Model;

/**
 * Tenant Model
 * Represents a restaurant brand in the multi-tenant system
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'name', 'slug', 'logo', 'email', 'phone', 'address', 'description', 'website', 'status', 'api_key', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Find tenant by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug)
    {
        return $this->findOne(['slug' => $slug]);
    }

    /**
     * Find tenant by API key
     *
     * @param string $api_key
     * @return array|null
     */
    public function findByApiKey($api_key)
    {
        return $this->findOne(['api_key' => $api_key]);
    }

    /**
     * Get active tenants
     *
     * @return array
     */
    public function getActiveTenants()
    {
        return $this->findAll(['status' => 'active']);
    }

    /**
     * Generate unique API key
     *
     * @param string $prefix
     * @return string
     */
    public function generateApiKey($prefix = 'sk')
    {
        return $prefix . '_' . bin2hex(random_bytes(30));
    }

    /**
     * Get tenant subscription
     *
     * @param int $tenant_id
     * @return array|null
     */
    public function getActiveSubscription($tenant_id)
    {
        $sql = "SELECT ts.*, sp.name as plan_name, sp.max_branches, sp.max_menu_items,
                sp.max_orders_per_month, sp.max_storage_mb
                FROM tenant_subscriptions ts
                JOIN subscription_plans sp ON ts.plan_id = sp.id
                WHERE ts.tenant_id = :tenant_id AND ts.status = 'active'
                ORDER BY ts.id DESC LIMIT 1";

        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Check if tenant can add more branches
     *
     * @param int $tenant_id
     * @return bool
     */
    public function canAddBranch($tenant_id)
    {
        $subscription = $this->getActiveSubscription($tenant_id);

        if (!$subscription || $subscription['max_branches'] === null) {
            return true; // Unlimited
        }

        $sql = "SELECT COUNT(*) as count FROM branches WHERE tenant_id = :tenant_id";
        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        $current_count = $result[0]['count'];

        return $current_count < $subscription['max_branches'];
    }

    /**
     * Check if tenant can add more menu items
     *
     * @param int $tenant_id
     * @return bool
     */
    public function canAddMenuItem($tenant_id)
    {
        $subscription = $this->getActiveSubscription($tenant_id);

        if (!$subscription || $subscription['max_menu_items'] === null) {
            return true; // Unlimited
        }

        $sql = "SELECT COUNT(*) as count FROM menu_items WHERE tenant_id = :tenant_id";
        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        $current_count = $result[0]['count'];

        return $current_count < $subscription['max_menu_items'];
    }

    /**
     * Get usage statistics
     *
     * @param int $tenant_id
     * @return array
     */
    public function getUsageStats($tenant_id)
    {
        $stats = [];

        // Count branches
        $sql = "SELECT COUNT(*) as count FROM branches WHERE tenant_id = :tenant_id";
        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        $stats['branches'] = $result[0]['count'];

        // Count menu items
        $sql = "SELECT COUNT(*) as count FROM menu_items WHERE tenant_id = :tenant_id";
        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        $stats['menu_items'] = $result[0]['count'];

        // Count orders this month
        $sql = "SELECT COUNT(*) as count FROM orders
                WHERE tenant_id = :tenant_id
                AND MONTH(created_at) = MONTH(NOW())
                AND YEAR(created_at) = YEAR(NOW())";
        $result = $this->query($sql, [':tenant_id' => $tenant_id]);
        $stats['orders_this_month'] = $result[0]['count'];

        // Get subscription limits
        $subscription = $this->getActiveSubscription($tenant_id);
        $stats['limits'] = [
            'max_branches' => $subscription['max_branches'] ?? null,
            'max_menu_items' => $subscription['max_menu_items'] ?? null,
            'max_orders_per_month' => $subscription['max_orders_per_month'] ?? null,
        ];

        return $stats;
    }
}
