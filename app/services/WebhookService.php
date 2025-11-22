<?php
// FILE: /app/services/WebhookService.php

namespace App\Services;

/**
 * Webhook Service
 * Manages webhook subscriptions and deliveries
 */
class WebhookService
{
    private $db;

    // Supported events
    const EVENTS = [
        'order.created',
        'order.updated',
        'order.completed',
        'order.canceled',
        'payment.succeeded',
        'payment.failed',
        'customer.created',
        'customer.updated'
    ];

    // Retry configuration
    const MAX_RETRIES = 3;
    const RETRY_DELAYS = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Register webhook
     *
     * @param int $tenant_id Tenant ID
     * @param string $url Webhook URL
     * @param array $events Events to subscribe to
     * @param string|null $secret Webhook secret for verification
     * @return int|false Webhook ID
     */
    public function registerWebhook($tenant_id, $url, $events, $secret = null)
    {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Validate events
        foreach ($events as $event) {
            if (!in_array($event, self::EVENTS)) {
                return false;
            }
        }

        // Generate secret if not provided
        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
        }

        $sql = "INSERT INTO webhooks
                (tenant_id, url, events, secret, is_active)
                VALUES (:tenant_id, :url, :events, :secret, 1)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':url' => $url,
            ':events' => json_encode($events),
            ':secret' => $secret
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Trigger webhook event
     *
     * @param int $tenant_id Tenant ID
     * @param string $event Event name
     * @param array $payload Event data
     * @return int Number of webhooks triggered
     */
    public function trigger($tenant_id, $event, $payload)
    {
        // Get webhooks subscribed to this event
        $webhooks = $this->getWebhooksForEvent($tenant_id, $event);
        if (empty($webhooks)) {
            return 0;
        }

        $triggered = 0;

        foreach ($webhooks as $webhook) {
            // Create delivery record
            $delivery_id = $this->createDelivery($webhook['id'], $event, $payload);
            if ($delivery_id) {
                // Send webhook (async in production)
                $this->sendWebhook($delivery_id);
                $triggered++;
            }
        }

        return $triggered;
    }

    /**
     * Send webhook delivery
     *
     * @param int $delivery_id Delivery ID
     * @return bool
     */
    public function sendWebhook($delivery_id)
    {
        // Get delivery
        $delivery = $this->getDelivery($delivery_id);
        if (!$delivery) {
            return false;
        }

        // Get webhook
        $webhook = $this->getWebhook($delivery['webhook_id']);
        if (!$webhook || !$webhook['is_active']) {
            return false;
        }

        // Prepare payload
        $payload = json_decode($delivery['payload'], true);
        $payload['event'] = $delivery['event'];
        $payload['webhook_id'] = $webhook['id'];
        $payload['timestamp'] = time();

        // Generate signature
        $signature = $this->generateSignature($payload, $webhook['secret']);

        // Send HTTP request
        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: ' . $delivery['event'],
                'User-Agent: SplashOrder-Webhooks/1.0'
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Update delivery status
        if ($http_code >= 200 && $http_code < 300) {
            $this->markDeliverySuccess($delivery_id, $http_code, $response);
            return true;
        } else {
            $this->markDeliveryFailed($delivery_id, $http_code, $error ?: $response);

            // Schedule retry if not exceeded
            if ($delivery['retry_count'] < self::MAX_RETRIES) {
                $this->scheduleRetry($delivery_id);
            }

            return false;
        }
    }

