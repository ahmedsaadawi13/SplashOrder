<?php
// FILE: /app/services/RBACService.php

namespace App\Services;

/**
 * RBAC Service
 * Advanced Role-Based Access Control
 */
class RBACService
{
    private $db;
    private $cache_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->cache_service = new CacheService();
    }

    /**
     * Check if user has permission
     *
     * @param int $user_id User ID
     * @param string $permission_slug Permission slug
     * @return bool
     */
    public function hasPermission($user_id, $permission_slug)
    {
        // Check cache first
        $cache_key = "user_permissions:{$user_id}";
        $permissions = $this->cache_service->get($cache_key);

        if ($permissions === null) {
            $permissions = $this->getUserPermissions($user_id);
            $this->cache_service->set($cache_key, $permissions, 3600);
        }

        return in_array($permission_slug, $permissions);
    }

    /**
     * Get all permissions for user
     *
     * @param int $user_id User ID
     * @return array
     */
    public function getUserPermissions($user_id)
    {
        $sql = "SELECT DISTINCT p.slug
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return array_column($stmt->fetchAll(), 'slug');
    }

    /**
     * Create role
     *
     * @param int $tenant_id Tenant ID
     * @param string $name Role name
     * @param string $slug Role slug
     * @param string $description Description
     * @return int|false Role ID
     */
    public function createRole($tenant_id, $name, $slug, $description = null)
    {
        $sql = "INSERT INTO roles (tenant_id, name, slug, description)
                VALUES (:tenant_id, :name, :slug, :description)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $tenant_id,
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Assign permission to role
     *
     * @param int $role_id Role ID
     * @param int $permission_id Permission ID
     * @return bool
     */
    public function assignPermissionToRole($role_id, $permission_id)
    {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                VALUES (:role_id, :permission_id)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':role_id' => $role_id,
            ':permission_id' => $permission_id
        ]);
    }

    /**
     * Assign role to user
     *
     * @param int $user_id User ID
     * @param int $role_id Role ID
     * @return bool
     */
    public function assignRoleToUser($user_id, $role_id)
    {
        $sql = "INSERT IGNORE INTO user_roles (user_id, role_id)
                VALUES (:user_id, :role_id)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':role_id' => $role_id
        ]);

        // Clear user permissions cache
        $this->cache_service->delete("user_permissions:{$user_id}");

        return $result;
    }

    /**
     * Remove role from user
     *
     * @param int $user_id User ID
     * @param int $role_id Role ID
     * @return bool
     */
    public function removeRoleFromUser($user_id, $role_id)
    {
        $sql = "DELETE FROM user_roles
                WHERE user_id = :user_id AND role_id = :role_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':role_id' => $role_id
        ]);

        // Clear user permissions cache
        $this->cache_service->delete("user_permissions:{$user_id}");

        return $result;
    }

    /**
     * Get roles for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getTenantRoles($tenant_id)
    {
        $sql = "SELECT * FROM roles
                WHERE tenant_id = :tenant_id
                ORDER BY name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Get all permissions
     *
     * @param string $category Filter by category
     * @return array
     */
    public function getAllPermissions($category = null)
    {
        $sql = "SELECT * FROM permissions";

        if ($category) {
            $sql .= " WHERE category = :category";
        }

        $sql .= " ORDER BY category, name ASC";

        $stmt = $this->db->prepare($sql);

        if ($category) {
            $stmt->execute([':category' => $category]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Get permissions for role
     *
     * @param int $role_id Role ID
     * @return array
     */
    public function getRolePermissions($role_id)
    {
        $sql = "SELECT p.* FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
                ORDER BY p.category, p.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_id' => $role_id]);

        return $stmt->fetchAll();
    }

    /**
     * Sync role permissions
     *
     * @param int $role_id Role ID
     * @param array $permission_ids Array of permission IDs
     * @return bool
     */
    public function syncRolePermissions($role_id, $permission_ids)
    {
        // Delete existing permissions
        $sql = "DELETE FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_id' => $role_id]);

        // Add new permissions
        foreach ($permission_ids as $permission_id) {
            $this->assignPermissionToRole($role_id, $permission_id);
        }

        // Clear cache for all users with this role
        $this->clearRoleCache($role_id);

        return true;
    }

    /**
     * Clear cache for users with specific role
     *
     * @param int $role_id Role ID
     * @return void
     */
    private function clearRoleCache($role_id)
    {
        $sql = "SELECT user_id FROM user_roles WHERE role_id = :role_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role_id' => $role_id]);
        $users = $stmt->fetchAll();

        foreach ($users as $user) {
            $this->cache_service->delete("user_permissions:{$user['user_id']}");
        }
    }
}
