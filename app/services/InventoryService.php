<?php
// FILE: /app/services/InventoryService.php

namespace App\Services;

/**
 * Inventory Service
 * Handles inventory management and stock tracking
 */
class InventoryService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Deduct ingredients for an order
     *
     * @param int $menu_item_id Menu item ID
     * @param int $quantity Quantity ordered
     * @return bool
     */
    public function deductStock($menu_item_id, $quantity)
    {
        // Get ingredients required for this item
        $sql = "SELECT mii.ingredient_id, mii.quantity_required, i.current_stock
                FROM menu_item_ingredients mii
                JOIN ingredients i ON mii.ingredient_id = i.id
                WHERE mii.menu_item_id = :menu_item_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':menu_item_id' => $menu_item_id]);
        $ingredients = $stmt->fetchAll();

        foreach ($ingredients as $ingredient) {
            $required = $ingredient['quantity_required'] * $quantity;

            // Check if sufficient stock
            if ($ingredient['current_stock'] < $required) {
                return false; // Insufficient stock
            }

            // Deduct stock
            $sql = "UPDATE ingredients
                    SET current_stock = current_stock - :quantity
                    WHERE id = :ingredient_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':quantity' => $required,
                ':ingredient_id' => $ingredient['ingredient_id']
            ]);

            // Record stock movement
            $this->recordStockMovement($ingredient['ingredient_id'], 'usage', -$required, 'order', null);
        }

        return true;
    }

    /**
     * Check if item is in stock
     *
     * @param int $menu_item_id Menu item ID
     * @param int $quantity Quantity to check
     * @return bool
     */
    public function isInStock($menu_item_id, $quantity)
    {
        $sql = "SELECT mii.ingredient_id, mii.quantity_required, i.current_stock
                FROM menu_item_ingredients mii
                JOIN ingredients i ON mii.ingredient_id = i.id
                WHERE mii.menu_item_id = :menu_item_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':menu_item_id' => $menu_item_id]);
        $ingredients = $stmt->fetchAll();

        foreach ($ingredients as $ingredient) {
            $required = $ingredient['quantity_required'] * $quantity;
            if ($ingredient['current_stock'] < $required) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get low stock alerts
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getLowStockAlerts($tenant_id)
    {
        $sql = "SELECT * FROM ingredients
                WHERE tenant_id = :tenant_id
                AND current_stock <= min_stock
                ORDER BY current_stock ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Add stock (purchase/restock)
     *
     * @param int $ingredient_id Ingredient ID
     * @param float $quantity Quantity to add
     * @param float $cost Total cost
     * @return bool
     */
    public function addStock($ingredient_id, $quantity, $cost)
    {
        $sql = "UPDATE ingredients
                SET current_stock = current_stock + :quantity,
                    last_restocked = NOW()
                WHERE id = :ingredient_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':quantity' => $quantity,
            ':ingredient_id' => $ingredient_id
        ]);

        if ($result) {
            $this->recordStockMovement($ingredient_id, 'purchase', $quantity, null, null, $cost);
        }

        return $result;
    }

    /**
     * Record stock movement
     *
     * @param int $ingredient_id Ingredient ID
     * @param string $type Type of movement
     * @param float $quantity Quantity
     * @param string|null $reference_type Reference type
     * @param int|null $reference_id Reference ID
     * @param float|null $cost Cost
     * @return void
     */
    private function recordStockMovement($ingredient_id, $type, $quantity, $reference_type = null, $reference_id = null, $cost = null)
    {
        $sql = "INSERT INTO stock_movements
                (ingredient_id, type, quantity, reference_type, reference_id, cost)
                VALUES (:ingredient_id, :type, :quantity, :reference_type, :reference_id, :cost)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ingredient_id' => $ingredient_id,
            ':type' => $type,
            ':quantity' => $quantity,
            ':reference_type' => $reference_type,
            ':reference_id' => $reference_id,
            ':cost' => $cost
        ]);
    }

    /**
     * Get inventory value
     *
     * @param int $tenant_id Tenant ID
     * @return float
     */
    public function getInventoryValue($tenant_id)
    {
        $sql = "SELECT SUM(current_stock * cost_per_unit) as total_value
                FROM ingredients
                WHERE tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $result = $stmt->fetch();

        return (float)($result['total_value'] ?? 0);
    }
}
