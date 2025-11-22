<?php
// FILE: /app/models/StockMovement.php

namespace App\Models;

use App\Core\Model;

/**
 * StockMovement Model
 * Manages stock movement history
 */
class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $fillable = [
        'ingredient_id',
        'tenant_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
        'user_id'
    ];

    /**
     * Get movements for ingredient
     *
     * @param int $ingredient_id Ingredient ID
     * @param int $limit Limit
     * @return array
     */
    public function getByIngredient($ingredient_id, $limit = 50)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE ingredient_id = :ingredient_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ingredient_id', $ingredient_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
