<?php
// FILE: /public/index.php

/**
 * SplashOrder - Multi-tenant Restaurant Online Ordering SaaS
 * Front Controller - Main entry point for all requests
 * Compatible with PHP 7.0+
 */

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone
date_default_timezone_set('UTC');

// Define directory separator constant
define('DS', DIRECTORY_SEPARATOR);

// Define root path
define('ROOT_PATH', dirname(__DIR__) . DS);

// Load environment variables
if (file_exists(ROOT_PATH . '.env')) {
    $lines = file(ROOT_PATH . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Autoload core classes
spl_autoload_register(function ($class) {
    // Remove namespace prefix
    $class = str_replace('App\\', '', $class);
    $class = str_replace('\\', DS, $class);

    // Build file paths to check
    $paths = [
        ROOT_PATH . 'app' . DS . $class . '.php',
        ROOT_PATH . 'app' . DS . strtolower($class) . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Load core files
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Database.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Router.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Controller.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Model.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Auth.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Session.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Csrf.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Validator.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Request.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'Upload.php';
require ROOT_PATH . 'app' . DS . 'core' . DS . 'helpers.php';

// Start session
Session::start();

// Create router instance
$router = new Router();

// Define routes

// Public routes
$router->add('', ['controller' => 'Home', 'action' => 'index']);
$router->add('login', ['controller' => 'Auth', 'action' => 'login']);
$router->add('register', ['controller' => 'Auth', 'action' => 'register']);
$router->add('logout', ['controller' => 'Auth', 'action' => 'logout']);

// Public ordering routes
$router->add('order/{tenant}', ['controller' => 'PublicOrder', 'action' => 'index']);
$router->add('order/{tenant}/menu', ['controller' => 'PublicOrder', 'action' => 'menu']);
$router->add('order/{tenant}/cart', ['controller' => 'PublicOrder', 'action' => 'cart']);
$router->add('order/{tenant}/checkout', ['controller' => 'PublicOrder', 'action' => 'checkout']);
$router->add('order/{tenant}/confirmation/{order_id}', ['controller' => 'PublicOrder', 'action' => 'confirmation']);

// Customer routes
$router->add('customer/dashboard', ['controller' => 'Customer', 'action' => 'dashboard']);
$router->add('customer/orders', ['controller' => 'Customer', 'action' => 'orders']);
$router->add('customer/order/{id}', ['controller' => 'Customer', 'action' => 'viewOrder']);
$router->add('customer/profile', ['controller' => 'Customer', 'action' => 'profile']);

// Dashboard routes (authenticated)
$router->add('dashboard', ['controller' => 'Dashboard', 'action' => 'index']);

// Tenant management
$router->add('admin/tenants', ['controller' => 'Admin\\Tenants', 'action' => 'index', 'namespace' => 'Admin']);
$router->add('admin/tenants/create', ['controller' => 'Admin\\Tenants', 'action' => 'create', 'namespace' => 'Admin']);
$router->add('admin/tenants/edit/{id}', ['controller' => 'Admin\\Tenants', 'action' => 'edit', 'namespace' => 'Admin']);
$router->add('admin/tenants/delete/{id}', ['controller' => 'Admin\\Tenants', 'action' => 'delete', 'namespace' => 'Admin']);

// Branch management
$router->add('branches', ['controller' => 'Branches', 'action' => 'index']);
$router->add('branches/create', ['controller' => 'Branches', 'action' => 'create']);
$router->add('branches/edit/{id}', ['controller' => 'Branches', 'action' => 'edit']);
$router->add('branches/delete/{id}', ['controller' => 'Branches', 'action' => 'delete']);

// Category management
$router->add('categories', ['controller' => 'Categories', 'action' => 'index']);
$router->add('categories/create', ['controller' => 'Categories', 'action' => 'create']);
$router->add('categories/edit/{id}', ['controller' => 'Categories', 'action' => 'edit']);
$router->add('categories/delete/{id}', ['controller' => 'Categories', 'action' => 'delete']);

// Menu Items management
$router->add('menu-items', ['controller' => 'MenuItems', 'action' => 'index']);
$router->add('menu-items/create', ['controller' => 'MenuItems', 'action' => 'create']);
$router->add('menu-items/edit/{id}', ['controller' => 'MenuItems', 'action' => 'edit']);
$router->add('menu-items/delete/{id}', ['controller' => 'MenuItems', 'action' => 'delete']);

// Menu Options management
$router->add('menu-options', ['controller' => 'MenuOptions', 'action' => 'index']);
$router->add('menu-options/create', ['controller' => 'MenuOptions', 'action' => 'create']);
$router->add('menu-options/edit/{id}', ['controller' => 'MenuOptions', 'action' => 'edit']);
$router->add('menu-options/delete/{id}', ['controller' => 'MenuOptions', 'action' => 'delete']);

// Orders management
$router->add('orders', ['controller' => 'Orders', 'action' => 'index']);
$router->add('orders/view/{id}', ['controller' => 'Orders', 'action' => 'view']);
$router->add('orders/update-status/{id}', ['controller' => 'Orders', 'action' => 'updateStatus']);

// Customers management
$router->add('customers', ['controller' => 'Customers', 'action' => 'index']);
$router->add('customers/view/{id}', ['controller' => 'Customers', 'action' => 'view']);

// Coupons management
$router->add('coupons', ['controller' => 'Coupons', 'action' => 'index']);
$router->add('coupons/create', ['controller' => 'Coupons', 'action' => 'create']);
$router->add('coupons/edit/{id}', ['controller' => 'Coupons', 'action' => 'edit']);
$router->add('coupons/delete/{id}', ['controller' => 'Coupons', 'action' => 'delete']);

// Subscription and billing
$router->add('subscription', ['controller' => 'Subscription', 'action' => 'index']);
$router->add('subscription/upgrade', ['controller' => 'Subscription', 'action' => 'upgrade']);
$router->add('subscription/invoices', ['controller' => 'Subscription', 'action' => 'invoices']);
$router->add('subscription/payment/{invoice_id}', ['controller' => 'Subscription', 'action' => 'payment']);

// Reports
$router->add('reports/sales', ['controller' => 'Reports', 'action' => 'sales']);
$router->add('reports/orders', ['controller' => 'Reports', 'action' => 'orders']);
$router->add('reports/customers', ['controller' => 'Reports', 'action' => 'customers']);

// API routes
$router->add('api/orders/create', ['controller' => 'Api\\Orders', 'action' => 'create', 'namespace' => 'Api']);

// Get URL from query string
$url = $_GET['url'] ?? '';

// Dispatch the router
$router->dispatch($url);
