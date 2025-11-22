<?php
// FILE: /app/models/MarketingCampaign.php

namespace App\Models;

use App\Core\Model;

/**
 * MarketingCampaign Model
 * Manages marketing campaigns
 */
class MarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'channel',
        'subject',
        'message',
        'target_audience',
        'scheduled_at',
        'status',
        'sent_at',
        'sent_count',
        'failed_count',
        'created_by'
    ];

    /**
     * Get campaigns by status
     *
     * @param int $tenant_id Tenant ID
     * @param string $status Status
     * @return array
     */
    public function getByStatus($tenant_id, $status)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND status = :status
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':status' => $status
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Get scheduled campaigns
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getScheduled($tenant_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND status = 'scheduled'
                AND scheduled_at <= NOW()
                ORDER BY scheduled_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }
}
