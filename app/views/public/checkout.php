<!-- FILE: /app/views/public/checkout.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo e($tenant['name']); ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/public.css'); ?>">
</head>
<body>
    <div class="public-header">
        <div class="container">
            <h1>Checkout</h1>
        </div>
    </div>

    <div class="public-content">
        <div class="container">
            <div class="checkout-grid">
                <div class="checkout-form">
                    <form method="POST" action="<?php echo url('/order/' . $tenant['slug'] . '/checkout'); ?>">
                        <?php echo Csrf::field(); ?>

                        <h2>Contact Information</h2>

                        <div class="form-group">
                            <label for="customer_name">Full Name*</label>
                            <input type="text" id="customer_name" name="customer_name" value="<?php echo e(old('customer_name')); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Phone Number*</label>
                            <input type="tel" id="customer_phone" name="customer_phone" value="<?php echo e(old('customer_phone')); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="customer_email">Email</label>
                            <input type="email" id="customer_email" name="customer_email" value="<?php echo e(old('customer_email')); ?>">
                        </div>

                        <h2>Order Type</h2>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="order_type" value="delivery" <?php echo (old('order_type', 'delivery') == 'delivery') ? 'checked' : ''; ?> onchange="toggleOrderType()">
                                Delivery
                            </label>
                            <label>
                                <input type="radio" name="order_type" value="pickup" <?php echo (old('order_type') == 'pickup') ? 'checked' : ''; ?> onchange="toggleOrderType()">
                                Pickup
                            </label>
                        </div>

                        <div id="delivery-fields" class="order-type-fields">
                            <h3>Delivery Address</h3>

                            <div class="form-group">
                                <label for="delivery_address">Street Address*</label>
                                <textarea id="delivery_address" name="delivery_address" rows="2"><?php echo e(old('delivery_address')); ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="delivery_city">City*</label>
                                    <input type="text" id="delivery_city" name="delivery_city" value="<?php echo e(old('delivery_city')); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="delivery_area">Area</label>
                                    <input type="text" id="delivery_area" name="delivery_area" value="<?php echo e(old('delivery_area')); ?>">
                                </div>
                            </div>
                        </div>

                        <div id="pickup-fields" class="order-type-fields" style="display: none;">
                            <h3>Select Branch</h3>

                            <div class="form-group">
                                <label for="branch_id">Branch*</label>
                                <select id="branch_id" name="branch_id">
                                    <option value="">Select a branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['id']; ?>" <?php echo (old('branch_id') == $branch['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($branch['name']); ?> - <?php echo e($branch['address']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h2>Payment Method</h2>

                        <div class="form-group">
                            <label>
                                <input type="radio" name="payment_method" value="cod" <?php echo (old('payment_method', 'cod') == 'cod') ? 'checked' : ''; ?>>
                                Cash on Delivery
                            </label>
                            <label>
                                <input type="radio" name="payment_method" value="online" <?php echo (old('payment_method') == 'online') ? 'checked' : ''; ?>>
                                Online Payment (Simulated)
                            </label>
                        </div>

                        <h2>Additional Information</h2>

                        <div class="form-group">
                            <label for="coupon_code">Coupon Code</label>
                            <input type="text" id="coupon_code" name="coupon_code" value="<?php echo e(old('coupon_code')); ?>" placeholder="Enter coupon code">
                        </div>

                        <div class="form-group">
                            <label for="notes">Order Notes</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Special instructions..."><?php echo e(old('notes')); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block">Place Order</button>
                    </form>
                </div>

                <div class="checkout-summary">
                    <h2>Order Summary</h2>

                    <div class="summary-items">
                        <?php foreach ($cart as $item): ?>
                            <div class="summary-item">
                                <div class="summary-item-name">
                                    <?php echo e($item['name']); ?> x<?php echo $item['quantity']; ?>
                                </div>
                                <div class="summary-item-price"><?php echo currency($item['total']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-totals">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span><?php echo currency($subtotal); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Delivery Fee</span>
                            <span id="delivery-fee">-</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (10%)</span>
                            <span id="tax">-</span>
                        </div>
                        <div class="summary-row summary-total">
                            <strong>Total</strong>
                            <strong id="total">-</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleOrderType() {
            const orderType = document.querySelector('input[name="order_type"]:checked').value;
            const deliveryFields = document.getElementById('delivery-fields');
            const pickupFields = document.getElementById('pickup-fields');

            if (orderType === 'delivery') {
                deliveryFields.style.display = 'block';
                pickupFields.style.display = 'none';
            } else {
                deliveryFields.style.display = 'none';
                pickupFields.style.display = 'block';
            }
        }

        // Initialize on page load
        toggleOrderType();
    </script>

    <div class="public-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo e($tenant['name']); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

<?php clearOldInput(); ?>
