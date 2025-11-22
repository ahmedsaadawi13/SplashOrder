<?php
// FILE: /app/models/TwoFactorAuth.php

namespace App\Models;

use App\Core\Model;

/**
 * TwoFactorAuth Model
 * Manages 2FA settings
 */
class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_auth';
    protected $fillable = [
        'user_id',
        'method',
        'phone',
        'email',
        'secret',
        'backup_codes',
        'is_enabled',
        'enabled_at',
        'last_verified_at'
    ];

    /**
     * Get 2FA settings by user
     *
     * @param int $user_id User ID
     * @return array|null
     */
    public function getByUser($user_id)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Check if 2FA is enabled
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function isEnabled($user_id)
    {
        $settings = $this->getByUser($user_id);
        return $settings && $settings['is_enabled'] == 1;
    }
}
