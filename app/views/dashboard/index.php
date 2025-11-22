<!-- FILE: /app/views/dashboard/index.php -->
<div class="dashboard">
    <h1>Dashboard</h1>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Today's Orders</div>
            <div class="stat-value"><?php echo e($today_stats['total_orders'] ?? 0); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value"><?php echo currency($today_stats['total_revenue'] ?? 0); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Average Order Value</div>
            <div class="stat-value"><?php echo currency($today_stats['average_order_value'] ?? 0); ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Month's Orders</div>
            <div class="stat-value"><?php echo e($month_stats['total_orders'] ?? 0); ?></div>
        </div>
    </div>

    <!-- Orders by Status -->
    <div class="dashboard-section">
        <h2>Orders by Status</h2>
        <div class="status-grid">
            <div class="status-card">
                <div class="status-label">New</div>
                <div class="status-count"><?php echo e($new_orders ?? 0); ?></div>
                <a href="<?php echo url('/orders?status=new'); ?>" class="btn btn-sm">View</a>
            </div>

            <div class="status-card">
                <div class="status-label">Confirmed</div>
                <div class="status-count"><?php echo e($confirmed_orders ?? 0); ?></div>
                <a href="<?php echo url('/orders?status=confirmed'); ?>" class="btn btn-sm">View</a>
            </div>

            <div class="status-card">
                <div class="status-label">Preparing</div>
                <div class="status-count"><?php echo e($preparing_orders ?? 0); ?></div>
                <a href="<?php echo url('/orders?status=preparing'); ?>" class="btn btn-sm">View</a>
            </div>

            <div class="status-card">
                <div class="status-label">Out for Delivery</div>
                <div class="status-count"><?php echo e($out_for_delivery_orders ?? 0); ?></div>
                <a href="<?php echo url('/orders?status=out_for_delivery'); ?>" class="btn btn-sm">View</a>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="dashboard-section">
        <h2>Recent Orders</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo e($order['order_number']); ?></td>
                            <td><?php echo e($order['customer_name']); ?></td>
                            <td><?php echo currency($order['total']); ?></td>
                            <td><span class="badge badge-<?php echo e($order['status']); ?>"><?php echo e($order['status']); ?></span></td>
                            <td><?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?></td>
                            <td>
                                <a href="<?php echo url('/orders/view/' . $order['id']); ?>" class="btn btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">No orders yet</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Best Selling Items -->
    <?php if (!empty($best_selling)): ?>
        <div class="dashboard-section">
            <h2>Best Selling Items</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity Sold</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($best_selling as $item): ?>
                        <tr>
                            <td><?php echo e($item['item_name']); ?></td>
                            <td><?php echo e($item['total_quantity']); ?></td>
                            <td><?php echo e($item['order_count']); ?></td>
                            <td><?php echo currency($item['total_revenue']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Usage Statistics -->
    <?php if (!empty($usage_stats)): ?>
        <div class="dashboard-section">
            <h2>Account Usage</h2>
            <div class="usage-grid">
                <div class="usage-item">
                    <div class="usage-label">Branches</div>
                    <div class="usage-value">
                        <?php echo e($usage_stats['branches']); ?>
                        <?php if ($usage_stats['limits']['max_branches']): ?>
                            / <?php echo e($usage_stats['limits']['max_branches']); ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </div>
                </div>

                <div class="usage-item">
                    <div class="usage-label">Menu Items</div>
                    <div class="usage-value">
                        <?php echo e($usage_stats['menu_items']); ?>
                        <?php if ($usage_stats['limits']['max_menu_items']): ?>
                            / <?php echo e($usage_stats['limits']['max_menu_items']); ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </div>
                </div>

                <div class="usage-item">
                    <div class="usage-label">Orders This Month</div>
                    <div class="usage-value">
                        <?php echo e($usage_stats['orders_this_month']); ?>
                        <?php if ($usage_stats['limits']['max_orders_per_month']): ?>
                            / <?php echo e($usage_stats['limits']['max_orders_per_month']); ?>
                        <?php else: ?>
                            / Unlimited
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
