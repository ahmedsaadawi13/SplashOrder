<!-- FILE: /app/views/home/index.php -->
<div class="home-page">
    <div class="hero-section">
        <h1>Welcome to SplashOrder</h1>
        <p class="lead">Multi-tenant Restaurant Online Ordering SaaS Platform</p>
        <p>Empower your restaurant business with our comprehensive online ordering solution.</p>

        <?php if (!Auth::isLoggedIn()): ?>
            <div class="cta-buttons">
                <a href="<?php echo url('/register'); ?>" class="btn btn-primary btn-lg">Get Started</a>
                <a href="<?php echo url('/login'); ?>" class="btn btn-secondary btn-lg">Login</a>
            </div>
        <?php else: ?>
            <div class="cta-buttons">
                <a href="<?php echo url('/dashboard'); ?>" class="btn btn-primary btn-lg">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="features-section">
        <h2>Key Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>Multi-Tenant Architecture</h3>
                <p>Manage multiple restaurant brands with isolated data and customization options.</p>
            </div>

            <div class="feature-card">
                <h3>Branch Management</h3>
                <p>Support multiple locations with individual menus, hours, and delivery zones.</p>
            </div>

            <div class="feature-card">
                <h3>Menu Management</h3>
                <p>Easy-to-use menu builder with categories, items, options, and modifiers.</p>
            </div>

            <div class="feature-card">
                <h3>Online Ordering</h3>
                <p>Mobile-friendly ordering interface for delivery and pickup orders.</p>
            </div>

            <div class="feature-card">
                <h3>Order Management</h3>
                <p>Real-time order tracking with status updates and notifications.</p>
            </div>

            <div class="feature-card">
                <h3>Coupons & Discounts</h3>
                <p>Flexible coupon system with percentage and fixed discounts.</p>
            </div>

            <div class="feature-card">
                <h3>Analytics & Reports</h3>
                <p>Comprehensive reporting on sales, orders, and customer insights.</p>
            </div>

            <div class="feature-card">
                <h3>API Access</h3>
                <p>REST API for integrating with external systems and mobile apps.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($tenants)): ?>
        <div class="tenants-section">
            <h2>Demo Restaurants</h2>
            <div class="tenants-grid">
                <?php foreach ($tenants as $tenant): ?>
                    <div class="tenant-card">
                        <h3><?php echo e($tenant['name']); ?></h3>
                        <p><?php echo e($tenant['description'] ?? 'Premium dining experience'); ?></p>
                        <a href="<?php echo url('/order/' . $tenant['slug']); ?>" class="btn btn-primary">Order Now</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
