<?php
// FILE: /app/core/Auth.php

/**
 * Authentication Helper Class
 * Handles user authentication and authorization
 * Compatible with PHP 7.0+
 */
class Auth
{
    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public static function isLoggedIn()
    {
        return Session::has('user_id');
    }

    /**
     * Login user
     *
     * @param array $user User data
     * @return void
     */
    public static function login($user)
    {
        Session::set('user_id', $user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);
        Session::set('tenant_id', $user['tenant_id']);
    }

    /**
     * Logout user
     *
     * @return void
     */
    public static function logout()
    {
        Session::destroy();
    }

    /**
     * Get user data
     *
     * @param string|null $key Specific key to get, or null for all data
     * @return mixed
     */
    public static function user($key = null)
    {
        if ($key === null) {
            return [
                'id' => Session::get('user_id'),
                'email' => Session::get('user_email'),
                'name' => Session::get('user_name'),
                'role' => Session::get('user_role'),
                'tenant_id' => Session::get('tenant_id'),
            ];
        }

        $map = [
            'id' => 'user_id',
            'email' => 'user_email',
            'name' => 'user_name',
            'role' => 'user_role',
            'tenant_id' => 'tenant_id',
        ];

        return Session::get($map[$key] ?? $key);
    }

    /**
     * Check if user has specific role
     *
     * @param string|array $roles
     * @return bool
     */
    public static function hasRole($roles)
    {
        if (!self::isLoggedIn()) {
            return false;
        }

        if (!is_array($roles)) {
            $roles = [$roles];
        }

        return in_array(self::user('role'), $roles);
    }

    /**
     * Check if user is platform admin
     *
     * @return bool
     */
    public static function isPlatformAdmin()
    {
        return self::hasRole('platform_admin');
    }

    /**
     * Check if user is tenant admin
     *
     * @return bool
     */
    public static function isTenantAdmin()
    {
        return self::hasRole('tenant_admin');
    }

    /**
     * Check if user is staff
     *
     * @return bool
     */
    public static function isStaff()
    {
        return self::hasRole('staff');
    }

    /**
     * Get tenant ID of current user
     *
     * @return int|null
     */
    public static function tenantId()
    {
        return self::user('tenant_id');
    }
}
