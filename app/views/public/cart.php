<!-- FILE: /app/views/public/cart.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - <?php echo e($tenant['name']); ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/public.css'); ?>">
</head>
<body>
    <div class="public-header">
        <div class="container">
            <h1>Your Cart</h1>
            <a href="<?php echo url('/order/' . $tenant['slug'] . '/menu'); ?>">Continue Shopping</a>
        </div>
    </div>

    <div class="public-content">
        <div class="container">
            <?php if (!empty($cart)): ?>
                <form method="POST" action="<?php echo url('/order/' . $tenant['slug'] . '/cart'); ?>">
                    <input type="hidden" name="action" value="update">

                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $cart_key => $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($item['name']); ?></strong>
                                        <?php if (!empty($item['options'])): ?>
                                            <div class="cart-item-options">
                                                <?php foreach ($item['options'] as $option): ?>
                                                    <small><?php echo e($option['name']); ?>: <?php echo e($option['value']); ?>
                                                    <?php if ($option['price_modifier'] > 0): ?>
                                                        (+<?php echo currency($option['price_modifier']); ?>)
                                                    <?php endif; ?>
                                                    </small><br>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo currency($item['price']); ?></td>
                                    <td>
                                        <input type="number" name="quantity[<?php echo $cart_key; ?>]" value="<?php echo $item['quantity']; ?>" min="0" max="10">
                                    </td>
                                    <td><?php echo currency($item['total']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo url('/order/' . $tenant['slug'] . '/cart'); ?>" style="display: inline;">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="cart_key" value="<?php echo $cart_key; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Subtotal</strong></td>
                                <td colspan="2"><strong><?php echo currency($subtotal); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="cart-actions">
                        <button type="submit" class="btn btn-secondary">Update Cart</button>
                        <a href="<?php echo url('/order/' . $tenant['slug'] . '/checkout'); ?>" class="btn btn-primary btn-lg">Proceed to Checkout</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-cart">
                    <p>Your cart is empty.</p>
                    <a href="<?php echo url('/order/' . $tenant['slug'] . '/menu'); ?>" class="btn btn-primary">Browse Menu</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="public-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo e($tenant['name']); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
