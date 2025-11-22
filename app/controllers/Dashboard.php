<?php
// FILE: /app/controllers/Dashboard.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Order;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Customer;
use App\Models\Tenant;

/**
 * Dashboard Controller
 * Main dashboard for authenticated users
 */
class Dashboard extends Controller
{
    /**
     * Before filter
     */
    protected function before()
    {
        $this->requireAuth();
    }

    /**
     * Dashboard home page
     */
    public function indexAction()
    {
        $user_role = \Auth::user('role');
        $tenant_id = \Auth::tenantId();

        if ($user_role === 'platform_admin') {
            $this->platformAdminDashboard();
        } else {
            $this->tenantDashboard($tenant_id);
        }
    }

    /**
     * Platform admin dashboard
     */
    private function platformAdminDashboard()
    {
        $tenant_model = new Tenant();
        $order_model = new Order();

        $data = [
            'title' => 'Platform Dashboard - SplashOrder',
            'total_tenants' => $tenant_model->count(),
            'active_tenants' => $tenant_model->count(['status' => 'active']),
            'recent_tenants' => $tenant_model->findAll([], 'created_at DESC', 5)
        ];

        $this->renderWithLayout('dashboard/platform_admin.php', $data);
    }

    /**
     * Tenant dashboard
     */
    private function tenantDashboard($tenant_id)
    {
        $order_model = new Order();
        $branch_model = new Branch();
        $menu_item_model = new MenuItem();
        $customer_model = new Customer();
        $tenant_model = new Tenant();

        // Get today's statistics
        $today_stats = $order_model->getOrdersStats($tenant_id, 'today');

        // Get this month's statistics
        $month_stats = $order_model->getOrdersStats($tenant_id, 'month');

        // Get recent orders
        $recent_orders = $order_model->findAll([], 'created_at DESC', 10);
        $order_model->setTenantId($tenant_id);

        // Get orders by status
        $new_orders = $order_model->count(['status' => 'new']);
        $confirmed_orders = $order_model->count(['status' => 'confirmed']);
        $preparing_orders = $order_model->count(['status' => 'preparing']);
        $out_for_delivery_orders = $order_model->count(['status' => 'out_for_delivery']);

        // Get best selling items
        $best_selling = $order_model->getBestSellingItems($tenant_id, 5);

        // Get usage statistics
        $usage_stats = $tenant_model->getUsageStats($tenant_id);

        $data = [
            'title' => 'Dashboard - SplashOrder',
            'today_stats' => $today_stats,
            'month_stats' => $month_stats,
            'recent_orders' => $recent_orders,
            'new_orders' => $new_orders,
            'confirmed_orders' => $confirmed_orders,
            'preparing_orders' => $preparing_orders,
            'out_for_delivery_orders' => $out_for_delivery_orders,
            'best_selling' => $best_selling,
            'usage_stats' => $usage_stats,
            'total_branches' => $branch_model->count(),
            'total_menu_items' => $menu_item_model->count(),
            'total_customers' => $customer_model->count()
        ];

        $this->renderWithLayout('dashboard/index.php', $data);
    }
}
