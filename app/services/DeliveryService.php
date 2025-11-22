<?php
// FILE: /app/services/DeliveryService.php

namespace App\Services;

/**
 * Delivery Service
 * Manages delivery drivers, assignments, and tracking
 */
class DeliveryService
{
    private $db;
    private $realtime_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->realtime_service = new RealtimeService();
    }

    /**
     * Assign driver to order
     *
     * @param int $order_id Order ID
     * @param int $driver_id Driver ID
     * @param int $tenant_id Tenant ID
     * @return int|false Assignment ID or false
     */
    public function assignDriver($order_id, $driver_id, $tenant_id)
    {
        // Check driver availability
        if (!$this->isDriverAvailable($driver_id, $tenant_id)) {
            return false;
        }

        // Get order details
        $order = $this->getOrder($order_id, $tenant_id);
        if (!$order || $order['order_type'] !== 'delivery') {
            return false;
        }

        // Create assignment
        $sql = "INSERT INTO delivery_assignments
                (order_id, driver_id, tenant_id, status, assigned_at, estimated_pickup_time, estimated_delivery_time)
                VALUES (:order_id, :driver_id, :tenant_id, 'assigned', NOW(),
                        DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                        DATE_ADD(NOW(), INTERVAL 45 MINUTE))";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':order_id' => $order_id,
            ':driver_id' => $driver_id,
            ':tenant_id' => $tenant_id
        ]);

        if (!$result) {
            return false;
        }

        $assignment_id = $this->db->lastInsertId();

        // Update driver status
        $this->updateDriverStatus($driver_id, 'busy');

        // Update order status
        $this->updateOrderStatus($order_id, 'out_for_delivery');

        // Send real-time notification
        $this->realtime_service->broadcastOrderUpdate(
            $tenant_id,
            $order_id,
            'out_for_delivery',
            [
                'driver_id' => $driver_id,
                'assignment_id' => $assignment_id
            ]
        );

        // Notify driver
        $driver = $this->getDriver($driver_id);
        if ($driver && $driver['phone']) {
            $sms_service = new SmsService();
            $sms_service->send(
                $driver['phone'],
                "New delivery assigned! Order #{$order['order_number']} - {$order['delivery_address']}"
            );
        }

        return $assignment_id;
    }

    /**
     * Auto-assign driver to order
     *
     * @param int $order_id Order ID
     * @param int $tenant_id Tenant ID
     * @return int|false Assignment ID or false
     */
    public function autoAssignDriver($order_id, $tenant_id)
    {
        // Find nearest available driver
        $driver = $this->findNearestDriver($order_id, $tenant_id);
        if (!$driver) {
            return false;
        }

        return $this->assignDriver($order_id, $driver['id'], $tenant_id);
    }

    /**
     * Update driver location
     *
     * @param int $driver_id Driver ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @return bool
     */
    public function updateDriverLocation($driver_id, $latitude, $longitude)
    {
        $sql = "UPDATE delivery_drivers
                SET current_latitude = :latitude,
                    current_longitude = :longitude,
                    last_location_update = NOW()
                WHERE id = :driver_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':driver_id' => $driver_id,
            ':latitude' => $latitude,
            ':longitude' => $longitude
        ]);

        // Get current assignment
        $assignment = $this->getCurrentAssignment($driver_id);
        if ($assignment) {
            // Broadcast location update
            $this->realtime_service->broadcastDriverLocation(
                $assignment['tenant_id'],
                $assignment['order_id'],
                $driver_id,
                $latitude,
                $longitude
            );
        }

        return $result;
    }

    /**
     * Mark order as picked up
     *
     * @param int $assignment_id Assignment ID
     * @return bool
     */
    public function markPickedUp($assignment_id)
    {
        $sql = "UPDATE delivery_assignments
                SET status = 'picked_up',
                    actual_pickup_time = NOW()
                WHERE id = :assignment_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':assignment_id' => $assignment_id]);

        if ($result) {
            $assignment = $this->getAssignment($assignment_id);
            $this->updateOrderStatus($assignment['order_id'], 'out_for_delivery');

            // Send real-time update
            $this->realtime_service->broadcastOrderUpdate(
                $assignment['tenant_id'],
                $assignment['order_id'],
                'out_for_delivery',
                ['picked_up_at' => date('Y-m-d H:i:s')]
            );
        }

        return $result;
    }

    /**
     * Mark order as delivered
     *
     * @param int $assignment_id Assignment ID
     * @param string|null $delivery_notes Delivery notes
     * @return bool
     */
    public function markDelivered($assignment_id, $delivery_notes = null)
    {
        $sql = "UPDATE delivery_assignments
                SET status = 'delivered',
                    actual_delivery_time = NOW(),
                    delivery_notes = :notes
                WHERE id = :assignment_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':assignment_id' => $assignment_id,
            ':notes' => $delivery_notes
        ]);

        if ($result) {
            $assignment = $this->getAssignment($assignment_id);

            // Update order status
            $this->updateOrderStatus($assignment['order_id'], 'delivered');

            // Update driver status
            $this->updateDriverStatus($assignment['driver_id'], 'available');

            // Calculate delivery time
            $delivery_time = $this->calculateDeliveryTime($assignment_id);

            // Send real-time update
            $this->realtime_service->broadcastOrderUpdate(
                $assignment['tenant_id'],
                $assignment['order_id'],
                'delivered',
                [
                    'delivered_at' => date('Y-m-d H:i:s'),
                    'delivery_time_minutes' => $delivery_time
                ]
            );

            // Send notification to customer
            $order = $this->getOrder($assignment['order_id'], $assignment['tenant_id']);
            if ($order && $order['customer_phone']) {
                $sms_service = new SmsService();
                $sms_service->send(
                    $order['customer_phone'],
                    "Your order #{$order['order_number']} has been delivered! Thank you for your order."
                );
            }
        }

        return $result;
    }

    /**
     * Get driver performance stats
     *
     * @param int $driver_id Driver ID
     * @param int $days Days to analyze
     * @return array
     */
    public function getDriverStats($driver_id, $days = 30)
    {
        $sql = "SELECT
                COUNT(*) as total_deliveries,
                AVG(TIMESTAMPDIFF(MINUTE, assigned_at, actual_delivery_time)) as avg_delivery_time,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as successful_deliveries,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_deliveries
                FROM delivery_assignments
                WHERE driver_id = :driver_id
                AND assigned_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':driver_id' => $driver_id,
            ':days' => $days
        ]);

        return $stmt->fetch();
    }

    /**
     * Get available drivers
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getAvailableDrivers($tenant_id)
    {
        $sql = "SELECT * FROM delivery_drivers
                WHERE tenant_id = :tenant_id
                AND status = 'available'
                AND is_active = 1
                ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Get driver's current assignment
     *
     * @param int $driver_id Driver ID
     * @return array|null
     */
    public function getCurrentAssignment($driver_id)
    {
        $sql = "SELECT * FROM delivery_assignments
                WHERE driver_id = :driver_id
                AND status IN ('assigned', 'picked_up')
                ORDER BY assigned_at DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':driver_id' => $driver_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Calculate delivery time
     *
     * @param int $assignment_id Assignment ID
     * @return int|null Minutes
     */
    private function calculateDeliveryTime($assignment_id)
    {
        $sql = "SELECT TIMESTAMPDIFF(MINUTE, assigned_at, actual_delivery_time) as delivery_time
                FROM delivery_assignments
                WHERE id = :assignment_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':assignment_id' => $assignment_id]);
        $result = $stmt->fetch();

        return $result ? (int)$result['delivery_time'] : null;
    }

    /**
     * Find nearest available driver
     *
     * @param int $order_id Order ID
     * @param int $tenant_id Tenant ID
     * @return array|null
     */
    private function findNearestDriver($order_id, $tenant_id)
    {
        // Simple implementation - find first available driver
        // In production, use geolocation to find nearest driver
        $sql = "SELECT * FROM delivery_drivers
                WHERE tenant_id = :tenant_id
                AND status = 'available'
                AND is_active = 1
                ORDER BY id ASC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Check if driver is available
     *
     * @param int $driver_id Driver ID
     * @param int $tenant_id Tenant ID
     * @return bool
     */
    private function isDriverAvailable($driver_id, $tenant_id)
    {
        $sql = "SELECT status FROM delivery_drivers
                WHERE id = :driver_id
                AND tenant_id = :tenant_id
                AND is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':driver_id' => $driver_id,
            ':tenant_id' => $tenant_id
        ]);

        $result = $stmt->fetch();
        return $result && $result['status'] === 'available';
    }

    /**
     * Update driver status
     *
     * @param int $driver_id Driver ID
     * @param string $status Status
     * @return bool
     */
    private function updateDriverStatus($driver_id, $status)
    {
        $sql = "UPDATE delivery_drivers
                SET status = :status
                WHERE id = :driver_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':driver_id' => $driver_id,
            ':status' => $status
        ]);
    }

    /**
     * Update order status
     *
     * @param int $order_id Order ID
     * @param string $status Status
     * @return bool
     */
    private function updateOrderStatus($order_id, $status)
    {
        $sql = "UPDATE orders
                SET status = :status
                WHERE id = :order_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':order_id' => $order_id,
            ':status' => $status
        ]);
    }

    /**
     * Get order
     *
     * @param int $order_id Order ID
     * @param int $tenant_id Tenant ID
     * @return array|null
     */
    private function getOrder($order_id, $tenant_id)
    {
        $sql = "SELECT * FROM orders
                WHERE id = :order_id
                AND tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $order_id,
            ':tenant_id' => $tenant_id
        ]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get driver
     *
     * @param int $driver_id Driver ID
     * @return array|null
     */
    private function getDriver($driver_id)
    {
        $sql = "SELECT * FROM delivery_drivers WHERE id = :driver_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':driver_id' => $driver_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get assignment
     *
     * @param int $assignment_id Assignment ID
     * @return array|null
     */
    private function getAssignment($assignment_id)
    {
        $sql = "SELECT * FROM delivery_assignments WHERE id = :assignment_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':assignment_id' => $assignment_id]);

        return $stmt->fetch() ?: null;
    }
}
