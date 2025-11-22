<?php
// FILE: /app/services/CurrencyService.php

namespace App\Services;

/**
 * Currency Service
 * Handles multi-currency support and conversions
 */
class CurrencyService
{
    private $db;
    private $cache_service;
    private $default_currency = 'USD';

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->cache_service = new CacheService();
    }

    /**
     * Convert amount between currencies
     *
     * @param float $amount Amount to convert
     * @param string $from_currency Source currency code
     * @param string $to_currency Target currency code
     * @return float
     */
    public function convert($amount, $from_currency, $to_currency)
    {
        if ($from_currency === $to_currency) {
            return $amount;
        }

        $from_rate = $this->getExchangeRate($from_currency);
        $to_rate = $this->getExchangeRate($to_currency);

        // Convert to base currency (USD) first, then to target
        $in_usd = $amount / $from_rate;
        return $in_usd * $to_rate;
    }

    /**
     * Get exchange rate for currency
     *
     * @param string $currency_code Currency code
     * @return float
     */
    public function getExchangeRate($currency_code)
    {
        // Check cache first
        $cache_key = "exchange_rate:{$currency_code}";
        $cached = $this->cache_service->get($cache_key);

        if ($cached !== null) {
            return (float)$cached;
        }

        $sql = "SELECT exchange_rate FROM currencies WHERE code = :code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $currency_code]);
        $result = $stmt->fetch();

        if (!$result) {
            return 1.0;
        }

        $rate = (float)$result['exchange_rate'];

        // Cache for 1 hour
        $this->cache_service->set($cache_key, $rate, 3600);

        return $rate;
    }

    /**
     * Update exchange rates from external API
     *
     * @return bool
     */
    public function updateExchangeRates()
    {
        // In production, fetch from API like OpenExchangeRates
        // For now, this is a placeholder

        // Example: $rates = $this->fetchFromAPI();

        // Update database
        // foreach ($rates as $code => $rate) {
        //     $this->updateRate($code, $rate);
        // }

        return true;
    }

    /**
     * Format amount with currency symbol
     *
     * @param float $amount Amount
     * @param string $currency_code Currency code
     * @param bool $symbol_before Symbol position
     * @return string
     */
    public function format($amount, $currency_code, $symbol_before = true)
    {
        $currency = $this->getCurrency($currency_code);
        if (!$currency) {
            return number_format($amount, 2);
        }

        $formatted_amount = number_format($amount, 2);
        $symbol = $currency['symbol'];

        return $symbol_before
            ? "{$symbol}{$formatted_amount}"
            : "{$formatted_amount} {$symbol}";
    }

    /**
     * Get currency information
     *
     * @param string $currency_code Currency code
     * @return array|null
     */
    public function getCurrency($currency_code)
    {
        $sql = "SELECT * FROM currencies WHERE code = :code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $currency_code]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get available currencies for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getTenantCurrencies($tenant_id)
    {
        $sql = "SELECT c.* FROM currencies c
                JOIN tenant_currencies tc ON c.code = tc.currency_code
                WHERE tc.tenant_id = :tenant_id
                AND tc.is_active = 1
                ORDER BY tc.is_default DESC, c.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Get default currency for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return string
     */
    public function getDefaultCurrency($tenant_id)
    {
        $sql = "SELECT currency_code FROM tenant_currencies
                WHERE tenant_id = :tenant_id
                AND is_default = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $result = $stmt->fetch();

        return $result ? $result['currency_code'] : $this->default_currency;
    }

    /**
     * Enable currency for tenant
     *
     * @param int $tenant_id Tenant ID
     * @param string $currency_code Currency code
     * @param bool $is_default Set as default
     * @return bool
     */
    public function enableCurrencyForTenant($tenant_id, $currency_code, $is_default = false)
    {
        // If setting as default, unset other defaults
        if ($is_default) {
            $sql = "UPDATE tenant_currencies
                    SET is_default = 0
                    WHERE tenant_id = :tenant_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':tenant_id' => $tenant_id]);
        }

        $sql = "INSERT INTO tenant_currencies (tenant_id, currency_code, is_default, is_active)
                VALUES (:tenant_id, :currency_code, :is_default, 1)
                ON DUPLICATE KEY UPDATE
                    is_default = :is_default2,
                    is_active = 1";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':currency_code' => $currency_code,
            ':is_default' => $is_default ? 1 : 0,
            ':is_default2' => $is_default ? 1 : 0
        ]);
    }
}