    /**
     * Retry failed deliveries
     *
     * @return int Number of retries attempted
     */
    public function retryFailedDeliveries()
    {
        $sql = "SELECT * FROM webhook_deliveries
                WHERE status = 'failed'
                AND retry_count < :max_retries
                AND next_retry_at <= NOW()
                LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':max_retries' => self::MAX_RETRIES]);
        $deliveries = $stmt->fetchAll();

        $retried = 0;

        foreach ($deliveries as $delivery) {
            $this->sendWebhook($delivery['id']);
            $retried++;
        }

        return $retried;
    }

    /**
     * Verify webhook signature
     *
     * @param array $payload Payload data
     * @param string $signature Provided signature
     * @param string $secret Webhook secret
     * @return bool
     */
    public function verifySignature($payload, $signature, $secret)
    {
        $expected_signature = $this->generateSignature($payload, $secret);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Get webhook deliveries
     *
     * @param int $webhook_id Webhook ID
     * @param int $limit Limit
     * @return array
     */
    public function getDeliveries($webhook_id, $limit = 50)
    {
        $sql = "SELECT * FROM webhook_deliveries
                WHERE webhook_id = :webhook_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':webhook_id', $webhook_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Disable webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool
     */
    public function disableWebhook($webhook_id)
    {
        $sql = "UPDATE webhooks
                SET is_active = 0
                WHERE id = :webhook_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':webhook_id' => $webhook_id]);
    }

    /**
     * Enable webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool
     */
    public function enableWebhook($webhook_id)
    {
        $sql = "UPDATE webhooks
                SET is_active = 1
                WHERE id = :webhook_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':webhook_id' => $webhook_id]);
    }

    /**
     * Delete webhook
     *
     * @param int $webhook_id Webhook ID
     * @return bool
     */
    public function deleteWebhook($webhook_id)
    {
        $sql = "DELETE FROM webhooks WHERE id = :webhook_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':webhook_id' => $webhook_id]);
    }

    /**
     * Get webhooks for event
     *
     * @param int $tenant_id Tenant ID
     * @param string $event Event name
     * @return array
     */
    private function getWebhooksForEvent($tenant_id, $event)
    {
        $sql = "SELECT * FROM webhooks
                WHERE tenant_id = :tenant_id
                AND is_active = 1
                AND JSON_CONTAINS(events, :event)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':event' => json_encode($event)
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Create delivery record
     *
     * @param int $webhook_id Webhook ID
     * @param string $event Event name
     * @param array $payload Payload data
     * @return int|false Delivery ID
     */
    private function createDelivery($webhook_id, $event, $payload)
    {
        $sql = "INSERT INTO webhook_deliveries
                (webhook_id, event, payload, status, retry_count)
                VALUES (:webhook_id, :event, :payload, 'pending', 0)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':webhook_id' => $webhook_id,
            ':event' => $event,
            ':payload' => json_encode($payload)
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Mark delivery as successful
     *
     * @param int $delivery_id Delivery ID
     * @param int $http_code HTTP status code
     * @param string $response Response body
     * @return bool
     */
    private function markDeliverySuccess($delivery_id, $http_code, $response)
    {
        $sql = "UPDATE webhook_deliveries
                SET status = 'success',
                    response_code = :http_code,
                    response_body = :response,
                    delivered_at = NOW()
                WHERE id = :delivery_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':delivery_id' => $delivery_id,
            ':http_code' => $http_code,
            ':response' => substr($response, 0, 1000) // Limit response size
        ]);
    }

    /**
     * Mark delivery as failed
     *
     * @param int $delivery_id Delivery ID
     * @param int $http_code HTTP status code
     * @param string $error Error message
     * @return bool
     */
    private function markDeliveryFailed($delivery_id, $http_code, $error)
    {
        $sql = "UPDATE webhook_deliveries
                SET status = 'failed',
                    response_code = :http_code,
                    response_body = :error,
                    retry_count = retry_count + 1
                WHERE id = :delivery_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':delivery_id' => $delivery_id,
            ':http_code' => $http_code,
            ':error' => substr($error, 0, 1000)
        ]);
    }

    /**
     * Schedule retry
     *
     * @param int $delivery_id Delivery ID
     * @return bool
     */
    private function scheduleRetry($delivery_id)
    {
        $delivery = $this->getDelivery($delivery_id);
        if (!$delivery) {
            return false;
        }

        $retry_index = min($delivery['retry_count'], count(self::RETRY_DELAYS) - 1);
        $delay = self::RETRY_DELAYS[$retry_index];

        $sql = "UPDATE webhook_deliveries
                SET next_retry_at = DATE_ADD(NOW(), INTERVAL :delay SECOND)
                WHERE id = :delivery_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':delivery_id' => $delivery_id,
            ':delay' => $delay
        ]);
    }

    /**
     * Generate signature
     *
     * @param array $payload Payload data
     * @param string $secret Secret key
     * @return string
     */
    private function generateSignature($payload, $secret)
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Get webhook
     *
     * @param int $webhook_id Webhook ID
     * @return array|null
     */
    private function getWebhook($webhook_id)
    {
        $sql = "SELECT * FROM webhooks WHERE id = :webhook_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':webhook_id' => $webhook_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get delivery
     *
     * @param int $delivery_id Delivery ID
     * @return array|null
     */
    private function getDelivery($delivery_id)
    {
        $sql = "SELECT * FROM webhook_deliveries WHERE id = :delivery_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':delivery_id' => $delivery_id]);

        return $stmt->fetch() ?: null;
    }
}
