<?php
// FILE: /app/models/DeliveryAssignment.php

namespace App\Models;

use App\Core\Model;

/**
 * DeliveryAssignment Model
 * Manages delivery assignments
 */
class DeliveryAssignment extends Model
{
    protected $table = 'delivery_assignments';
    protected $fillable = [
        'order_id',
        'driver_id',
        'tenant_id',
        'status',
        'assigned_at',
        'estimated_pickup_time',
        'estimated_delivery_time',
        'actual_pickup_time',
        'actual_delivery_time',
        'delivery_notes'
    ];

    /**
     * Get assignment by order
     *
     * @param int $order_id Order ID
     * @return array|null
     */
    public function getByOrder($order_id)
    {
        $sql = "SELECT da.*, dd.name as driver_name, dd.phone as driver_phone
                FROM {$this->table} da
                JOIN delivery_drivers dd ON da.driver_id = dd.id
                WHERE da.order_id = :order_id
                ORDER BY da.assigned_at DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $order_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get current assignment for driver
     *
     * @param int $driver_id Driver ID
     * @return array|null
     */
    public function getCurrentByDriver($driver_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE driver_id = :driver_id
                AND status IN ('assigned', 'picked_up')
                ORDER BY assigned_at DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':driver_id' => $driver_id]);

        return $stmt->fetch() ?: null;
    }
}
