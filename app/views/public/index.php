<!-- FILE: /app/views/public/index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($tenant['name']); ?> - Online Ordering</title>
    <link rel="stylesheet" href="<?php echo asset('css/public.css'); ?>">
</head>
<body>
    <div class="public-header">
        <div class="container">
            <h1><?php echo e($tenant['name']); ?></h1>
            <p><?php echo e($tenant['description']); ?></p>
        </div>
    </div>

    <div class="public-content">
        <div class="container">
            <!-- Featured Items -->
            <?php if (!empty($featured_items)): ?>
                <section class="featured-section">
                    <h2>Featured Items</h2>
                    <div class="items-grid">
                        <?php foreach ($featured_items as $item): ?>
                            <div class="item-card">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo url('/storage/uploads/' . $item['image']); ?>" alt="<?php echo e($item['name']); ?>">
                                <?php endif; ?>
                                <div class="item-info">
                                    <h3><?php echo e($item['name']); ?></h3>
                                    <p><?php echo e(truncate($item['description'], 100)); ?></p>
                                    <div class="item-price"><?php echo currency($item['base_price']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- View Full Menu Button -->
            <div class="cta-section">
                <a href="<?php echo url('/order/' . $tenant['slug'] . '/menu'); ?>" class="btn btn-primary btn-lg">View Full Menu & Order Now</a>
            </div>

            <!-- Branches -->
            <?php if (!empty($branches)): ?>
                <section class="branches-section">
                    <h2>Our Locations</h2>
                    <div class="branches-grid">
                        <?php foreach ($branches as $branch): ?>
                            <div class="branch-card">
                                <h3><?php echo e($branch['name']); ?></h3>
                                <p><?php echo e($branch['address']); ?>, <?php echo e($branch['city']); ?></p>
                                <p>Phone: <?php echo e($branch['phone']); ?></p>
                                <p>Hours: <?php echo e($branch['opening_hours']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
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
