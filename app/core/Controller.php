<?php
// FILE: /app/core/Controller.php

namespace App\Core;

/**
 * Base Controller Class
 * All controllers extend this base class
 * Compatible with PHP 7.0+
 */
abstract class Controller
{
    protected $route_params = [];

    /**
     * Constructor
     *
     * @param array $route_params Parameters from the matched route
     */
    public function __construct($route_params)
    {
        $this->route_params = $route_params;
    }

    /**
     * Magic method called when a non-existent or inaccessible method is called
     *
     * @param string $name Method name
     * @param array $args Arguments passed to the method
     * @return void
     */
    public function __call($name, $args)
    {
        $method = $name . 'Action';

        if (method_exists($this, $method)) {
            if ($this->before() !== false) {
                call_user_func_array([$this, $method], $args);
                $this->after();
            }
        } else {
            http_response_code(404);
            echo "Method $method not found in controller " . get_class($this);
        }
    }

    /**
     * Before filter - called before each action method
     *
     * @return void
     */
    protected function before()
    {
        // Override in child controllers if needed
    }

    /**
     * After filter - called after each action method
     *
     * @return void
     */
    protected function after()
    {
        // Override in child controllers if needed
    }

    /**
     * Render a view template
     *
     * @param string $template The view template
     * @param array $data Data to pass to the view
     * @return void
     */
    protected function render($template, $data = [])
    {
        extract($data, EXTR_SKIP);

        $file = dirname(__DIR__) . "/views/$template";

        if (is_readable($file)) {
            require $file;
        } else {
            echo "View $file not found";
        }
    }

    /**
     * Render a view template with layout
     *
     * @param string $template The view template
     * @param array $data Data to pass to the view
     * @param string $layout The layout template
     * @return void
     */
    protected function renderWithLayout($template, $data = [], $layout = 'layouts/main.php')
    {
        extract($data, EXTR_SKIP);

        // Start output buffering
        ob_start();

        // Render the view template
        $view_file = dirname(__DIR__) . "/views/$template";
        if (is_readable($view_file)) {
            require $view_file;
        } else {
            echo "View $view_file not found";
        }

        // Get the view content
        $content = ob_get_clean();

        // Render the layout with content
        $layout_file = dirname(__DIR__) . "/views/$layout";
        if (is_readable($layout_file)) {
            require $layout_file;
        } else {
            echo $content;
        }
    }

    /**
     * Redirect to another URL
     *
     * @param string $url The URL to redirect to
     * @return void
     */
    protected function redirect($url)
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Return JSON response
     *
     * @param mixed $data Data to encode as JSON
     * @param int $status_code HTTP status code
     * @return void
     */
    protected function jsonResponse($data, $status_code = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }

    /**
     * Require authentication
     *
     * @return void
     */
    protected function requireAuth()
    {
        if (!Auth::isLoggedIn()) {
            Session::set('redirect_url', $_SERVER['REQUEST_URI']);
            $this->redirect('/login');
        }
    }

    /**
     * Require specific role
     *
     * @param string|array $roles Required role(s)
     * @return void
     */
    protected function requireRole($roles)
    {
        $this->requireAuth();

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $user_role = Auth::user('role');

        if (!in_array($user_role, $roles)) {
            http_response_code(403);
            die('Access denied. Insufficient permissions.');
        }
    }

    /**
     * Get current tenant ID from authenticated user
     *
     * @return int|null
     */
    protected function getTenantId()
    {
        return Auth::user('tenant_id');
    }

    /**
     * Verify CSRF token
     *
     * @return bool
     */
    protected function verifyCsrfToken()
    {
        $token = $_POST['csrf_token'] ?? '';
        return Csrf::verify($token);
    }
}
