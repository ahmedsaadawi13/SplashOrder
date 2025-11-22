<?php
// FILE: /app/models/Ingredient.php

namespace App\Models;

use App\Core\Model;

/**
 * Ingredient Model
 * Manages inventory ingredients
 */
class Ingredient extends Model
{
    protected $table = 'ingredients';
    protected $fillable = [
        'tenant_id',
        'name',
        'unit',
        'quantity_in_stock',
        'reorder_level',
        'unit_cost'
    ];

    /**
     * Get low stock ingredients
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getLowStock($tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND quantity_in_stock <= reorder_level
                ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Update stock quantity
     *
     * @param int $ingredient_id Ingredient ID
     * @param float $quantity Quantity change (+ or -)
     * @return bool
     */
    public function adjustStock($ingredient_id, $quantity)
    {
        $sql = "UPDATE {$this->table}
                SET quantity_in_stock = quantity_in_stock + :quantity
                WHERE id = :ingredient_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':ingredient_id' => $ingredient_id,
            ':quantity' => $quantity
        ]);
    }
}
