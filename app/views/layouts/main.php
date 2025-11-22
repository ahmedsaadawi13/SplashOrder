<!-- FILE: /app/views/layouts/main.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title ?? 'SplashOrder'); ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="<?php echo url('/'); ?>">SplashOrder</a>
                </div>
                <nav class="nav">
                    <?php if (Auth::isLoggedIn()): ?>
                        <a href="<?php echo url('/dashboard'); ?>">Dashboard</a>

                        <?php if (Auth::hasRole(['tenant_admin', 'staff'])): ?>
                            <a href="<?php echo url('/orders'); ?>">Orders</a>
                            <a href="<?php echo url('/menu-items'); ?>">Menu</a>
                            <a href="<?php echo url('/branches'); ?>">Branches</a>
                            <a href="<?php echo url('/categories'); ?>">Categories</a>
                            <a href="<?php echo url('/coupons'); ?>">Coupons</a>
                        <?php endif; ?>

                        <span class="user-info">
                            <?php echo e(Auth::user('name')); ?> (<?php echo e(Auth::user('role')); ?>)
                        </span>
                        <a href="<?php echo url('/logout'); ?>">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo url('/login'); ?>">Login</a>
                        <a href="<?php echo url('/register'); ?>">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Flash Messages -->
            <?php if (Session::hasFlash('success')): ?>
                <div class="alert alert-success">
                    <?php echo e(Session::getFlash('success')); ?>
                </div>
            <?php endif; ?>

            <?php if (Session::hasFlash('error')): ?>
                <div class="alert alert-error">
                    <?php echo e(Session::getFlash('error')); ?>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?php echo $content; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> SplashOrder. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
