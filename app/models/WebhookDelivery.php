<?php
// FILE: /app/models/WebhookDelivery.php

namespace App\Models;

use App\Core\Model;

/**
 * WebhookDelivery Model
 * Manages webhook delivery tracking
 */
class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';
    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'status',
        'response_code',
        'response_body',
        'retry_count',
        'next_retry_at',
        'delivered_at'
    ];

    /**
     * Get deliveries for webhook
     *
     * @param int $webhook_id Webhook ID
     * @param int $limit Limit
     * @return array
     */
    public function getByWebhook($webhook_id, $limit = 50)
    {
        $sql = "SELECT * FROM {$this->table}
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
     * Get failed deliveries for retry
     *
     * @param int $max_retries Max retries
     * @param int $limit Limit
     * @return array
     */
    public function getFailedForRetry($max_retries, $limit = 100)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'failed'
                AND retry_count < :max_retries
                AND next_retry_at <= NOW()
                ORDER BY next_retry_at ASC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':max_retries', $max_retries);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
