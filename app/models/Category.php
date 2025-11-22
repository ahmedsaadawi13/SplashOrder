<?php
// FILE: /app/models/Category.php

namespace App\Models;

use App\Core\Model;

/**
 * Category Model
 * Represents menu categories
 */
class Category extends Model
{
    protected $table = 'categories';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'name', 'slug', 'description', 'image', 'sort_order', 'is_active', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get active categories
     *
     * @param int $tenant_id
     * @return array
     */
    public function getActiveCategories($tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['is_active' => 1], 'sort_order ASC, name ASC');
    }

    /**
     * Find category by slug
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

    /**
     * Get categories with item count
     *
     * @param int $tenant_id
     * @return array
     */
    public function getCategoriesWithCount($tenant_id)
    {
        $sql = "SELECT c.*, COUNT(mi.id) as item_count
                FROM {$this->table} c
                LEFT JOIN menu_items mi ON c.id = mi.category_id
                WHERE c.tenant_id = :tenant_id
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.name ASC";

        return $this->query($sql, [':tenant_id' => $tenant_id]);
    }
}
