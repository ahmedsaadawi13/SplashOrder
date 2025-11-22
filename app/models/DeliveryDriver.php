<?php
// FILE: /app/models/DeliveryDriver.php

namespace App\Models;

use App\Core\Model;

/**
 * DeliveryDriver Model
 * Manages delivery drivers
 */
class DeliveryDriver extends Model
{
    protected $table = 'delivery_drivers';
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'vehicle_type',
        'vehicle_number',
        'license_number',
        'status',
        'current_latitude',
        'current_longitude',
        'last_location_update',
        'is_active'
    ];

    /**
     * Get available drivers
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getAvailable($tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND status = 'available'
                AND is_active = 1
                ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Update driver status
     *
     * @param int $driver_id Driver ID
     * @param string $status Status
     * @return bool
     */
    public function updateStatus($driver_id, $status)
    {
        return $this->update($driver_id, ['status' => $status]);
    }
}
