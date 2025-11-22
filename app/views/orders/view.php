<!-- FILE: /app/views/orders/view.php -->
<div class="order-view-page">
    <div class="page-header">
        <h1>Order #<?php echo e($order['order_number']); ?></h1>
        <a href="<?php echo url('/orders'); ?>" class="btn btn-secondary">Back to Orders</a>
    </div>

    <div class="order-view-grid">
        <div class="order-info-section">
            <h2>Order Information</h2>

            <div class="info-grid">
                <div class="info-item">
                    <label>Order Number:</label>
                    <div><?php echo e($order['order_number']); ?></div>
                </div>

                <div class="info-item">
                    <label>Order Date:</label>
                    <div><?php echo formatDate($order['created_at'], 'M d, Y H:i:s'); ?></div>
                </div>

                <div class="info-item">
                    <label>Status:</label>
                    <div><span class="badge badge-<?php echo e($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></div>
                </div>

                <div class="info-item">
                    <label>Order Type:</label>
                    <div><?php echo ucfirst($order['order_type']); ?></div>
                </div>

                <div class="info-item">
                    <label>Payment Method:</label>
                    <div><?php echo strtoupper($order['payment_method']); ?></div>
                </div>

                <div class="info-item">
                    <label>Payment Status:</label>
                    <div><span class="badge badge-<?php echo e($order['payment_status']); ?>"><?php echo ucfirst($order['payment_status']); ?></span></div>
                </div>
            </div>

            <h3>Customer Details</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Name:</label>
                    <div><?php echo e($order['customer_name']); ?></div>
                </div>

                <div class="info-item">
                    <label>Phone:</label>
                    <div><?php echo e($order['customer_phone']); ?></div>
                </div>

                <?php if ($order['customer_email']): ?>
                    <div class="info-item">
                        <label>Email:</label>
                        <div><?php echo e($order['customer_email']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($order['order_type'] === 'delivery' && $order['delivery_address']): ?>
                    <div class="info-item">
                        <label>Delivery Address:</label>
                        <div><?php echo e($order['delivery_address']); ?>, <?php echo e($order['delivery_city']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($order['notes']): ?>
                    <div class="info-item">
                        <label>Notes:</label>
                        <div><?php echo e($order['notes']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Update Status Form -->
            <div class="status-update-section">
                <h3>Update Status</h3>
                <form method="POST" action="<?php echo url('/orders/update-status/' . $order['id']); ?>" class="status-form">
                    <?php echo Csrf::field(); ?>

                    <div class="form-row">
                        <select name="status" required>
                            <option value="new" <?php echo ($order['status'] === 'new') ? 'selected' : ''; ?>>New</option>
                            <option value="confirmed" <?php echo ($order['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="preparing" <?php echo ($order['status'] === 'preparing') ? 'selected' : ''; ?>>Preparing</option>
                            <option value="out_for_delivery" <?php echo ($order['status'] === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                            <option value="delivered" <?php echo ($order['status'] === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                            <option value="canceled" <?php echo ($order['status'] === 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="order-items-section">
            <h2>Order Items</h2>

            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td>
                                <?php echo e($item['item_name']); ?>
                                <?php if ($item['options_text']): ?>
                                    <br><small class="text-muted"><?php echo e($item['options_text']); ?></small>
                                <?php endif; ?>
                                <?php if ($item['notes']): ?>
                                    <br><small class="text-info">Note: <?php echo e($item['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo currency($item['unit_price']); ?></td>
                            <td><?php echo currency($item['total_price']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3"><strong>Subtotal</strong></td>
                        <td><?php echo currency($order['subtotal']); ?></td>
                    </tr>
                    <?php if ($order['delivery_fee'] > 0): ?>
                        <tr>
                            <td colspan="3">Delivery Fee</td>
                            <td><?php echo currency($order['delivery_fee']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order['discount'] > 0): ?>
                        <tr>
                            <td colspan="3">Discount <?php if($order['coupon_code']): ?>(<?php echo e($order['coupon_code']); ?>)<?php endif; ?></td>
                            <td>-<?php echo currency($order['discount']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3">Tax</td>
                        <td><?php echo currency($order['tax']); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3"><strong>Total</strong></td>
                        <td><strong><?php echo currency($order['total']); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
