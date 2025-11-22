<?php
// FILE: /app/controllers/Analytics.php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AnalyticsService;

/**
 * Analytics Controller
 * Handles analytics and reporting
 */
class Analytics extends Controller
{
    private $analytics_service;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->analytics_service = new AnalyticsService();
    }

    /**
     * Analytics dashboard
     */
    public function index()
    {
        $tenant_id = \Auth::getTenantId();

        // Get sales trends (default 30 days)
        $period = $_GET['period'] ?? '30days';
        $sales_trends = $this->analytics_service->getSalesTrends($tenant_id, $period);

        // Get peak hours
        $peak_hours = $this->analytics_service->getPeakHours($tenant_id);

        // Get customer retention
        $retention = $this->analytics_service->getCustomerRetention($tenant_id);

        // Get product performance
        $top_products = $this->analytics_service->getProductPerformance($tenant_id, 30, 10);

        // Get revenue forecast
        $forecast = $this->analytics_service->getRevenueForecast($tenant_id);

        $this->view('analytics/index', [
            'sales_trends' => $sales_trends,
            'peak_hours' => $peak_hours,
            'retention' => $retention,
            'top_products' => $top_products,
            'forecast' => $forecast,
            'period' => $period
        ]);
    }

    /**
     * Sales report
     */
    public function sales()
    {
        $tenant_id = \Auth::getTenantId();

        // Get period from query params
        $period = $_GET['period'] ?? '30days';
        $sales_data = $this->analytics_service->getSalesTrends($tenant_id, $period);

        // Calculate totals
        $total_orders = array_sum(array_column($sales_data, 'orders'));
        $total_revenue = array_sum(array_column($sales_data, 'revenue'));
        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

        $this->view('analytics/sales', [
            'sales_data' => $sales_data,
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'avg_order_value' => $avg_order_value,
            'period' => $period
        ]);
    }

    /**
     * Customer analytics
     */
    public function customers()
    {
        $tenant_id = \Auth::getTenantId();

        $retention = $this->analytics_service->getCustomerRetention($tenant_id);

        $this->view('analytics/customers', [
            'retention' => $retention
        ]);
    }

    /**
     * Product performance
     */
    public function products()
    {
        $tenant_id = \Auth::getTenantId();

        $days = $_GET['days'] ?? 30;
        $products = $this->analytics_service->getProductPerformance($tenant_id, $days, 20);

        $this->view('analytics/products', [
            'products' => $products,
            'days' => $days
        ]);
    }

    /**
     * Export report (CSV)
     */
    public function export()
    {
        $tenant_id = \Auth::getTenantId();
        $type = $_GET['type'] ?? 'sales';
        $period = $_GET['period'] ?? '30days';

        if ($type === 'sales') {
            $data = $this->analytics_service->getSalesTrends($tenant_id, $period);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Orders', 'Revenue', 'Avg Order Value']);

            foreach ($data as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['orders'],
                    $row['revenue'],
                    $row['avg_order_value']
                ]);
            }

            fclose($output);
            exit;
        }
    }
}
