<?php
// FILE: /app/models/Webhook.php

namespace App\Models;

use App\Core\Model;

/**
 * Webhook Model
 * Manages webhook subscriptions
 */
class Webhook extends Model
{
    protected $table = 'webhooks';
    protected $fillable = [
        'tenant_id',
        'url',
        'events',
        'secret',
        'is_active'
    ];

    /**
     * Get active webhooks for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getActive($tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND is_active = 1
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Get webhooks for event
     *
     * @param int $tenant_id Tenant ID
     * @param string $event Event name
     * @return array
     */
    public function getForEvent($tenant_id, $event)
    {
        $sql = "SELECT * FROM {$this->table}
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
}
