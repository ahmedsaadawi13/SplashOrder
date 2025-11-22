<?php
// FILE: /app/models/CampaignDelivery.php

namespace App\Models;

use App\Core\Model;

/**
 * CampaignDelivery Model
 * Manages campaign delivery tracking
 */
class CampaignDelivery extends Model
{
    protected $table = 'campaign_deliveries';
    protected $fillable = [
        'campaign_id',
        'customer_id',
        'status',
        'sent_at',
        'opened',
        'opened_at',
        'clicked',
        'clicked_at'
    ];

    /**
     * Track open
     *
     * @param int $campaign_id Campaign ID
     * @param int $customer_id Customer ID
     * @return bool
     */
    public function trackOpen($campaign_id, $customer_id)
    {
        $sql = "UPDATE {$this->table}
                SET opened = 1,
                    opened_at = NOW()
                WHERE campaign_id = :campaign_id
                AND customer_id = :customer_id
                AND opened = 0";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':customer_id' => $customer_id
        ]);
    }

    /**
     * Track click
     *
     * @param int $campaign_id Campaign ID
     * @param int $customer_id Customer ID
     * @return bool
     */
    public function trackClick($campaign_id, $customer_id)
    {
        $sql = "UPDATE {$this->table}
                SET clicked = 1,
                    clicked_at = NOW()
                WHERE campaign_id = :campaign_id
                AND customer_id = :customer_id
                AND clicked = 0";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':campaign_id' => $campaign_id,
            ':customer_id' => $customer_id
        ]);
    }
}
