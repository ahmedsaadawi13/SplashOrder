<?php
// FILE: /app/controllers/PublicOrder.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Tenant;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;

/**
 * PublicOrder Controller
 * Public-facing ordering interface for customers
 */
class PublicOrder extends Controller
{
    private $tenant;
    private $tenant_id;

    /**
     * Before filter - load tenant
     */
    protected function before()
    {
        $tenant_slug = $this->route_params['tenant'] ?? null;

        if (!$tenant_slug) {
            die('Restaurant not found.');
        }

        $tenant_model = new Tenant();
        $this->tenant = $tenant_model->findBySlug($tenant_slug);

        if (!$this->tenant || $this->tenant['status'] !== 'active') {
            die('Restaurant not found or inactive.');
        }

        $this->tenant_id = $this->tenant['id'];

        // Start cart session if not exists
        if (!\Session::has('cart_' . $this->tenant_id)) {
            \Session::set('cart_' . $this->tenant_id, []);
        }
    }

    /**
     * Restaurant homepage
     */
    public function indexAction()
    {
        $branch_model = new Branch();
        $branches = $branch_model->getOpenBranches($this->tenant_id);

        $menu_item_model = new MenuItem();
        $featured_items = $menu_item_model->getFeaturedItems($this->tenant_id, 8);

        $this->render('public/index.php', [
            'tenant' => $this->tenant,
            'branches' => $branches,
            'featured_items' => $featured_items
        ]);
    }

    /**
     * Menu page
     */
    public function menuAction()
    {
        $category_model = new Category();
        $categories = $category_model->getActiveCategories($this->tenant_id);

        $menu_item_model = new MenuItem();

        // Get menu items grouped by category
        $menu_by_category = [];
        foreach ($categories as $category) {
            $items = $menu_item_model->getItemsByCategory($category['id'], $this->tenant_id);
            if (!empty($items)) {
                $menu_by_category[] = [
                    'category' => $category,
                    'items' => $items
                ];
            }
        }

        // Get cart count
        $cart = \Session::get('cart_' . $this->tenant_id, []);
        $cart_count = array_sum(array_column($cart, 'quantity'));

        $this->render('public/menu.php', [
            'tenant' => $this->tenant,
            'menu_by_category' => $menu_by_category,
            'cart_count' => $cart_count
        ]);
    }

    /**
     * Cart page
     */
    public function cartAction()
    {
        if (\Request::isPost()) {
            $action = \Request::post('action');

            if ($action === 'add') {
                $this->addToCart();
            } elseif ($action === 'update') {
                $this->updateCart();
            } elseif ($action === 'remove') {
                $this->removeFromCart();
            }

            if (\Request::isAjax()) {
                $this->jsonResponse(['success' => true]);
            }

            $this->redirect('/order/' . $this->tenant['slug'] . '/cart');
        }

        $cart = \Session::get('cart_' . $this->tenant_id, []);

        // Calculate totals
        $subtotal = 0;
        foreach ($cart as &$item) {
            $item_total = $item['price'] * $item['quantity'];

            // Add option prices
            if (!empty($item['options'])) {
                foreach ($item['options'] as $option) {
                    $item_total += $option['price_modifier'] * $item['quantity'];
                }
            }

            $item['total'] = $item_total;
            $subtotal += $item_total;
        }

        $this->render('public/cart.php', [
            'tenant' => $this->tenant,
            'cart' => $cart,
            'subtotal' => $subtotal
        ]);
    }

