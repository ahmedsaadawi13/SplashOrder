<!-- FILE: /app/views/emails/order_confirmation.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9fafb; padding: 20px; }
        .order-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .total { font-weight: bold; font-size: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
        </div>

        <div class="content">
            <p>Hi <?php echo htmlspecialchars($customer['name']); ?>,</p>

            <p>Thank you for your order! We've received it and it's being processed.</p>

            <div class="order-details">
                <h2>Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                <p><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                <p><strong>Order Type:</strong> <?php echo ucfirst($order['order_type']); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>

                <?php if ($order['order_type'] === 'delivery' && !empty($order['delivery_address'])): ?>
                    <p><strong>Delivery Address:</strong><br>
                    <?php echo htmlspecialchars($order['delivery_address']); ?><br>
                    <?php echo htmlspecialchars($order['delivery_city']); ?></p>
                <?php endif; ?>

                <h3>Order Items:</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($order['items'])): ?>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['total_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">Subtotal</td>
                            <td>$<?php echo number_format($order['subtotal'], 2); ?></td>
                        </tr>
                        <?php if ($order['delivery_fee'] > 0): ?>
                            <tr>
                                <td colspan="2">Delivery Fee</td>
                                <td>$<?php echo number_format($order['delivery_fee'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($order['discount'] > 0): ?>
                            <tr>
                                <td colspan="2">Discount</td>
                                <td>-$<?php echo number_format($order['discount'], 2); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="2">Tax</td>
                            <td>$<?php echo number_format($order['tax'], 2); ?></td>
                        </tr>
                        <tr class="total">
                            <td colspan="2">Total</td>
                            <td>$<?php echo number_format($order['total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p>We'll send you another email when your order status changes.</p>

            <p>Thank you for choosing us!</p>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> SplashOrder. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
