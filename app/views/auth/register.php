<!-- FILE: /app/views/auth/register.php -->
<div class="auth-container">
    <div class="auth-box">
        <h1>Register for SplashOrder</h1>

        <form method="POST" action="<?php echo url('/register'); ?>" class="auth-form">
            <?php echo Csrf::field(); ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <small>Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </div>

            <div class="form-footer">
                <p>Already have an account? <a href="<?php echo url('/login'); ?>">Login here</a></p>
            </div>
        </form>
    </div>
</div>

<?php clearOldInput(); ?>
