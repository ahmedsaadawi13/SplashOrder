<?php
// FILE: /app/services/PushNotificationService.php

namespace App\Services;

/**
 * Push Notification Service
 * Sends push notifications to mobile devices
 */
class PushNotificationService
{
    private $db;
    private $fcm_server_key;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->fcm_server_key = getenv('FCM_SERVER_KEY') ?: null;
    }

    /**
     * Create push notification
     *
     * @param array $data Notification data
     * @return int|false Notification ID
     */
    public function createNotification($data)
    {
        $sql = "INSERT INTO push_notifications
                (tenant_id, notification_type, title, body, data, target_type,
                 target_customer_id, target_segment_id, scheduled_at, status)
                VALUES (:tenant_id, :notification_type, :title, :body, :data, :target_type,
                        :target_customer_id, :target_segment_id, :scheduled_at, :status)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':notification_type' => $data['notification_type'],
            ':title' => $data['title'],
            ':body' => $data['body'],
            ':data' => json_encode($data['data'] ?? []),
            ':target_type' => $data['target_type'],
            ':target_customer_id' => $data['target_customer_id'] ?? null,
            ':target_segment_id' => $data['target_segment_id'] ?? null,
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':status' => $data['scheduled_at'] ? 'scheduled' : 'draft'
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Send push notification
     *
     * @param int $notification_id Notification ID
     * @return bool
     */
    public function sendNotification($notification_id)
    {
        $notification = $this->getNotification($notification_id);
        if (!$notification) {
            return false;
        }

        // Get target devices
        $devices = $this->getTargetDevices($notification);
        if (empty($devices)) {
            return false;
        }

        // Update status to sending
        $this->updateNotificationStatus($notification_id, 'sending');

        $sent_count = 0;
        $delivered_count = 0;

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $notification);

            // Create delivery record
            $status = $result ? 'sent' : 'failed';
            $this->createDeliveryRecord($notification_id, $device['id'], $status);

            if ($result) {
                $sent_count++;
                $delivered_count++;
            }
        }

        // Update notification stats
        $this->updateNotificationStats($notification_id, $sent_count, $delivered_count);
        $this->updateNotificationStatus($notification_id, 'sent');

        return true;
    }

    /**
     * Send to individual device
     *
     * @param array $device Device data
     * @param array $notification Notification data
     * @return bool
     */
    private function sendToDevice($device, $notification)
    {
        if (!$this->fcm_server_key) {
            // Mock send in development
            error_log("PUSH NOTIFICATION: {$notification['title']} to {$device['device_token']}");
            return true;
        }

        // Prepare FCM payload
        $payload = [
            'to' => $device['device_token'],
            'notification' => [
                'title' => $notification['title'],
                'body' => $notification['body'],
                'sound' => 'default'
            ],
            'data' => json_decode($notification['data'], true)
        ];

        // Send via FCM
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: key=' . $this->fcm_server_key
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $http_code === 200;
    }

    /**
     * Track notification open
     *
     * @param int $delivery_id Delivery ID
     * @return bool
     */
    public function trackOpen($delivery_id)
    {
        $sql = "UPDATE push_notification_deliveries
                SET status = 'opened',
                    opened_at = NOW()
                WHERE id = :delivery_id
                AND status != 'opened'";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':delivery_id' => $delivery_id]);

        // Update notification total_opened
        if ($result) {
            $delivery = $this->getDelivery($delivery_id);
            if ($delivery) {
                $sql = "UPDATE push_notifications
                        SET total_opened = total_opened + 1
                        WHERE id = :notification_id";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([':notification_id' => $delivery['notification_id']]);
            }
        }

        return $result;
    }

    /**
     * Get target devices for notification
     *
     * @param array $notification Notification data
     * @return array
     */
    private function getTargetDevices($notification)
    {
        $sql = "SELECT d.* FROM mobile_devices d";

        if ($notification['target_type'] === 'individual') {
            $sql .= " WHERE d.customer_id = :customer_id AND d.is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':customer_id' => $notification['target_customer_id']]);
        } elseif ($notification['target_type'] === 'segment') {
            $sql .= " JOIN customer_segment_members csm ON d.customer_id = csm.customer_id
                     WHERE csm.segment_id = :segment_id AND d.is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':segment_id' => $notification['target_segment_id']]);
        } else {
            // All customers for tenant
            $sql .= " JOIN customers c ON d.customer_id = c.id
                     WHERE c.tenant_id = :tenant_id AND d.is_active = 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tenant_id' => $notification['tenant_id']]);
        }

        return $stmt->fetchAll();
    }

    /**
     * Create delivery record
     *
     * @param int $notification_id Notification ID
     * @param int $device_id Device ID
     * @param string $status Status
     * @return bool
     */
    private function createDeliveryRecord($notification_id, $device_id, $status)
    {
        $sql = "INSERT INTO push_notification_deliveries
                (notification_id, device_id, status, sent_at)
                VALUES (:notification_id, :device_id, :status, NOW())";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':notification_id' => $notification_id,
            ':device_id' => $device_id,
            ':status' => $status
        ]);
    }

    /**
     * Update notification stats
     *
     * @param int $notification_id Notification ID
     * @param int $sent_count Sent count
     * @param int $delivered_count Delivered count
     * @return bool
     */
    private function updateNotificationStats($notification_id, $sent_count, $delivered_count)
    {
        $sql = "UPDATE push_notifications
                SET total_sent = :sent_count,
                    total_delivered = :delivered_count,
                    sent_at = NOW()
                WHERE id = :notification_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':notification_id' => $notification_id,
            ':sent_count' => $sent_count,
            ':delivered_count' => $delivered_count
        ]);
    }

    /**
     * Update notification status
     *
     * @param int $notification_id Notification ID
     * @param string $status Status
     * @return bool
     */
    private function updateNotificationStatus($notification_id, $status)
    {
        $sql = "UPDATE push_notifications
                SET status = :status
                WHERE id = :notification_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':notification_id' => $notification_id,
            ':status' => $status
        ]);
    }

    /**
     * Get notification by ID
     *
     * @param int $notification_id Notification ID
     * @return array|null
     */
    private function getNotification($notification_id)
    {
        $sql = "SELECT * FROM push_notifications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $notification_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get delivery by ID
     *
     * @param int $delivery_id Delivery ID
     * @return array|null
     */
    private function getDelivery($delivery_id)
    {
        $sql = "SELECT * FROM push_notification_deliveries WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $delivery_id]);

        return $stmt->fetch() ?: null;
    }
}
