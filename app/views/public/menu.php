<!-- FILE: /app/views/public/menu.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - <?php echo e($tenant['name']); ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/public.css'); ?>">
</head>
<body>
    <div class="public-header">
        <div class="container">
            <h1><?php echo e($tenant['name']); ?> - Menu</h1>
            <div class="cart-link">
                <a href="<?php echo url('/order/' . $tenant['slug'] . '/cart'); ?>" class="btn btn-primary">
                    Cart (<?php echo $cart_count; ?>)
                </a>
            </div>
        </div>
    </div>

    <div class="public-content">
        <div class="container">
            <?php if (!empty($menu_by_category)): ?>
                <?php foreach ($menu_by_category as $section): ?>
                    <section class="menu-category">
                        <h2><?php echo e($section['category']['name']); ?></h2>
                        <?php if ($section['category']['description']): ?>
                            <p><?php echo e($section['category']['description']); ?></p>
                        <?php endif; ?>

                        <div class="menu-items">
                            <?php foreach ($section['items'] as $item): ?>
                                <div class="menu-item">
                                    <?php if ($item['image']): ?>
                                        <div class="menu-item-image">
                                            <img src="<?php echo url('/storage/uploads/' . $item['image']); ?>" alt="<?php echo e($item['name']); ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="menu-item-info">
                                        <div class="menu-item-header">
                                            <h3><?php echo e($item['name']); ?></h3>
                                            <div class="menu-item-price"><?php echo currency($item['base_price']); ?></div>
                                        </div>

                                        <?php if ($item['description']): ?>
                                            <p><?php echo e($item['description']); ?></p>
                                        <?php endif; ?>

                                        <div class="menu-item-meta">
                                            <?php if ($item['is_vegetarian']): ?>
                                                <span class="badge badge-vegetarian">Vegetarian</span>
                                            <?php endif; ?>
                                            <?php if ($item['is_spicy']): ?>
                                                <span class="badge badge-spicy">Spicy</span>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" action="<?php echo url('/order/' . $tenant['slug'] . '/cart'); ?>" class="add-to-cart-form">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <div class="quantity-input">
                                                <label>Quantity:</label>
                                                <input type="number" name="quantity" value="1" min="1" max="10">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Add to Cart</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No menu items available at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="floating-cart">
        <a href="<?php echo url('/order/' . $tenant['slug'] . '/cart'); ?>" class="btn btn-primary btn-lg">
            View Cart (<?php echo $cart_count; ?>)
        </a>
    </div>

    <div class="public-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo e($tenant['name']); ?>. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
