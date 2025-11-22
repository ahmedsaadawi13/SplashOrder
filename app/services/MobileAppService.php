<?php
// FILE: /app/services/MobileAppService.php

namespace App\Services;

/**
 * Mobile App Service
 * Handles mobile app backend operations
 */
class MobileAppService
{
    private $db;
    private $push_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->push_service = new PushNotificationService();
    }

    /**
     * Register mobile device
     *
     * @param array $data Device data
     * @return int|false Device ID
     */
    public function registerDevice($data)
    {
        // Check if device already exists
        $existing = $this->getDeviceByToken($data['device_token']);

        if ($existing) {
            // Update existing device
            $sql = "UPDATE mobile_devices
                    SET customer_id = :customer_id,
                        device_model = :device_model,
                        os_version = :os_version,
                        app_version = :app_version,
                        is_active = 1,
                        last_active_at = NOW()
                    WHERE id = :device_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':device_id' => $existing['id'],
                ':customer_id' => $data['customer_id'] ?? null,
                ':device_model' => $data['device_model'] ?? null,
                ':os_version' => $data['os_version'] ?? null,
                ':app_version' => $data['app_version'] ?? null
            ]);

            return $existing['id'];
        }

        // Register new device
        $sql = "INSERT INTO mobile_devices
                (customer_id, device_type, device_token, device_model, os_version, app_version)
                VALUES (:customer_id, :device_type, :device_token, :device_model, :os_version, :app_version)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':customer_id' => $data['customer_id'] ?? null,
            ':device_type' => $data['device_type'],
            ':device_token' => $data['device_token'],
            ':device_model' => $data['device_model'] ?? null,
            ':os_version' => $data['os_version'] ?? null,
            ':app_version' => $data['app_version'] ?? null
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Start app session
     *
     * @param int $device_id Device ID
     * @param int|null $customer_id Customer ID
     * @param string $app_version App version
     * @return int|false Session ID
     */
    public function startSession($device_id, $customer_id = null, $app_version = null)
    {
        $sql = "INSERT INTO app_sessions
                (device_id, customer_id, app_version)
                VALUES (:device_id, :customer_id, :app_version)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':device_id' => $device_id,
            ':customer_id' => $customer_id,
            ':app_version' => $app_version
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * End app session
     *
     * @param int $session_id Session ID
     * @param int $screens_viewed Screens viewed
     * @param int $actions_performed Actions performed
     * @return bool
     */
    public function endSession($session_id, $screens_viewed = 0, $actions_performed = 0)
    {
        $sql = "UPDATE app_sessions
                SET session_end = NOW(),
                    duration_seconds = TIMESTAMPDIFF(SECOND, session_start, NOW()),
                    screens_viewed = :screens_viewed,
                    actions_performed = :actions_performed
                WHERE id = :session_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':session_id' => $session_id,
            ':screens_viewed' => $screens_viewed,
            ':actions_performed' => $actions_performed
        ]);
    }

    /**
     * Get app analytics
     *
     * @param int $tenant_id Tenant ID
     * @param int $days Days to analyze
     * @return array
     */
    public function getAppAnalytics($tenant_id, $days = 30)
    {
        $sql = "SELECT
                COUNT(DISTINCT s.device_id) as unique_devices,
                COUNT(DISTINCT s.customer_id) as unique_users,
                COUNT(*) as total_sessions,
                AVG(s.duration_seconds) as avg_session_duration,
                SUM(s.screens_viewed) as total_screens_viewed,
                SUM(s.actions_performed) as total_actions
                FROM app_sessions s
                JOIN mobile_devices d ON s.device_id = d.id
                JOIN customers c ON d.customer_id = c.id
                WHERE c.tenant_id = :tenant_id
                AND s.session_start >= DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':days' => $days
        ]);

        return $stmt->fetch();
    }

    /**
     * Get device by token
     *
     * @param string $device_token Device token
     * @return array|null
     */
    private function getDeviceByToken($device_token)
    {
        $sql = "SELECT * FROM mobile_devices WHERE device_token = :device_token";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':device_token' => $device_token]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get active devices for customer
     *
     * @param int $customer_id Customer ID
     * @return array
     */
    public function getCustomerDevices($customer_id)
    {
        $sql = "SELECT * FROM mobile_devices
                WHERE customer_id = :customer_id
                AND is_active = 1
                ORDER BY last_active_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':customer_id' => $customer_id]);

        return $stmt->fetchAll();
    }

    /**
     * Deactivate device
     *
     * @param int $device_id Device ID
     * @return bool
     */
    public function deactivateDevice($device_id)
    {
        $sql = "UPDATE mobile_devices
                SET is_active = 0
                WHERE id = :device_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':device_id' => $device_id]);
    }
}
