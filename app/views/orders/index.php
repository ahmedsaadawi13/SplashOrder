<!-- FILE: /app/views/orders/index.php -->
<div class="orders-page">
    <div class="page-header">
        <h1>Orders</h1>

        <div class="page-actions">
            <form method="GET" action="<?php echo url('/orders'); ?>" class="filter-form">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="new" <?php echo ($status === 'new') ? 'selected' : ''; ?>>New</option>
                    <option value="confirmed" <?php echo ($status === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="preparing" <?php echo ($status === 'preparing') ? 'selected' : ''; ?>>Preparing</option>
                    <option value="out_for_delivery" <?php echo ($status === 'out_for_delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
                    <option value="delivered" <?php echo ($status === 'delivered') ? 'selected' : ''; ?>>Delivered</option>
                    <option value="canceled" <?php echo ($status === 'canceled') ? 'selected' : ''; ?>>Canceled</option>
                </select>
            </form>
        </div>
    </div>

    <?php if (!empty($orders)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo e($order['order_number']); ?></strong></td>
                        <td><?php echo e($order['customer_name']); ?></td>
                        <td><?php echo e($order['customer_phone']); ?></td>
                        <td><?php echo ucfirst($order['order_type']); ?></td>
                        <td><?php echo currency($order['total']); ?></td>
                        <td><span class="badge badge-<?php echo e($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                        <td><span class="badge badge-<?php echo e($order['payment_status']); ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                        <td><?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?></td>
                        <td>
                            <a href="<?php echo url('/orders/view/' . $order['id']); ?>" class="btn btn-sm">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php echo pagination($current_page, $total_pages, url('/orders')); ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <p>No orders found.</p>
        </div>
    <?php endif; ?>
</div>
