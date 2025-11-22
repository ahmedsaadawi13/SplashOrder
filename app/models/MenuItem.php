<?php
// FILE: /app/models/MenuItem.php

namespace App\Models;

use App\Core\Model;

/**
 * MenuItem Model
 * Represents menu items
 */
class MenuItem extends Model
{
    protected $table = 'menu_items';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'category_id', 'name', 'slug', 'description', 'image',
                    'base_price', 'preparation_time', 'calories', 'is_vegetarian', 'is_spicy',
                    'is_available', 'is_featured', 'sort_order', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get menu items by category
     *
     * @param int $category_id
     * @param int $tenant_id
     * @return array
     */
    public function getItemsByCategory($category_id, $tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['category_id' => $category_id, 'is_available' => 1], 'sort_order ASC, name ASC');
    }

    /**
     * Get available items
     *
     * @param int $tenant_id
     * @return array
     */
    public function getAvailableItems($tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['is_available' => 1], 'name ASC');
    }

    /**
     * Get featured items
     *
     * @param int $tenant_id
     * @param int $limit
     * @return array
     */
    public function getFeaturedItems($tenant_id, $limit = 6)
    {
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                JOIN categories c ON mi.category_id = c.id
                WHERE mi.tenant_id = :tenant_id
                AND mi.is_featured = 1
                AND mi.is_available = 1
                ORDER BY mi.sort_order ASC, mi.name ASC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get item with category
     *
     * @param int $id
     * @param int $tenant_id
     * @return array|null
     */
    public function getItemWithCategory($id, $tenant_id)
    {
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                JOIN categories c ON mi.category_id = c.id
                WHERE mi.id = :id AND mi.tenant_id = :tenant_id
                LIMIT 1";

        $result = $this->query($sql, [':id' => $id, ':tenant_id' => $tenant_id]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get item options
     *
     * @param int $item_id
     * @return array
     */
    public function getItemOptions($item_id)
    {
        $sql = "SELECT mo.*, GROUP_CONCAT(mov.id, ':', mov.name, ':', mov.price_modifier, ':', mov.is_default SEPARATOR '|') as values
                FROM menu_item_options mio
                JOIN menu_options mo ON mio.option_id = mo.id
                LEFT JOIN menu_option_values mov ON mo.id = mov.option_id
                WHERE mio.menu_item_id = :item_id
                GROUP BY mo.id
                ORDER BY mo.is_required DESC, mo.id ASC";

        $results = $this->query($sql, [':item_id' => $item_id]);

        // Parse the values
        foreach ($results as &$option) {
            $values = [];
            if (!empty($option['values'])) {
                $value_strings = explode('|', $option['values']);
                foreach ($value_strings as $value_string) {
                    list($id, $name, $price, $is_default) = explode(':', $value_string);
                    $values[] = [
                        'id' => $id,
                        'name' => $name,
                        'price_modifier' => $price,
                        'is_default' => $is_default
                    ];
                }
            }
            $option['values'] = $values;
        }

        return $results;
    }

    /**
     * Search menu items
     *
     * @param string $query
     * @param int $tenant_id
     * @return array
     */
    public function searchItems($query, $tenant_id)
    {
        $sql = "SELECT mi.*, c.name as category_name
                FROM {$this->table} mi
                JOIN categories c ON mi.category_id = c.id
                WHERE mi.tenant_id = :tenant_id
                AND mi.is_available = 1
                AND (mi.name LIKE :query OR mi.description LIKE :query)
                ORDER BY mi.name ASC";

        $search = '%' . $query . '%';
        return $this->query($sql, [':tenant_id' => $tenant_id, ':query' => $search]);
    }

    /**
     * Find item by slug
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
