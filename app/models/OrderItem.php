<?php
// FILE: /app/models/OrderItem.php

namespace App\Models;

use App\Core\Model;

/**
 * OrderItem Model
 * Represents items in an order
 */
class OrderItem extends Model
{
    protected $table = 'order_items';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'order_id', 'menu_item_id', 'item_name', 'quantity', 'unit_price', 'total_price', 'notes', 'created_at'];
        return in_array($column, $columns);
    }

    /**
     * Get items by order
     *
     * @param int $order_id
     * @return array
     */
    public function getItemsByOrder($order_id)
    {
        return $this->findAll(['order_id' => $order_id]);
    }

    /**
     * Get item with options
     *
     * @param int $item_id
     * @return array|null
     */
    public function getItemWithOptions($item_id)
    {
        $item = $this->findById($item_id);

        if (!$item) {
            return null;
        }

        // Get item options
        $sql = "SELECT * FROM order_item_options WHERE order_item_id = :item_id";
        $item['options'] = $this->query($sql, [':item_id' => $item_id]);

        return $item;
    }
}