    /**
     * Checkout page
     */
    public function checkoutAction()
    {
        $cart = \Session::get('cart_' . $this->tenant_id, []);

        if (empty($cart)) {
            \Session::setFlash('error', 'Your cart is empty.');
            $this->redirect('/order/' . $this->tenant['slug'] . '/menu');
        }

        if (\Request::isPost()) {
            $this->processCheckout();
            return;
        }

        // Get branches
        $branch_model = new Branch();
        $branches = $branch_model->getOpenBranches($this->tenant_id);

        // Calculate subtotal
        $subtotal = 0;
        foreach ($cart as $item) {
            $item_total = $item['price'] * $item['quantity'];
            if (!empty($item['options'])) {
                foreach ($item['options'] as $option) {
                    $item_total += $option['price_modifier'] * $item['quantity'];
                }
            }
            $subtotal += $item_total;
        }

        $this->render('public/checkout.php', [
            'tenant' => $this->tenant,
            'cart' => $cart,
            'branches' => $branches,
            'subtotal' => $subtotal,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    /**
     * Order confirmation page
     */
    public function confirmationAction()
    {
        $order_id = $this->route_params['order_id'] ?? null;

        if (!$order_id) {
            $this->redirect('/order/' . $this->tenant['slug']);
        }

        $order_model = new Order();
        $order = $order_model->getOrderWithDetails($order_id, $this->tenant_id);

        if (!$order) {
            $this->redirect('/order/' . $this->tenant['slug']);
        }

        $this->render('public/confirmation.php', [
            'tenant' => $this->tenant,
            'order' => $order
        ]);
    }

    /**
     * Add item to cart
     */
    private function addToCart()
    {
        $item_id = \Request::post('item_id');
        $quantity = max(1, (int)\Request::post('quantity', 1));
        $options = \Request::post('options', []);

        $menu_item_model = new MenuItem();
        $item = $menu_item_model->getItemWithCategory($item_id, $this->tenant_id);

        if (!$item || !$item['is_available']) {
            \Session::setFlash('error', 'Item not available.');
            return;
        }

        $cart = \Session::get('cart_' . $this->tenant_id, []);

        // Create unique cart key based on item and options
        $cart_key = $item_id . '_' . md5(json_encode($options));

        // Process selected options
        $selected_options = [];
        $options_price = 0;

        if (!empty($options)) {
            foreach ($options as $option_id => $value_id) {
                $sql = "SELECT mo.name as option_name, mov.name as value_name, mov.price_modifier
                        FROM menu_option_values mov
                        JOIN menu_options mo ON mov.option_id = mo.id
                        WHERE mov.id = :value_id AND mo.id = :option_id";

                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare($sql);
                $stmt->execute([':value_id' => $value_id, ':option_id' => $option_id]);
                $option_data = $stmt->fetch();

                if ($option_data) {
                    $selected_options[] = [
                        'name' => $option_data['option_name'],
                        'value' => $option_data['value_name'],
                        'price_modifier' => $option_data['price_modifier']
                    ];
                    $options_price += $option_data['price_modifier'];
                }
            }
        }

        if (isset($cart[$cart_key])) {
            $cart[$cart_key]['quantity'] += $quantity;
        } else {
            $cart[$cart_key] = [
                'item_id' => $item_id,
                'name' => $item['name'],
                'price' => $item['base_price'],
                'quantity' => $quantity,
                'options' => $selected_options
            ];
        }

        \Session::set('cart_' . $this->tenant_id, $cart);
        \Session::setFlash('success', 'Item added to cart.');
    }

    /**
     * Update cart quantities
     */
    private function updateCart()
    {
        $quantities = \Request::post('quantity', []);
        $cart = \Session::get('cart_' . $this->tenant_id, []);

        foreach ($quantities as $cart_key => $quantity) {
            $quantity = max(0, (int)$quantity);
            if ($quantity > 0 && isset($cart[$cart_key])) {
                $cart[$cart_key]['quantity'] = $quantity;
            } elseif (isset($cart[$cart_key])) {
                unset($cart[$cart_key]);
            }
        }

        \Session::set('cart_' . $this->tenant_id, $cart);
        \Session::setFlash('success', 'Cart updated.');
    }

    /**
     * Remove item from cart
     */
    private function removeFromCart()
    {
        $cart_key = \Request::post('cart_key');
        $cart = \Session::get('cart_' . $this->tenant_id, []);

        if (isset($cart[$cart_key])) {
            unset($cart[$cart_key]);
            \Session::set('cart_' . $this->tenant_id, $cart);
            \Session::setFlash('success', 'Item removed from cart.');
        }
    }

    /**
     * Process checkout
     */
    private function processCheckout()
    {
        // Verify CSRF
        if (!$this->verifyCsrfToken()) {
            \Session::setFlash('error', 'Invalid request.');
            $this->redirect('/order/' . $this->tenant['slug'] . '/checkout');
        }

        $data = \Request::post();

        // Validate
        $validator = new \Validator($data);
        $validator->rules([
            'customer_name' => 'required|min:3',
            'customer_phone' => 'required',
            'order_type' => 'required|in:delivery,pickup',
            'payment_method' => 'required|in:cod,online'
        ]);

        if ($data['order_type'] === 'delivery') {
            $validator->rules([
                'delivery_address' => 'required',
                'delivery_city' => 'required'
            ]);
        } else {
            $validator->rules([
                'branch_id' => 'required|integer'
            ]);
        }

        if (!$validator->validate()) {
            \Session::setFlash('error', 'Please fix the errors below.');
            \Session::setFlash('errors', $validator->errors());
            setOldInput($data);
            $this->redirect('/order/' . $this->tenant['slug'] . '/checkout');
        }

        $cart = \Session::get('cart_' . $this->tenant_id, []);

        if (empty($cart)) {
            \Session::setFlash('error', 'Your cart is empty.');
            $this->redirect('/order/' . $this->tenant['slug'] . '/menu');
        }

        // Calculate totals
        $subtotal = 0;
        $order_items = [];

        foreach ($cart as $item) {
            $item_price = $item['price'];
            $options_price = 0;

            if (!empty($item['options'])) {
                foreach ($item['options'] as $option) {
                    $options_price += $option['price_modifier'];
                }
            }

            $unit_price = $item_price + $options_price;
            $total_price = $unit_price * $item['quantity'];

            $order_items[] = [
                'menu_item_id' => $item['item_id'],
                'item_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unit_price,
                'total_price' => $total_price,
                'options' => $item['options'] ?? []
            ];

            $subtotal += $total_price;
        }

        // Get delivery fee
        $delivery_fee = 0;
        $branch_id = null;

        if ($data['order_type'] === 'pickup') {
            $branch_id = $data['branch_id'];
        } else {
            // Use first branch's delivery fee for simplicity
            $branch_model = new Branch();
            $branches = $branch_model->getOpenBranches($this->tenant_id);
            if (!empty($branches)) {
                $delivery_fee = $branches[0]['delivery_fee'];
                $branch_id = $branches[0]['id'];
            }
        }

        // Apply coupon if provided
        $discount = 0;
        $coupon_code = null;

        if (!empty($data['coupon_code'])) {
            $coupon_model = new Coupon();
            $coupon_result = $coupon_model->validateCoupon($data['coupon_code'], $this->tenant_id, $subtotal);

            if ($coupon_result['valid']) {
                $discount = $coupon_model->calculateDiscount($coupon_result['coupon'], $subtotal);
                $coupon_code = $data['coupon_code'];
                $coupon_model->incrementUsage($coupon_result['coupon']['id']);
            }
        }

        // Calculate tax (10% for example)
        $tax = ($subtotal + $delivery_fee - $discount) * 0.10;

        $total = $subtotal + $delivery_fee + $tax - $discount;

        // Find or create customer
        $customer_model = new Customer();
        $customer_id = $customer_model->findOrCreate([
            'name' => $data['customer_name'],
            'phone' => $data['customer_phone'],
            'email' => $data['customer_email'] ?? null,
            'address' => $data['delivery_address'] ?? null,
            'city' => $data['delivery_city'] ?? null,
            'area' => $data['delivery_area'] ?? null
        ], $this->tenant_id);

        // Create order
        $order_model = new Order();
        $order_model->setTenantId($this->tenant_id);

        $order_data = [
            'tenant_id' => $this->tenant_id,
            'branch_id' => $branch_id,
            'customer_id' => $customer_id,
            'order_number' => $order_model->generateOrderNumber(),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_city' => $data['delivery_city'] ?? null,
            'delivery_area' => $data['delivery_area'] ?? null,
            'order_type' => $data['order_type'],
            'status' => 'new',
            'payment_method' => $data['payment_method'],
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'coupon_code' => $coupon_code,
            'notes' => $data['notes'] ?? null
        ];

        $order_id = $order_model->createOrderWithItems($order_data, $order_items);

        if ($order_id) {
            // Clear cart
            \Session::remove('cart_' . $this->tenant_id);

            // Update customer statistics
            $customer_model->updateStatistics($customer_id);

            // Redirect to confirmation
            $this->redirect('/order/' . $this->tenant['slug'] . '/confirmation/' . $order_id);
        } else {
            \Session::setFlash('error', 'Failed to create order. Please try again.');
            $this->redirect('/order/' . $this->tenant['slug'] . '/checkout');
        }
    }
}
