<?php
// FILE: /app/services/PaymentService.php

namespace App\Services;

/**
 * Payment Service
 * Handles payment processing with Stripe
 * For production, install Stripe SDK: composer require stripe/stripe-php
 */
class PaymentService
{
    private $stripe_secret_key;
    private $stripe_publishable_key;
    private $currency;
    private $enabled;

    public function __construct()
    {
        $this->stripe_secret_key = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        $this->stripe_publishable_key = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
        $this->currency = $_ENV['CURRENCY'] ?? 'usd';
        $this->enabled = !empty($this->stripe_secret_key);

        // Initialize Stripe if available
        if ($this->enabled && class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->stripe_secret_key);
        }
    }

    /**
     * Create payment intent
     *
     * @param float $amount Amount in dollars
     * @param array $metadata Additional metadata
     * @return array|false
     */
    public function createPaymentIntent($amount, $metadata = [])
    {
        if (!$this->enabled) {
            return $this->createMockPaymentIntent($amount, $metadata);
        }

        try {
            // Convert to cents
            $amount_cents = (int)($amount * 100);

            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount_cents,
                'currency' => $this->currency,
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'status' => $intent->status,
                'amount' => $amount
            ];
        } catch (\Exception $e) {
            error_log("Stripe payment intent creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm payment
     *
     * @param string $payment_intent_id Payment intent ID
     * @return array|false
     */
    public function confirmPayment($payment_intent_id)
    {
        if (!$this->enabled) {
            return $this->confirmMockPayment($payment_intent_id);
        }

        try {
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

            return [
                'id' => $intent->id,
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency
            ];
        } catch (\Exception $e) {
            error_log("Stripe payment confirmation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process refund
     *
     * @param string $payment_intent_id Payment intent ID
     * @param float|null $amount Refund amount (null for full refund)
     * @return array|false
     */
    public function refundPayment($payment_intent_id, $amount = null)
    {
        if (!$this->enabled) {
            return $this->createMockRefund($payment_intent_id, $amount);
        }

        try {
            $refund_data = ['payment_intent' => $payment_intent_id];

            if ($amount !== null) {
                $refund_data['amount'] = (int)($amount * 100);
            }

            $refund = \Stripe\Refund::create($refund_data);

            return [
                'id' => $refund->id,
                'status' => $refund->status,
                'amount' => $refund->amount / 100
            ];
        } catch (\Exception $e) {
            error_log("Stripe refund failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create customer in Stripe
     *
     * @param array $customer_data Customer information
     * @return string|false Customer ID
     */
    public function createCustomer($customer_data)
    {
        if (!$this->enabled) {
            return 'cus_mock_' . uniqid();
        }

        try {
            $customer = \Stripe\Customer::create([
                'email' => $customer_data['email'] ?? null,
                'name' => $customer_data['name'] ?? null,
                'phone' => $customer_data['phone'] ?? null,
                'metadata' => [
                    'internal_customer_id' => $customer_data['id'] ?? null
                ]
            ]);

            return $customer->id;
        } catch (\Exception $e) {
            error_log("Stripe customer creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save payment method for customer
     *
     * @param string $customer_id Stripe customer ID
     * @param string $payment_method_id Payment method ID
     * @return bool
     */
    public function attachPaymentMethod($customer_id, $payment_method_id)
    {
        if (!$this->enabled) {
            return true;
        }

        try {
            $payment_method = \Stripe\PaymentMethod::retrieve($payment_method_id);
            $payment_method->attach(['customer' => $customer_id]);

            return true;
        } catch (\Exception $e) {
            error_log("Payment method attachment failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create subscription for recurring billing
     *
     * @param string $customer_id Stripe customer ID
     * @param string $price_id Stripe price ID
     * @return array|false
     */
    public function createSubscription($customer_id, $price_id)
    {
        if (!$this->enabled) {
            return [
                'id' => 'sub_mock_' . uniqid(),
                'status' => 'active',
                'current_period_end' => time() + (30 * 24 * 60 * 60)
            ];
        }

        try {
            $subscription = \Stripe\Subscription::create([
                'customer' => $customer_id,
                'items' => [['price' => $price_id]],
                'payment_behavior' => 'default_incomplete',
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_end' => $subscription->current_period_end,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null
            ];
        } catch (\Exception $e) {
            error_log("Stripe subscription creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel subscription
     *
     * @param string $subscription_id Subscription ID
     * @return bool
     */
    public function cancelSubscription($subscription_id)
    {
        if (!$this->enabled) {
            return true;
        }

        try {
            $subscription = \Stripe\Subscription::retrieve($subscription_id);
            $subscription->cancel();

            return true;
        } catch (\Exception $e) {
            error_log("Stripe subscription cancellation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get publishable key for frontend
     *
     * @return string
     */
    public function getPublishableKey()
    {
        return $this->stripe_publishable_key;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Webhook payload
     * @param string $signature Stripe signature
     * @return object|false
     */
    public function verifyWebhook($payload, $signature)
    {
        $webhook_secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';

        if (!$this->enabled || empty($webhook_secret)) {
            return false;
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhook_secret
            );

            return $event;
        } catch (\Exception $e) {
            error_log("Webhook verification failed: " . $e->getMessage());
            return false;
        }
    }

    // Mock methods for development/testing

    /**
     * Create mock payment intent
     *
     * @param float $amount Amount
     * @param array $metadata Metadata
     * @return array
     */
    private function createMockPaymentIntent($amount, $metadata)
    {
        return [
            'id' => 'pi_mock_' . uniqid(),
            'client_secret' => 'pi_mock_secret_' . uniqid(),
            'status' => 'requires_payment_method',
            'amount' => $amount,
            'metadata' => $metadata
        ];
    }

    /**
     * Confirm mock payment
     *
     * @param string $payment_intent_id Payment intent ID
     * @return array
     */
    private function confirmMockPayment($payment_intent_id)
    {
        return [
            'id' => $payment_intent_id,
            'status' => 'succeeded',
            'amount' => 0,
            'currency' => $this->currency
        ];
    }

    /**
     * Create mock refund
     *
     * @param string $payment_intent_id Payment intent ID
     * @param float|null $amount Amount
     * @return array
     */
    private function createMockRefund($payment_intent_id, $amount)
    {
        return [
            'id' => 're_mock_' . uniqid(),
            'status' => 'succeeded',
            'amount' => $amount ?? 0
        ];
    }
}
