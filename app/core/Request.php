<?php
// FILE: /app/core/Request.php

/**
 * HTTP Request Class
 * Handles HTTP request data
 * Compatible with PHP 7.0+
 */
class Request
{
    /**
     * Get all input data
     *
     * @return array
     */
    public static function all()
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Get specific input value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * Get value from POST data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function post($key = null, $default = null)
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Get value from GET data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function query($key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Check if input exists
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        return isset(self::all()[$key]);
    }

    /**
     * Get request method
     *
     * @return string
     */
    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Check if request is POST
     *
     * @return bool
     */
    public static function isPost()
    {
        return self::method() === 'POST';
    }

    /**
     * Check if request is GET
     *
     * @return bool
     */
    public static function isGet()
    {
        return self::method() === 'GET';
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get request URI
     *
     * @return string
     */
    public static function uri()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Get uploaded file
     *
     * @param string $key
     * @return array|null
     */
    public static function file($key)
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Check if file was uploaded
     *
     * @param string $key
     * @return bool
     */
    public static function hasFile($key)
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public static function ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public static function userAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Get JSON input data (for API requests)
     *
     * @return array
     */
    public static function json()
    {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
}
