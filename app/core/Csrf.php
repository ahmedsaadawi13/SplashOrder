<?php
// FILE: /app/core/Csrf.php

/**
 * CSRF Protection Class
 * Generates and validates CSRF tokens
 * Compatible with PHP 7.0+
 */
class Csrf
{
    /**
     * Generate a new CSRF token
     *
     * @return string
     */
    public static function generate()
    {
        if (!Session::has('csrf_token')) {
            Session::set('csrf_token', bin2hex(random_bytes(32)));
        }
        return Session::get('csrf_token');
    }

    /**
     * Verify a CSRF token
     *
     * @param string $token
     * @return bool
     */
    public static function verify($token)
    {
        if (!Session::has('csrf_token')) {
            return false;
        }

        return hash_equals(Session::get('csrf_token'), $token);
    }

    /**
     * Generate CSRF input field for forms
     *
     * @return string
     */
    public static function field()
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
