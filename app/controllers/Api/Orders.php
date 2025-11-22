<?php
// FILE: /app/controllers/Api/Orders.php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Tenant;
use App\Models\MenuItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Branch;

/**
 * API Orders Controller
 * REST API for creating orders from external sources
 */
class Orders extends Controller
{
    /**
     * Create order via API
     * POST /api/orders/create
     */
    public function createAction()
    {
        // Only accept POST requests
        if (!\Request::isPost()) {
            $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }

        // Get API key from header
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if (!$api_key) {
            $this->jsonResponse(['error' => 'API key required'], 401);
        }

        // Authenticate tenant
        $tenant_model = new Tenant();
        $tenant = $tenant_model->findByApiKey($api_key);

        if (!$tenant || $tenant['status'] !== 'active') {
            $this->jsonResponse(['error' => 'Invalid API key'], 401);
        }

        // Get JSON input
        $input = \Request::json();

        // Validate required fields
        $required_fields = ['customer_name', 'customer_phone', 'items', 'order_type'];
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                $this->jsonResponse(['error' => "Missing required field: $field"], 400);
            }
        }

        // Validate order type
        if (!in_array($input['order_type'], ['delivery', 'pickup'])) {
            $this->jsonResponse(['error' => 'Invalid order_type. Must be "delivery" or "pickup"'], 400);
        }

        // Validate items
        if (!is_array($input['items']) || empty($input['items'])) {
            $this->jsonResponse(['error' => 'Items must be a non-empty array'], 400);
        }

        // Process items and calculate totals
        $menu_item_model = new MenuItem();
        $menu_item_model->setTenantId($tenant['id']);

        $subtotal = 0;
        $order_items = [];

        foreach ($input['items'] as $item_data) {
            if (empty($item_data['menu_item_id']) || empty($item_data['quantity'])) {
                $this->jsonResponse(['error' => 'Each item must have menu_item_id and quantity'], 400);
            }

            $menu_item = $menu_item_model->findById($item_data['menu_item_id']);

            if (!$menu_item || !$menu_item['is_available']) {
                $this->jsonResponse([
                    'error' => 'Menu item not found or unavailable',
                    'item_id' => $item_data['menu_item_id']
                ], 400);
            }

            $quantity = max(1, (int)$item_data['quantity']);
            $unit_price = $menu_item['base_price'];
            $total_price = $unit_price * $quantity;

            $order_items[] = [
                'menu_item_id' => $menu_item['id'],
                'item_name' => $menu_item['name'],
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total_price' => $total_price
            ];

            $subtotal += $total_price;
        }

        // Get branch for delivery fee
        $branch_id = null;
        $delivery_fee = 0;

        if ($input['order_type'] === 'pickup') {
            if (empty($input['branch_id'])) {
                $this->jsonResponse(['error' => 'branch_id required for pickup orders'], 400);
            }

            $branch_model = new Branch();
            $branch = $branch_model->findById($input['branch_id']);

            if (!$branch || $branch['tenant_id'] != $tenant['id']) {
                $this->jsonResponse(['error' => 'Invalid branch_id'], 400);
            }

            $branch_id = $branch['id'];
        } else {
            // Delivery
            $branch_model = new Branch();
            $branch_model->setTenantId($tenant['id']);
            $branches = $branch_model->getOpenBranches($tenant['id']);

            if (!empty($branches)) {
                $branch_id = $branches[0]['id'];
                $delivery_fee = $branches[0]['delivery_fee'];
            }
        }

        // Calculate tax (10%)
        $tax = ($subtotal + $delivery_fee) * 0.10;
        $total = $subtotal + $delivery_fee + $tax;

        // Create or find customer
        $customer_model = new Customer();
        $customer_id = $customer_model->findOrCreate([
            'name' => $input['customer_name'],
            'phone' => $input['customer_phone'],
            'email' => $input['customer_email'] ?? null,
            'address' => $input['delivery_address'] ?? null,
            'city' => $input['delivery_city'] ?? null
        ], $tenant['id']);

        // Create order
        $order_model = new Order();
        $order_model->setTenantId($tenant['id']);

        $order_data = [
            'tenant_id' => $tenant['id'],
            'branch_id' => $branch_id,
            'customer_id' => $customer_id,
            'order_number' => $order_model->generateOrderNumber(),
            'customer_name' => $input['customer_name'],
            'customer_phone' => $input['customer_phone'],
            'customer_email' => $input['customer_email'] ?? null,
            'delivery_address' => $input['delivery_address'] ?? null,
            'delivery_city' => $input['delivery_city'] ?? null,
            'order_type' => $input['order_type'],
            'status' => 'new',
            'payment_method' => $input['payment_method'] ?? 'cod',
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'discount' => 0,
            'tax' => $tax,
            'total' => $total,
            'notes' => $input['notes'] ?? null
        ];

        $order_id = $order_model->createOrderWithItems($order_data, $order_items);

        if ($order_id) {
            // Get created order
            $order = $order_model->getOrderWithDetails($order_id, $tenant['id']);

            // Update customer statistics
            $customer_model->updateStatistics($customer_id);

            // Return success response
            $this->jsonResponse([
                'success' => true,
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'status' => $order['status'],
                    'total' => $order['total'],
                    'created_at' => $order['created_at']
                ]
            ], 201);
        } else {
            $this->jsonResponse(['error' => 'Failed to create order'], 500);
        }
    }
}
