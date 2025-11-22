<?php
// FILE: /app/models/Coupon.php

namespace App\Models;

use App\Core\Model;

/**
 * Coupon Model
 * Represents discount coupons
 */
class Coupon extends Model
{
    protected $table = 'coupons';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'code', 'description', 'discount_type', 'discount_value',
                    'min_order_amount', 'max_discount', 'usage_limit', 'usage_count',
                    'valid_from', 'valid_until', 'is_active', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Find coupon by code
     *
     * @param string $code
     * @param int $tenant_id
     * @return array|null
     */
    public function findByCode($code, $tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findOne(['code' => strtoupper($code)]);
    }

    /**
     * Validate coupon
     *
     * @param string $code
     * @param int $tenant_id
     * @param float $order_amount
     * @return array Returns ['valid' => bool, 'message' => string, 'coupon' => array|null]
     */
    public function validateCoupon($code, $tenant_id, $order_amount)
    {
        $coupon = $this->findByCode($code, $tenant_id);

        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid coupon code', 'coupon' => null];
        }

        if (!$coupon['is_active']) {
            return ['valid' => false, 'message' => 'This coupon is no longer active', 'coupon' => null];
        }

        // Check validity dates
        $now = date('Y-m-d H:i:s');

        if ($coupon['valid_from'] && $now < $coupon['valid_from']) {
            return ['valid' => false, 'message' => 'This coupon is not yet valid', 'coupon' => null];
        }

        if ($coupon['valid_until'] && $now > $coupon['valid_until']) {
            return ['valid' => false, 'message' => 'This coupon has expired', 'coupon' => null];
        }

        // Check usage limit
        if ($coupon['usage_limit'] && $coupon['usage_count'] >= $coupon['usage_limit']) {
            return ['valid' => false, 'message' => 'This coupon has reached its usage limit', 'coupon' => null];
        }

        // Check minimum order amount
        if ($coupon['min_order_amount'] && $order_amount < $coupon['min_order_amount']) {
            return [
                'valid' => false,
                'message' => 'Minimum order amount of $' . number_format($coupon['min_order_amount'], 2) . ' required',
                'coupon' => null
            ];
        }

        return ['valid' => true, 'message' => 'Coupon is valid', 'coupon' => $coupon];
    }

    /**
     * Calculate discount amount
     *
     * @param array $coupon
     * @param float $order_amount
     * @return float
     */
    public function calculateDiscount($coupon, $order_amount)
    {
        if ($coupon['discount_type'] === 'percentage') {
            $discount = ($order_amount * $coupon['discount_value']) / 100;

            // Apply max discount if set
            if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else {
            // Fixed discount
            $discount = $coupon['discount_value'];
        }

        // Discount cannot exceed order amount
        if ($discount > $order_amount) {
            $discount = $order_amount;
        }

        return round($discount, 2);
    }

    /**
     * Increment usage count
     *
     * @param int $coupon_id
     * @return bool
     */
    public function incrementUsage($coupon_id)
    {
        $sql = "UPDATE {$this->table} SET usage_count = usage_count + 1 WHERE id = :id";
        $this->query($sql, [':id' => $coupon_id]);
        return true;
    }

    /**
     * Get active coupons
     *
     * @param int $tenant_id
     * @return array
     */
    public function getActiveCoupons($tenant_id)
    {
        $this->setTenantId($tenant_id);
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND is_active = 1
                AND (valid_until IS NULL OR valid_until >= NOW())
                ORDER BY created_at DESC";

        return $this->query($sql, [':tenant_id' => $tenant_id]);
    }
}
