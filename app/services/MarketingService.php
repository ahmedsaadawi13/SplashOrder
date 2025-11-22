<?php
// FILE: /app/services/MarketingService.php

namespace App\Services;

/**
 * Marketing Service
 * Handles marketing campaigns, promotions, and customer engagement
 */
class MarketingService
{
    private $db;
    private $email_service;
    private $sms_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->email_service = new EmailService();
        $this->sms_service = new SmsService();
    }

    /**
     * Create marketing campaign
     *
     * @param array $data Campaign data
     * @return int|false Campaign ID
     */
    public function createCampaign($data)
    {
        $sql = "INSERT INTO marketing_campaigns
                (tenant_id, name, type, channel, subject, message, target_audience,
                 scheduled_at, status, created_by)
                VALUES (:tenant_id, :name, :type, :channel, :subject, :message,
                        :target_audience, :scheduled_at, 'draft', :created_by)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':name' => $data['name'],
            ':type' => $data['type'], // promotional, transactional, newsletter
            ':channel' => $data['channel'], // email, sms, both
            ':subject' => $data['subject'] ?? null,
            ':message' => $data['message'],
            ':target_audience' => json_encode($data['target_audience'] ?? []),
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':created_by' => $data['created_by']
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Send campaign
     *
     * @param int $campaign_id Campaign ID
     * @return array Result with sent count
     */
    public function sendCampaign($campaign_id)
    {
        // Get campaign
        $campaign = $this->getCampaign($campaign_id);
        if (!$campaign || $campaign['status'] === 'sent') {
            return ['success' => false, 'error' => 'Invalid campaign or already sent'];
        }

        // Get target customers
        $customers = $this->getTargetCustomers($campaign);
        if (empty($customers)) {
            return ['success' => false, 'error' => 'No customers match target audience'];
        }

        // Update campaign status
        $this->updateCampaignStatus($campaign_id, 'sending');

        $sent_count = 0;
        $failed_count = 0;

        foreach ($customers as $customer) {
            $result = false;

            // Send via email
            if (in_array($campaign['channel'], ['email', 'both']) && $customer['email']) {
                $result = $this->email_service->send(
                    $customer['email'],
                    $campaign['subject'],
                    $this->personalize($campaign['message'], $customer)
                );
                if ($result) $sent_count++;
            }

            // Send via SMS
            if (in_array($campaign['channel'], ['sms', 'both']) && $customer['phone']) {
                $result = $this->sms_service->send(
                    $customer['phone'],
                    $this->personalize($campaign['message'], $customer)
                );
                if ($result) $sent_count++;
            }

            // Log delivery
            $this->logCampaignDelivery($campaign_id, $customer['id'], $result ? 'sent' : 'failed');

            if (!$result) {
                $failed_count++;
            }
        }

        // Update campaign
        $this->updateCampaignStatus($campaign_id, 'sent');
        $this->updateCampaignStats($campaign_id, $sent_count, $failed_count);

        return [
            'success' => true,
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'total_recipients' => count($customers)
        ];
    }

    /**
     * Send automated birthday campaign
     *
     * @param int $tenant_id Tenant ID
     * @return int Sent count
     */
    public function sendBirthdayCampaigns($tenant_id)
    {
        // Get customers with birthday today
        $sql = "SELECT * FROM customers
                WHERE tenant_id = :tenant_id
                AND MONTH(birth_date) = MONTH(CURDATE())
                AND DAY(birth_date) = DAY(CURDATE())
                AND email IS NOT NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $customers = $stmt->fetchAll();

        $sent_count = 0;

        foreach ($customers as $customer) {
            $subject = "Happy Birthday, {$customer['name']}! 🎉";
            $message = "Happy Birthday, {$customer['name']}!\n\n";
            $message .= "Celebrate with us! Use code BIRTHDAY20 for 20% off your next order.\n\n";
            $message .= "Valid for 7 days.";

            if ($this->email_service->send($customer['email'], $subject, $message)) {
                $sent_count++;
            }
        }

        return $sent_count;
    }

    /**
     * Send win-back campaign to inactive customers
     *
     * @param int $tenant_id Tenant ID
     * @param int $days_inactive Days of inactivity
     * @return int Sent count
     */
    public function sendWinBackCampaign($tenant_id, $days_inactive = 30)
    {
        // Get inactive customers
        $sql = "SELECT c.* FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE c.tenant_id = :tenant_id
                AND c.email IS NOT NULL
                GROUP BY c.id
                HAVING MAX(o.created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
                OR MAX(o.created_at) IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':days' => $days_inactive
        ]);
        $customers = $stmt->fetchAll();

        $sent_count = 0;

        foreach ($customers as $customer) {
            $subject = "We Miss You! Come Back for a Special Offer";
            $message = "Hi {$customer['name']},\n\n";
            $message .= "We haven't seen you in a while and we miss you!\n\n";
            $message .= "Here's a special 25% discount just for you. Use code COMEBACK25.\n\n";
            $message .= "We'd love to serve you again!";

            if ($this->email_service->send($customer['email'], $subject, $message)) {
                $sent_count++;
            }
        }

        return $sent_count;
    }

    /**
     * Send order follow-up for reviews
     *
     * @param int $order_id Order ID
     * @return bool
     */
    public function sendReviewRequest($order_id)
    {
        // Get order and customer
        $sql = "SELECT o.*, c.name as customer_name, c.email as customer_email
                FROM orders o
                JOIN customers c ON o.customer_id = c.id
                WHERE o.id = :order_id
                AND o.status = 'delivered'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch();

        if (!$order || !$order['customer_email']) {
            return false;
        }

        $subject = "How was your order? Share your feedback!";
        $message = "Hi {$order['customer_name']},\n\n";
        $message .= "Thank you for your recent order ({$order['order_number']})!\n\n";
        $message .= "We'd love to hear your feedback. Please take a moment to rate your experience.\n\n";
        $message .= "[Review Link]\n\n";
        $message .= "Your feedback helps us improve!";

        return $this->email_service->send($order['customer_email'], $subject, $message);
    }

    /**
     * Send abandoned cart reminder
     *
     * @param int $tenant_id Tenant ID
     * @return int Sent count
     */
    public function sendAbandonedCartReminders($tenant_id)
    {
        // In a real implementation, you'd track cart sessions
        // For now, this is a placeholder
        return 0;
    }

    /**
     * Get campaign performance
     *
     * @param int $campaign_id Campaign ID
     * @return array
     */
    public function getCampaignPerformance($campaign_id)
    {
        $sql = "SELECT
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened,
                SUM(CASE WHEN clicked = 1 THEN 1 ELSE 0 END) as clicked
                FROM campaign_deliveries
                WHERE campaign_id = :campaign_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':campaign_id' => $campaign_id]);

        return $stmt->fetch();
    }

    /**
     * Track email open
     *
     * @param int $campaign_id Campaign ID
     * @param int $customer_id Customer ID
     * @return bool
     */
    public function trackOpen($campaign_id, $customer_id)
    {
        $sql = "UPDATE campaign_deliveries
                SET opened = 1,
                    opened_at = NOW()
                WHERE campaign_id = :campaign_id
                AND customer_id = :customer_id
                AND opened = 0";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':customer_id' => $customer_id
        ]);
    }

    /**
     * Track link click
     *
     * @param int $campaign_id Campaign ID
     * @param int $customer_id Customer ID
     * @return bool
     */
    public function trackClick($campaign_id, $customer_id)
    {
        $sql = "UPDATE campaign_deliveries
                SET clicked = 1,
                    clicked_at = NOW()
                WHERE campaign_id = :campaign_id
                AND customer_id = :customer_id
                AND clicked = 0";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':customer_id' => $customer_id
        ]);
    }

    /**
     * Get target customers based on audience criteria
     *
     * @param array $campaign Campaign data
     * @return array
     */
    private function getTargetCustomers($campaign)
    {
        $audience = json_decode($campaign['target_audience'], true);

        // Build dynamic query based on criteria
        $sql = "SELECT DISTINCT c.* FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE c.tenant_id = :tenant_id";

        $params = [':tenant_id' => $campaign['tenant_id']];

        // All customers
        if (isset($audience['segment']) && $audience['segment'] === 'all') {
            // No additional filters
        }

        // New customers
        if (isset($audience['segment']) && $audience['segment'] === 'new') {
            $sql .= " AND c.total_orders = 0";
        }

        // Returning customers
        if (isset($audience['segment']) && $audience['segment'] === 'returning') {
            $sql .= " AND c.total_orders > 0";
        }

        // VIP customers
        if (isset($audience['segment']) && $audience['segment'] === 'vip') {
            $sql .= " AND c.total_spent > 500";
        }

        // Inactive customers
        if (isset($audience['segment']) && $audience['segment'] === 'inactive') {
            $sql .= " GROUP BY c.id
                     HAVING MAX(o.created_at) < DATE_SUB(NOW(), INTERVAL 30 DAY)
                     OR MAX(o.created_at) IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Personalize message with customer data
     *
     * @param string $message Message template
     * @param array $customer Customer data
     * @return string
     */
    private function personalize($message, $customer)
    {
        $replacements = [
            '{name}' => $customer['name'],
            '{first_name}' => explode(' ', $customer['name'])[0],
            '{email}' => $customer['email'],
            '{phone}' => $customer['phone']
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Update campaign status
     *
     * @param int $campaign_id Campaign ID
     * @param string $status Status
     * @return bool
     */
    private function updateCampaignStatus($campaign_id, $status)
    {
        $sql = "UPDATE marketing_campaigns
                SET status = :status,
                    sent_at = " . ($status === 'sent' ? 'NOW()' : 'sent_at') . "
                WHERE id = :campaign_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':status' => $status
        ]);
    }

    /**
     * Update campaign stats
     *
     * @param int $campaign_id Campaign ID
     * @param int $sent_count Sent count
     * @param int $failed_count Failed count
     * @return bool
     */
    private function updateCampaignStats($campaign_id, $sent_count, $failed_count)
    {
        $sql = "UPDATE marketing_campaigns
                SET sent_count = :sent_count,
                    failed_count = :failed_count
                WHERE id = :campaign_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':sent_count' => $sent_count,
            ':failed_count' => $failed_count
        ]);
    }

    /**
     * Log campaign delivery
     *
     * @param int $campaign_id Campaign ID
     * @param int $customer_id Customer ID
     * @param string $status Status
     * @return bool
     */
    private function logCampaignDelivery($campaign_id, $customer_id, $status)
    {
        $sql = "INSERT INTO campaign_deliveries
                (campaign_id, customer_id, status, sent_at)
                VALUES (:campaign_id, :customer_id, :status, NOW())";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':customer_id' => $customer_id,
            ':status' => $status
        ]);
    }

    /**
     * Get campaign
     *
     * @param int $campaign_id Campaign ID
     * @return array|null
     */
    private function getCampaign($campaign_id)
    {
        $sql = "SELECT * FROM marketing_campaigns WHERE id = :campaign_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':campaign_id' => $campaign_id]);

        return $stmt->fetch() ?: null;
    }
}
