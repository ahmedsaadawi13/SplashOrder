<!-- FILE: /app/views/auth/login.php -->
<div class="auth-container">
    <div class="auth-box">
        <h1>Login to SplashOrder</h1>

        <form method="POST" action="<?php echo url('/login'); ?>" class="auth-form">
            <?php echo Csrf::field(); ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </div>

            <div class="form-footer">
                <p>Don't have an account? <a href="<?php echo url('/register'); ?>">Register here</a></p>
            </div>
        </form>

        <div class="demo-credentials">
            <h3>Demo Credentials:</h3>
            <p><strong>Platform Admin:</strong> admin@splashorder.com / password</p>
            <p><strong>Tenant Admin (Pizza Paradise):</strong> john@pizzaparadise.com / password</p>
            <p><strong>Tenant Admin (Burger Palace):</strong> sarah@burgerpalace.com / password</p>
        </div>
    </div>
</div>

<?php clearOldInput(); ?>
