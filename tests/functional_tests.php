<?php
// FILE: /tests/functional_tests.php

/**
 * SplashOrder Functional Tests
 * Basic functional testing for critical features
 *
 * Usage: php tests/functional_tests.php
 */

// Test results tracking
$tests_passed = 0;
$tests_failed = 0;
$test_results = [];

/**
 * Run a test
 */
function test($name, $callback) {
    global $tests_passed, $tests_failed, $test_results;

    try {
        $result = $callback();
        if ($result) {
            $tests_passed++;
            $test_results[] = "✓ PASS: $name";
            echo "\033[32m✓ PASS:\033[0m $name\n";
        } else {
            $tests_failed++;
            $test_results[] = "✗ FAIL: $name";
            echo "\033[31m✗ FAIL:\033[0m $name\n";
        }
    } catch (Exception $e) {
        $tests_failed++;
        $test_results[] = "✗ ERROR: $name - " . $e->getMessage();
        echo "\033[31m✗ ERROR:\033[0m $name - " . $e->getMessage() . "\n";
    }
}

/**
 * Assert equality
 */
function assertEquals($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        throw new Exception($message ?: "Expected $expected, got $actual");
    }
    return true;
}

/**
 * Assert true
 */
function assertTrue($condition, $message = 'Assertion failed') {
    if (!$condition) {
        throw new Exception($message);
    }
    return true;
}

echo "\n";
echo "========================================\n";
echo "  SplashOrder Functional Tests\n";
echo "========================================\n\n";

// Load configuration
require_once __DIR__ . '/../config/database.php';
$config = require __DIR__ . '/../config/database.php';

// Test database connection
test('Database connection', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);
    assertTrue($pdo instanceof PDO, 'Failed to connect to database');
    return true;
});

// Test database tables exist
test('Required tables exist', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $required_tables = [
        'tenants', 'users', 'subscription_plans', 'tenant_subscriptions',
        'branches', 'categories', 'menu_items', 'menu_options',
        'customers', 'orders', 'order_items', 'coupons'
    ];

    foreach ($required_tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        assertTrue($result !== false, "Table $table does not exist");
    }

    return true;
});

// Test tenant data
test('Demo tenants exist', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tenants WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($result['count'] >= 2, "Expected at least 2 active tenants");
    return true;
});

// Test admin user exists
test('Admin user exists', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'platform_admin' LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($result !== false, "No platform admin user found");
    assertEquals('admin@splashorder.com', $result['email'], "Admin email mismatch");
    return true;
});

// Test menu items exist
test('Menu items exist for tenants', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM menu_items");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($result['count'] >= 10, "Expected at least 10 menu items");
    return true;
});

// Test subscription plans exist
test('Subscription plans exist', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subscription_plans WHERE is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($result['count'] >= 3, "Expected at least 3 subscription plans");
    return true;
});

// Test orders exist
test('Sample orders exist', function() use ($config) {
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password']);

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    assertTrue($result['count'] >= 5, "Expected at least 5 sample orders");
    return true;
});

// Test password hashing
test('Password hashing works', function() {
    $password = 'testpassword123';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    assertTrue(password_verify($password, $hash), "Password verification failed");
    return true;
});

// Test file permissions
test('Upload directory is writable', function() {
    $upload_dir = __DIR__ . '/../storage/uploads';

    assertTrue(is_dir($upload_dir), "Upload directory does not exist");
    assertTrue(is_writable($upload_dir), "Upload directory is not writable");
    return true;
});

// Test core classes can be loaded
test('Core classes can be loaded', function() {
    $core_files = [
        __DIR__ . '/../app/core/Database.php',
        __DIR__ . '/../app/core/Router.php',
        __DIR__ . '/../app/core/Controller.php',
        __DIR__ . '/../app/core/Model.php',
        __DIR__ . '/../app/core/Auth.php',
        __DIR__ . '/../app/core/Session.php',
        __DIR__ . '/../app/core/Csrf.php',
        __DIR__ . '/../app/core/Validator.php',
    ];

    foreach ($core_files as $file) {
        assertTrue(file_exists($file), "Core file does not exist: $file");
        assertTrue(is_readable($file), "Core file is not readable: $file");
    }

    return true;
});

// Print summary
echo "\n";
echo "========================================\n";
echo "  Test Summary\n";
echo "========================================\n";
echo "Total Tests: " . ($tests_passed + $tests_failed) . "\n";
echo "\033[32mPassed: $tests_passed\033[0m\n";
echo "\033[31mFailed: $tests_failed\033[0m\n";
echo "\n";

if ($tests_failed === 0) {
    echo "\033[32m✓ All tests passed!\033[0m\n\n";
    exit(0);
} else {
    echo "\033[31m✗ Some tests failed. Please review the output above.\033[0m\n\n";
    exit(1);
}
