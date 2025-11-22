<!-- FILE: /app/views/public/confirmation.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - <?php echo e($tenant['name']); ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/public.css'); ?>">
</head>
<body>
    <div class="public-header">
        <div class="container">
            <h1>Order Confirmed!</h1>
        </div>
    </div>

    <div class="public-content">
        <div class="container">
            <div class="confirmation-box">
                <div class="confirmation-success">
                    <svg width="80" height="80" viewBox="0 0 80 80" fill="none">
                        <circle cx="40" cy="40" r="40" fill="#10B981"/>
                        <path d="M25 40 L35 50 L55 30" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <h2>Thank you for your order!</h2>
                    <p>Your order has been received and is being processed.</p>
                </div>

                <div class="order-details">
                    <div class="detail-row">
                        <strong>Order Number:</strong>
                        <span><?php echo e($order['order_number']); ?></span>
                    </div>

                    <div class="detail-row">
                        <strong>Order Date:</strong>
                        <span><?php echo formatDate($order['created_at'], 'M d, Y H:i'); ?></span>
                    </div>

                    <div class="detail-row">
                        <strong>Order Type:</strong>
                        <span><?php echo ucfirst($order['order_type']); ?></span>
                    </div>

                    <div class="detail-row">
                        <strong>Status:</strong>
                        <span class="badge badge-<?php echo e($order['status']); ?>"><?php echo ucfirst($order['status']); ?></span>
                    </div>

                    <div class="detail-row">
                        <strong>Payment Method:</strong>
                        <span><?php echo ucfirst($order['payment_method']); ?></span>
                    </div>

                    <?php if ($order['order_type'] === 'delivery' && $order['delivery_address']): ?>
                        <div class="detail-row">
                            <strong>Delivery Address:</strong>
                            <span><?php echo e($order['delivery_address']); ?>, <?php echo e($order['delivery_city']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="order-items">
                    <h3>Order Items</h3>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <?php echo e($item['item_name']); ?>
                                        <?php if ($item['options_text']): ?>
                                            <br><small><?php echo e($item['options_text']); ?></small>
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
                                    <td colspan="3">Discount</td>
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

                <div class="confirmation-actions">
                    <a href="<?php echo url('/order/' . $tenant['slug']); ?>" class="btn btn-primary">Back to Home</a>
                    <a href="<?php echo url('/order/' . $tenant['slug'] . '/menu'); ?>" class="btn btn-secondary">Order Again</a>
                </div>
            </div>
        </div>
    </div>

    <div class="public-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo e($tenant['name']); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
