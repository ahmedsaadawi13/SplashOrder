<?php
// FILE: /app/models/Invoice.php

namespace App\Models;

use App\Core\Model;

/**
 * Invoice Model
 * Represents billing invoices for subscriptions
 */
class Invoice extends Model
{
    protected $table = 'invoices';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'subscription_id', 'invoice_number', 'amount', 'tax',
                    'total', 'status', 'due_date', 'paid_at', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Generate invoice number
     *
     * @return string
     */
    public function generateInvoiceNumber()
    {
        return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get invoices by tenant
     *
     * @param int $tenant_id
     * @return array
     */
    public function getInvoicesByTenant($tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll([], 'created_at DESC');
    }

    /**
     * Get pending invoices
     *
     * @param int $tenant_id
     * @return array
     */
    public function getPendingInvoices($tenant_id)
    {
        $this->setTenantId($tenant_id);
        return $this->findAll(['status' => 'pending'], 'due_date ASC');
    }

    /**
     * Mark invoice as paid
     *
     * @param int $invoice_id
     * @return bool
     */
    public function markAsPaid($invoice_id)
    {
        return $this->update($invoice_id, [
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Find invoice by number
     *
     * @param string $invoice_number
     * @return array|null
     */
    public function findByInvoiceNumber($invoice_number)
    {
        return $this->findOne(['invoice_number' => $invoice_number]);
    }
}
