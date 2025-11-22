<?php
// FILE: /app/services/LoyaltyService.php

namespace App\Services;

use App\Models\CustomerLoyalty;
use App\Models\LoyaltyTransaction;

/**
 * Loyalty Service
 * Handles customer loyalty and rewards program
 */
class LoyaltyService
{
    private $loyalty_model;
    private $transaction_model;
    private $email_service;

    public function __construct()
    {
        $this->loyalty_model = new CustomerLoyalty();
        $this->transaction_model = new LoyaltyTransaction();
        $this->email_service = new EmailService();
    }

    /**
     * Award points for an order
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @param float $order_amount Order total
     * @param int $order_id Order ID
     * @return int Points earned
     */
    public function awardPoints($customer_id, $tenant_id, $order_amount, $order_id)
    {
        // Get loyalty program settings
        $program = $this->getLoyaltyProgram($tenant_id);
        if (!$program || !$program['is_active']) {
            return 0;
        }

        // Calculate points
        $points = (int)floor($order_amount * $program['points_per_dollar']);

        if ($points <= 0) {
            return 0;
        }

        // Get or create customer loyalty record
        $loyalty = $this->getOrCreateLoyalty($customer_id, $tenant_id);

        // Update points
        $new_balance = $loyalty['points_balance'] + $points;
        $new_lifetime = $loyalty['points_earned_lifetime'] + $points;

        $this->loyalty_model->update($loyalty['id'], [
            'points_balance' => $new_balance,
            'points_earned_lifetime' => $new_lifetime,
            'tier' => $this->calculateTier($new_lifetime)
        ]);

        // Record transaction
        $this->transaction_model->insert([
            'customer_loyalty_id' => $loyalty['id'],
            'type' => 'earned',
            'points' => $points,
            'order_id' => $order_id,
            'description' => "Points earned from order",
            'balance_after' => $new_balance
        ]);

        // Send notification
        $customer = $this->getCustomer($customer_id);
        $this->email_service->sendLoyaltyPointsNotification($customer, $points, $new_balance);

        return $points;
    }

    /**
     * Redeem points
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @param int $points Points to redeem
     * @param int|null $order_id Order ID
     * @return float|false Discount amount or false if failed
     */
    public function redeemPoints($customer_id, $tenant_id, $points, $order_id = null)
    {
        $program = $this->getLoyaltyProgram($tenant_id);
        if (!$program) {
            return false;
        }

        // Check minimum redemption
        if ($points < $program['min_points_redemption']) {
            return false;
        }

        $loyalty = $this->getOrCreateLoyalty($customer_id, $tenant_id);

        // Check sufficient balance
        if ($loyalty['points_balance'] < $points) {
            return false;
        }

        // Calculate discount amount
        $discount = $points * $program['dollars_per_point'];

        // Update points
        $new_balance = $loyalty['points_balance'] - $points;
        $new_redeemed = $loyalty['points_redeemed_lifetime'] + $points;

        $this->loyalty_model->update($loyalty['id'], [
            'points_balance' => $new_balance,
            'points_redeemed_lifetime' => $new_redeemed
        ]);

        // Record transaction
        $this->transaction_model->insert([
            'customer_loyalty_id' => $loyalty['id'],
            'type' => 'redeemed',
            'points' => -$points,
            'order_id' => $order_id,
            'description' => "Points redeemed for $" . number_format($discount, 2),
            'balance_after' => $new_balance
        ]);

        return $discount;
    }

    /**
     * Get customer loyalty info
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @return array|null
     */
    public function getCustomerLoyalty($customer_id, $tenant_id)
    {
        return $this->getOrCreateLoyalty($customer_id, $tenant_id);
    }

    /**
     * Get loyalty transactions history
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @param int $limit Limit
     * @return array
     */
    public function getTransactionHistory($customer_id, $tenant_id, $limit = 50)
    {
        $loyalty = $this->getOrCreateLoyalty($customer_id, $tenant_id);

        $sql = "SELECT * FROM loyalty_transactions
                WHERE customer_loyalty_id = :loyalty_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':loyalty_id', $loyalty['id']);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Calculate tier based on lifetime points
     *
     * @param int $lifetime_points Lifetime points earned
     * @return string
     */
    private function calculateTier($lifetime_points)
    {
        if ($lifetime_points >= 10000) {
            return 'platinum';
        } elseif ($lifetime_points >= 5000) {
            return 'gold';
        } elseif ($lifetime_points >= 1000) {
            return 'silver';
        }
        return 'bronze';
    }

    /**
     * Get or create customer loyalty record
     *
     * @param int $customer_id Customer ID
     * @param int $tenant_id Tenant ID
     * @return array
     */
    private function getOrCreateLoyalty($customer_id, $tenant_id)
    {
        $sql = "SELECT * FROM customer_loyalty
                WHERE customer_id = :customer_id AND tenant_id = :tenant_id LIMIT 1";

        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([':customer_id' => $customer_id, ':tenant_id' => $tenant_id]);
        $loyalty = $stmt->fetch();

        if (!$loyalty) {
            // Create new loyalty record
            $loyalty_id = $this->loyalty_model->insert([
                'customer_id' => $customer_id,
                'tenant_id' => $tenant_id,
                'points_balance' => 0,
                'points_earned_lifetime' => 0,
                'points_redeemed_lifetime' => 0,
                'tier' => 'bronze'
            ]);

            $loyalty = $this->loyalty_model->findById($loyalty_id);
        }

        return $loyalty;
    }

    /**
     * Get loyalty program for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return array|null
     */
    private function getLoyaltyProgram($tenant_id)
    {
        $sql = "SELECT * FROM loyalty_programs WHERE tenant_id = :tenant_id AND is_active = 1 LIMIT 1";

        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get customer data
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    private function getCustomer($customer_id)
    {
        $sql = "SELECT * FROM customers WHERE id = :id LIMIT 1";

        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $customer_id]);

        return $stmt->fetch();
    }
}
