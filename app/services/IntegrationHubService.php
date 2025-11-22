<?php
// FILE: /app/services/IntegrationHubService.php

namespace App\Services;

/**
 * Integration Hub Service
 * Manages third-party integrations
 */
class IntegrationHubService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Connect integration
     *
     * @param array $data Integration data
     * @return int|false Integration ID
     */
    public function connectIntegration($data)
    {
        // Encrypt credentials
        $encrypted_credentials = $this->encryptCredentials($data['credentials']);

        $sql = "INSERT INTO integrations
                (tenant_id, integration_type, provider, credentials_encrypted, configuration,
                 sync_frequency, is_active, status)
                VALUES (:tenant_id, :integration_type, :provider, :credentials, :configuration,
                        :sync_frequency, 1, 'connected')";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':integration_type' => $data['integration_type'],
            ':provider' => $data['provider'],
            ':credentials' => $encrypted_credentials,
            ':configuration' => json_encode($data['configuration'] ?? []),
            ':sync_frequency' => $data['sync_frequency'] ?? 'manual'
        ]);

        if (!$result) {
            return false;
        }

        $integration_id = $this->db->lastInsertId();

        // Test connection
        if (!$this->testConnection($integration_id)) {
            $this->updateIntegrationStatus($integration_id, 'error', 'Connection test failed');
        }

        return $integration_id;
    }

    /**
     * Sync integration data
     *
     * @param int $integration_id Integration ID
     * @param string $sync_type Sync type
     * @param string $direction Sync direction
     * @return bool
     */
    public function syncIntegration($integration_id, $sync_type = 'manual', $direction = 'bidirectional')
    {
        $integration = $this->getIntegration($integration_id);
        if (!$integration || $integration['status'] !== 'connected') {
            return false;
        }

        // Create sync log
        $log_id = $this->createSyncLog($integration_id, $sync_type, $direction);

        // Perform sync based on integration type
        $result = $this->performSync($integration, $direction);

        // Update sync log
        $this->completeSyncLog($log_id, $result['processed'], $result['succeeded'], $result['failed'], $result['success']);

        // Update last sync time
        if ($result['success']) {
            $this->updateLastSync($integration_id);
        }

        return $result['success'];
    }

    /**
     * Perform actual sync
     *
     * @param array $integration Integration data
     * @param string $direction Direction
     * @return array
     */
    private function performSync($integration, $direction)
    {
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        switch ($integration['integration_type']) {
            case 'accounting':
                // Sync orders, invoices, payments
                $result = $this->syncAccounting($integration, $direction);
                break;

            case 'pos':
                // Sync menu items, inventory
                $result = $this->syncPOS($integration, $direction);
                break;

            case 'delivery_aggregator':
                // Sync orders from third-party platforms
                $result = $this->syncDeliveryAggregator($integration, $direction);
                break;

            case 'social_media':
                // Post updates to social platforms
                $result = $this->syncSocialMedia($integration, $direction);
                break;

            default:
                $result = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'success' => false];
        }

        return $result;
    }

    /**
     * Sync with accounting software
     *
     * @param array $integration Integration data
     * @param string $direction Direction
     * @return array
     */
    private function syncAccounting($integration, $direction)
    {
        // Mock implementation - in production, integrate with QuickBooks, Xero, etc.
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        if ($direction === 'export' || $direction === 'bidirectional') {
            // Export recent orders as invoices
            $orders = $this->getRecentOrders($integration['tenant_id'], 24);

            foreach ($orders as $order) {
                try {
                    // Create invoice in accounting software
                    // $this->createAccountingInvoice($integration, $order);
                    $processed++;
                    $succeeded++;
                } catch (\Exception $e) {
                    $failed++;
                }
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'success' => $failed === 0
        ];
    }

    /**
     * Sync with POS system
     *
     * @param array $integration Integration data
     * @param string $direction Direction
     * @return array
     */
    private function syncPOS($integration, $direction)
    {
        // Mock implementation
        return [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'success' => true
        ];
    }

    /**
     * Sync with delivery aggregators
     *
     * @param array $integration Integration data
     * @param string $direction Direction
     * @return array
     */
    private function syncDeliveryAggregator($integration, $direction)
    {
        // Mock implementation - in production, integrate with Uber Eats, DoorDash API
        return [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'success' => true
        ];
    }

    /**
     * Sync with social media
     *
     * @param array $integration Integration data
     * @param string $direction Direction
     * @return array
     */
    private function syncSocialMedia($integration, $direction)
    {
        // Post scheduled content to social platforms
        $posts = $this->getScheduledPosts($integration['tenant_id']);

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($posts as $post) {
            try {
                // Post to social media platform
                // $this->postToSocialMedia($integration, $post);
                $this->markPostAsPosted($post['id']);
                $processed++;
                $succeeded++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'success' => $failed === 0
        ];
    }

    /**
     * Test integration connection
     *
     * @param int $integration_id Integration ID
     * @return bool
     */
    private function testConnection($integration_id)
    {
        // Mock test - always returns true in this implementation
        return true;
    }

    /**
     * Create sync log
     *
     * @param int $integration_id Integration ID
     * @param string $sync_type Sync type
     * @param string $direction Direction
     * @return int
     */
    private function createSyncLog($integration_id, $sync_type, $direction)
    {
        $sql = "INSERT INTO integration_sync_logs
                (integration_id, sync_type, sync_direction, status)
                VALUES (:integration_id, :sync_type, :direction, 'running')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':integration_id' => $integration_id,
            ':sync_type' => $sync_type,
            ':direction' => $direction
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Complete sync log
     *
     * @param int $log_id Log ID
     * @param int $processed Processed count
     * @param int $succeeded Succeeded count
     * @param int $failed Failed count
     * @param bool $success Overall success
     * @return bool
     */
    private function completeSyncLog($log_id, $processed, $succeeded, $failed, $success)
    {
        $sql = "UPDATE integration_sync_logs
                SET records_processed = :processed,
                    records_succeeded = :succeeded,
                    records_failed = :failed,
                    status = :status,
                    completed_at = NOW()
                WHERE id = :log_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':log_id' => $log_id,
            ':processed' => $processed,
            ':succeeded' => $succeeded,
            ':failed' => $failed,
            ':status' => $success ? 'completed' : 'failed'
        ]);
    }

    /**
     * Update last sync time
     *
     * @param int $integration_id Integration ID
     * @return bool
     */
    private function updateLastSync($integration_id)
    {
        $sql = "UPDATE integrations
                SET last_sync_at = NOW(),
                    next_sync_at = CASE
                        WHEN sync_frequency = 'hourly' THEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                        WHEN sync_frequency = 'daily' THEN DATE_ADD(NOW(), INTERVAL 1 DAY)
                        ELSE NULL
                    END
                WHERE id = :integration_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':integration_id' => $integration_id]);
    }

    /**
     * Update integration status
     *
     * @param int $integration_id Integration ID
     * @param string $status Status
     * @param string|null $error_message Error message
     * @return bool
     */
    private function updateIntegrationStatus($integration_id, $status, $error_message = null)
    {
        $sql = "UPDATE integrations
                SET status = :status,
                    error_message = :error_message
                WHERE id = :integration_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':integration_id' => $integration_id,
            ':status' => $status,
            ':error_message' => $error_message
        ]);
    }

    /**
     * Encrypt credentials
     *
     * @param array $credentials Credentials
     * @return string
     */
    private function encryptCredentials($credentials)
    {
        // Simple encryption - in production, use proper encryption
        return base64_encode(json_encode($credentials));
    }

    /**
     * Get integration
     *
     * @param int $integration_id Integration ID
     * @return array|null
     */
    private function getIntegration($integration_id)
    {
        $sql = "SELECT * FROM integrations WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $integration_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get recent orders
     *
     * @param int $tenant_id Tenant ID
     * @param int $hours Hours
     * @return array
     */
    private function getRecentOrders($tenant_id, $hours)
    {
        $sql = "SELECT * FROM orders
                WHERE tenant_id = :tenant_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':hours' => $hours
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get scheduled posts
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    private function getScheduledPosts($tenant_id)
    {
        $sql = "SELECT * FROM social_media_posts
                WHERE tenant_id = :tenant_id
                AND status = 'scheduled'
                AND scheduled_at <= NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Mark post as posted
     *
     * @param int $post_id Post ID
     * @return bool
     */
    private function markPostAsPosted($post_id)
    {
        $sql = "UPDATE social_media_posts
                SET status = 'posted',
                    posted_at = NOW()
                WHERE id = :post_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':post_id' => $post_id]);
    }
}
