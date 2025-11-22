<?php
// FILE: /app/core/Router.php

/**
 * Router Class
 * Handles URL routing and dispatches requests to appropriate controllers
 * Compatible with PHP 7.0+
 */
class Router
{
    private $routes = [];
    private $params = [];

    /**
     * Add a route to the routing table
     *
     * @param string $route The route pattern
     * @param array $params Parameters (controller, action, etc.)
     * @return void
     */
    public function add($route, $params = [])
    {
        // Convert the route to a regular expression
        $route = preg_replace('/\//', '\\/', $route);
        $route = preg_replace('/\{([a-z]+)\}/', '(?P<\1>[a-z0-9-]+)', $route);
        $route = preg_replace('/\{([a-z]+):([^\}]+)\}/', '(?P<\1>\2)', $route);
        $route = '/^' . $route . '$/i';

        $this->routes[$route] = $params;
    }

    /**
     * Get all routes
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Match the route to the routes in the routing table
     *
     * @param string $url The route URL
     * @return boolean
     */
    public function match($url)
    {
        foreach ($this->routes as $route => $params) {
            if (preg_match($route, $url, $matches)) {
                foreach ($matches as $key => $match) {
                    if (is_string($key)) {
                        $params[$key] = $match;
                    }
                }
                $this->params = $params;
                return true;
            }
        }
        return false;
    }

    /**
     * Get the currently matched parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Dispatch the route, creating the controller object and running the action method
     *
     * @param string $url The route URL
     * @return void
     */
    public function dispatch($url)
    {
        $url = $this->removeQueryStringVariables($url);

        if ($this->match($url)) {
            $controller = $this->params['controller'];
            $controller = $this->convertToStudlyCaps($controller);
            $controller = $this->getNamespace() . $controller;

            if (class_exists($controller)) {
                $controller_object = new $controller($this->params);

                $action = $this->params['action'];
                $action = $this->convertToCamelCase($action);

                if (is_callable([$controller_object, $action])) {
                    $controller_object->$action();
                } else {
                    $this->notFound("Method $action not found in controller $controller");
                }
            } else {
                $this->notFound("Controller class $controller not found");
            }
        } else {
            $this->notFound("No route matched");
        }
    }

    /**
     * Convert string to StudlyCaps
     *
     * @param string $string
     * @return string
     */
    private function convertToStudlyCaps($string)
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));
    }

    /**
     * Convert string to camelCase
     *
     * @param string $string
     * @return string
     */
    private function convertToCamelCase($string)
    {
        return lcfirst($this->convertToStudlyCaps($string));
    }

    /**
     * Remove query string variables from URL
     *
     * @param string $url
     * @return string
     */
    private function removeQueryStringVariables($url)
    {
        if ($url != '') {
            $parts = explode('&', $url, 2);
            if (strpos($parts[0], '=') === false) {
                $url = $parts[0];
            } else {
                $url = '';
            }
        }
        return $url;
    }

    /**
     * Get the namespace for the controller class
     *
     * @return string
     */
    private function getNamespace()
    {
        $namespace = 'App\Controllers\\';

        if (array_key_exists('namespace', $this->params)) {
            $namespace .= $this->params['namespace'] . '\\';
        }

        return $namespace;
    }

    /**
     * Show 404 not found page
     *
     * @param string $message
     * @return void
     */
    private function notFound($message = 'Page not found')
    {
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        echo "<p>$message</p>";
        exit;
    }
}
