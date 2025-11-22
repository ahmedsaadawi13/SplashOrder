<?php
// FILE: /app/models/User.php

namespace App\Models;

use App\Core\Model;

/**
 * User Model
 * Represents system users (platform admins, tenant admins, staff)
 */
class User extends Model
{
    protected $table = 'users';

    /**
     * Check if table has a specific column
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        $columns = ['id', 'tenant_id', 'name', 'email', 'password', 'role', 'status', 'last_login', 'created_at', 'updated_at'];
        return in_array($column, $columns);
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $result = $this->query($sql, [':email' => $email]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Authenticate user
     *
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public function authenticate($email, $password)
    {
        $user = $this->findByEmail($email);

        if (!$user) {
            return false;
        }

        if ($user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Update last login
        $this->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        return $user;
    }

    /**
     * Create new user
     *
     * @param array $data
     * @return int|false
     */
    public function createUser($data)
    {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return $this->insert($data);
    }

    /**
     * Update user password
     *
     * @param int $user_id
     * @param string $new_password
     * @return bool
     */
    public function updatePassword($user_id, $new_password)
    {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        return $this->update($user_id, ['password' => $hashed]);
    }

    /**
     * Get users by tenant
     *
     * @param int $tenant_id
     * @return array
     */
    public function getUsersByTenant($tenant_id)
    {
        return $this->findAll(['tenant_id' => $tenant_id]);
    }

    /**
     * Get users by role
     *
     * @param string $role
     * @return array
     */
    public function getUsersByRole($role)
    {
        return $this->findAll(['role' => $role]);
    }
}
