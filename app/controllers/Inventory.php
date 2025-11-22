<?php
// FILE: /app/controllers/Inventory.php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\InventoryService;
use App\Models\Ingredient;

/**
 * Inventory Controller
 * Handles inventory management
 */
class Inventory extends Controller
{
    private $inventory_service;
    private $ingredient_model;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->inventory_service = new InventoryService();
        $this->ingredient_model = new Ingredient();
    }

    /**
     * Inventory dashboard
     */
    public function index()
    {
        $tenant_id = \Auth::getTenantId();

        $ingredients = $this->ingredient_model->findAll(['tenant_id' => $tenant_id]);
        $low_stock = $this->ingredient_model->getLowStock($tenant_id);
        $valuation = $this->inventory_service->getInventoryValuation($tenant_id);

        $this->view('inventory/index', [
            'ingredients' => $ingredients,
            'low_stock' => $low_stock,
            'valuation' => $valuation
        ]);
    }

    /**
     * Add ingredient
     */
    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $data = [
                'tenant_id' => \Auth::getTenantId(),
                'name' => $_POST['name'],
                'unit' => $_POST['unit'],
                'quantity_in_stock' => $_POST['quantity_in_stock'],
                'reorder_level' => $_POST['reorder_level'],
                'unit_cost' => $_POST['unit_cost']
            ];

            $id = $this->ingredient_model->insert($data);

            if ($id) {
                $this->flash('success', 'Ingredient added successfully');
                $this->redirect('/inventory');
            } else {
                $this->flash('error', 'Failed to add ingredient');
            }
        }

        $this->view('inventory/add');
    }

    /**
     * Edit ingredient
     */
    public function edit($id)
    {
        $ingredient = $this->ingredient_model->findById($id);

        if (!$ingredient || $ingredient['tenant_id'] != \Auth::getTenantId()) {
            $this->flash('error', 'Ingredient not found');
            $this->redirect('/inventory');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $data = [
                'name' => $_POST['name'],
                'unit' => $_POST['unit'],
                'reorder_level' => $_POST['reorder_level'],
                'unit_cost' => $_POST['unit_cost']
            ];

            $result = $this->ingredient_model->update($id, $data);

            if ($result) {
                $this->flash('success', 'Ingredient updated successfully');
                $this->redirect('/inventory');
            } else {
                $this->flash('error', 'Failed to update ingredient');
            }
        }

        $this->view('inventory/edit', ['ingredient' => $ingredient]);
    }

    /**
     * Add stock
     */
    public function addStock($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $quantity = $_POST['quantity'];
            $notes = $_POST['notes'] ?? null;

            $result = $this->inventory_service->addStock(
                $id,
                $quantity,
                'purchase',
                null,
                $notes,
                \Auth::getUserId()
            );

            if ($result) {
                $this->flash('success', 'Stock added successfully');
            } else {
                $this->flash('error', 'Failed to add stock');
            }

            $this->redirect('/inventory');
        }
    }

    /**
     * View stock movements
     */
    public function movements($ingredient_id)
    {
        $ingredient = $this->ingredient_model->findById($ingredient_id);

        if (!$ingredient || $ingredient['tenant_id'] != \Auth::getTenantId()) {
            $this->flash('error', 'Ingredient not found');
            $this->redirect('/inventory');
            return;
        }

        $movements = $this->inventory_service->getStockMovements($ingredient_id);

        $this->view('inventory/movements', [
            'ingredient' => $ingredient,
            'movements' => $movements
        ]);
    }

    /**
     * Low stock alerts
     */
    public function lowStock()
    {
        $tenant_id = \Auth::getTenantId();

        $alerts = $this->inventory_service->getLowStockAlerts($tenant_id);

        $this->view('inventory/low_stock', ['alerts' => $alerts]);
    }
}
