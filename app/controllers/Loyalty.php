<?php
// FILE: /app/controllers/Loyalty.php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\LoyaltyService;
use App\Models\CustomerLoyalty;

/**
 * Loyalty Controller
 * Handles loyalty program management
 */
class Loyalty extends Controller
{
    private $loyalty_service;
    private $loyalty_model;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->loyalty_service = new LoyaltyService();
        $this->loyalty_model = new CustomerLoyalty();
    }

    /**
     * Loyalty dashboard
     */
    public function index()
    {
        $tenant_id = \Auth::getTenantId();

        // Get loyalty program settings
        $sql = "SELECT * FROM loyalty_programs WHERE tenant_id = :tenant_id AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $program = $stmt->fetch();

        // Get top customers by tier
        $platinum_customers = $this->loyalty_model->getByTier($tenant_id, 'platinum', 10);
        $gold_customers = $this->loyalty_model->getByTier($tenant_id, 'gold', 10);

        $this->view('loyalty/index', [
            'program' => $program,
            'platinum_customers' => $platinum_customers,
            'gold_customers' => $gold_customers
        ]);
    }

    /**
     * View customer loyalty details
     */
    public function customer($customer_id)
    {
        $tenant_id = \Auth::getTenantId();

        $loyalty = $this->loyalty_service->getCustomerLoyalty($customer_id, $tenant_id);
        $transactions = $this->loyalty_service->getTransactionHistory($customer_id, $tenant_id);

        $this->view('loyalty/customer', [
            'loyalty' => $loyalty,
            'transactions' => $transactions
        ]);
    }

    /**
     * Award points manually
     */
    public function awardPoints()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $customer_id = $_POST['customer_id'];
            $points = (int)$_POST['points'];
            $tenant_id = \Auth::getTenantId();

            // Award points
            $result = $this->loyalty_service->awardPoints(
                $customer_id,
                $tenant_id,
                $points,
                null
            );

            if ($result) {
                $this->flash('success', 'Points awarded successfully');
            } else {
                $this->flash('error', 'Failed to award points');
            }

            $this->redirect('/loyalty/customer/' . $customer_id);
        }
    }

    /**
     * Redeem points
     */
    public function redeemPoints()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $customer_id = $_POST['customer_id'];
            $points = (int)$_POST['points'];
            $tenant_id = \Auth::getTenantId();

            $discount = $this->loyalty_service->redeemPoints($customer_id, $tenant_id, $points);

            if ($discount !== false) {
                $this->flash('success', "Points redeemed! Discount: $" . number_format($discount, 2));
            } else {
                $this->flash('error', 'Failed to redeem points');
            }

            $this->redirect('/loyalty/customer/' . $customer_id);
        }
    }
}
