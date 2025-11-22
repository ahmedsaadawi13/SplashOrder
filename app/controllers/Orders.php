<?php
// FILE: /app/controllers/Orders.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Orders Controller
 * Manages orders for tenant admins and staff
 */
class Orders extends Controller
{
    /**
     * Before filter
     */
    protected function before()
    {
        $this->requireAuth();
        $this->requireRole(['tenant_admin', 'staff']);
    }

    /**
     * List all orders
     */
    public function indexAction()
    {
        $tenant_id = $this->getTenantId();
        $order_model = new Order();
        $order_model->setTenantId($tenant_id);

        // Get filters
        $status = \Request::query('status');
        $search = \Request::query('search');
        $page = max(1, (int)\Request::query('page', 1));
        $per_page = config('pagination.per_page');

        // Build conditions
        $conditions = [];
        if ($status) {
            $conditions['status'] = $status;
        }

        // Get orders
        $offset = ($page - 1) * $per_page;
        $orders = $order_model->findAll($conditions, 'created_at DESC', $per_page, $offset);

        // Get total count for pagination
        $total_orders = $order_model->count($conditions);
        $total_pages = ceil($total_orders / $per_page);

        $this->renderWithLayout('orders/index.php', [
            'title' => 'Orders - SplashOrder',
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_orders' => $total_orders
        ]);
    }

    /**
     * View order details
     */
    public function viewAction()
    {
        $tenant_id = $this->getTenantId();
        $order_id = $this->route_params['id'] ?? null;

        if (!$order_id) {
            \Session::setFlash('error', 'Invalid order ID.');
            $this->redirect('/orders');
        }

        $order_model = new Order();
        $order = $order_model->getOrderWithDetails($order_id, $tenant_id);

        if (!$order) {
            \Session::setFlash('error', 'Order not found.');
            $this->redirect('/orders');
        }

        $this->renderWithLayout('orders/view.php', [
            'title' => 'Order #' . $order['order_number'] . ' - SplashOrder',
            'order' => $order
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatusAction()
    {
        $tenant_id = $this->getTenantId();
        $order_id = $this->route_params['id'] ?? null;

        if (!\Request::isPost() || !$order_id) {
            $this->redirect('/orders');
        }

        // Verify CSRF token
        if (!$this->verifyCsrfToken()) {
            \Session::setFlash('error', 'Invalid request.');
            $this->redirect('/orders/view/' . $order_id);
        }

        $new_status = \Request::post('status');

        $allowed_statuses = ['new', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'canceled'];
        if (!in_array($new_status, $allowed_statuses)) {
            \Session::setFlash('error', 'Invalid status.');
            $this->redirect('/orders/view/' . $order_id);
        }

        $order_model = new Order();
        $order_model->setTenantId($tenant_id);

        if ($order_model->updateStatus($order_id, $new_status)) {
            \Session::setFlash('success', 'Order status updated successfully.');
        } else {
            \Session::setFlash('error', 'Failed to update order status.');
        }

        $this->redirect('/orders/view/' . $order_id);
    }
}
