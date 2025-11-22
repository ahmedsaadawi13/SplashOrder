<?php
// FILE: /app/models/Branch.php

namespace App\Models;

use App\Core\Model;

/**
 * Branch Model
 * Represents restaurant branches
 */
class Branch extends Model
{
    protected $table = 'branches';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'name', 'slug', 'address', 'city', 'area', 'phone', 'email',
                    'latitude', 'longitude', 'opening_hours', 'delivery_radius_km', 'delivery_areas',
                    'delivery_fee', 'min_order_amount', 'status', 'is_active', 'image', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get active branches by tenant
     *
     * @param int $tenant_id
     * @return array
     */
    public function getActiveBranches($tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['is_active' => 1], 'name ASC');
    }

    /**
     * Get open branches
     *
     * @param int $tenant_id
     * @return array
     */
    public function getOpenBranches($tenant_id)
    {
        $this->setTenantId($tenant_id);
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND is_active = 1
                AND status = 'open'
                ORDER BY name ASC";

        return $this->query($sql, [':tenant_id' => $tenant_id]);
    }

    /**
     * Find branch by slug
     *
     * @param string $slug
     * @param int $tenant_id
     * @return array|null
     */
    public function findBySlug($slug, $tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findOne(['slug' => $slug]);
    }
}
