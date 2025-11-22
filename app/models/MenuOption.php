<?php
// FILE: /app/models/MenuOption.php

namespace App\Models;

use App\Core\Model;

/**
 * MenuOption Model
 * Represents menu options (sizes, add-ons, modifiers)
 */
class MenuOption extends Model
{
    protected $table = 'menu_options';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'name', 'type', 'selection_type', 'is_required', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Get option with values
     *
     * @param int $option_id
     * @return array|null
     */
    public function getOptionWithValues($option_id)
    {
        $option = $this->findById($option_id);

        if (!$option) {
            return null;
        }

        // Get values
        $sql = "SELECT * FROM menu_option_values WHERE option_id = :option_id ORDER BY id ASC";
        $option['values'] = $this->query($sql, [':option_id' => $option_id]);

        return $option;
    }

    /**
     * Get all options with their values
     *
     * @param int $tenant_id
     * @return array
     */
    public function getAllOptionsWithValues($tenant_id)
    {
        $this->setTenantId($tenant_id);
        $options = $this->findAll([], 'name ASC');

        foreach ($options as &$option) {
            $sql = "SELECT * FROM menu_option_values WHERE option_id = :option_id ORDER BY id ASC";
            $option['values'] = $this->query($sql, [':option_id' => $option['id']]);
        }

        return $options;
    }
}
