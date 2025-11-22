<?php
// FILE: /app/core/Session.php

/**
 * Session Management Class
 * Handles session operations
 * Compatible with PHP 7.0+
 */
class Session
{
    /**
     * Start the session if not already started
     *
     * @return void
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Set a session variable
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session variable
     *
     * @param string $key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     *
     * @param string $key
     * @return bool
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session variable
     *
     * @param string $key
     * @return void
     */
    public static function remove($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the session
     *
     * @return void
     */
    public static function destroy()
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Set a flash message
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function setFlash($key, $value)
    {
        self::set('flash_' . $key, $value);
    }

    /**
     * Get and remove a flash message
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getFlash($key, $default = null)
    {
        $value = self::get('flash_' . $key, $default);
        self::remove('flash_' . $key);
        return $value;
    }

    /**
     * Check if flash message exists
     *
     * @param string $key
     * @return bool
     */
    public static function hasFlash($key)
    {
        return self::has('flash_' . $key);
    }
}
