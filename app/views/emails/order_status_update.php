<!-- FILE: /app/views/emails/order_status_update.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9fafb; padding: 20px; }
        .status-box { background-color: white; padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center; }
        .status { font-size: 24px; font-weight: bold; color: #2563eb; margin: 10px 0; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Status Update</h1>
        </div>

        <div class="content">
            <p>Your order #<?php echo htmlspecialchars($order['order_number']); ?> status has been updated.</p>

            <div class="status-box">
                <p class="status"><?php echo strtoupper(str_replace('_', ' ', $status)); ?></p>
                <p><?php echo htmlspecialchars($status_message); ?></p>
            </div>

            <?php if ($status === 'out_for_delivery'): ?>
                <p>Your order is on its way! Estimated delivery time: <?php echo $order['estimated_delivery_time'] ?? 30; ?> minutes.</p>
            <?php endif; ?>

            <?php if ($status === 'delivered'): ?>
                <p>We hope you enjoyed your meal! Please consider leaving us a review.</p>
            <?php endif; ?>

            <p>Thank you for your order!</p>
        </div>

        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> SplashOrder. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
