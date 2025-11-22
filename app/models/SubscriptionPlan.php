<?php
// FILE: /app/models/SubscriptionPlan.php

namespace App\Models;

use App\Core\Model;

/**
 * SubscriptionPlan Model
 * Represents subscription plans available for tenants
 */
class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'name', 'slug', 'description', 'price', 'billing_cycle',
                    'max_branches', 'max_menu_items', 'max_orders_per_month', 'max_storage_mb',
                    'features', 'is_active', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get active plans
     *
     * @return array
     */
    public function getActivePlans()
    {
        return $this->findAll(['is_active' => 1], 'price ASC');
    }

    /**
     * Find plan by slug
     *
     * @param string $slug
     * @return array|null
     */
    public function findBySlug($slug)
    {
        return $this->findOne(['slug' => $slug]);
    }
}
