<?php
// FILE: /app/core/Model.php

namespace App\Core;

use PDO;

/**
 * Base Model Class
 * All models extend this base class
 * Provides common database operations with multi-tenancy support
 * Compatible with PHP 7.0+
 */
abstract class Model
{
    protected $db;
    protected $table;
    protected $tenant_id = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Set tenant ID for multi-tenant queries
     *
     * @param int $tenant_id
     * @return void
     */
    public function setTenantId($tenant_id)
    {
        $this->tenant_id = $tenant_id;
    }

    /**
     * Find all records with optional filters
     *
     * @param array $conditions WHERE conditions
     * @param string $order_by ORDER BY clause
     * @param int $limit LIMIT
     * @param int $offset OFFSET
     * @return array
     */
    public function findAll($conditions = [], $order_by = 'id DESC', $limit = null, $offset = 0)
    {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY {$order_by}";

        if ($limit !== null) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = $this->db->prepare($sql);
        $this->bindConditions($stmt, $conditions);

        if ($limit !== null) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find a single record by ID
     *
     * @param int $id
     * @return array|null
     */
    public function findById($id)
    {
        $conditions = ['id' => $id];

        // Add tenant filter if tenant_id is set
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id')) {
            $conditions['tenant_id'] = $this->tenant_id;
        }

        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM {$this->table} {$where} LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $this->bindConditions($stmt, $conditions);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Find a single record by conditions
     *
     * @param array $conditions
     * @return array|null
     */
    public function findOne($conditions)
    {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT * FROM {$this->table} {$where} LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $this->bindConditions($stmt, $conditions);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? $result : null;
    }

    /**
     * Count records
     *
     * @param array $conditions
     * @return int
     */
    public function count($conditions = [])
    {
        $where = $this->buildWhereClause($conditions);
        $sql = "SELECT COUNT(*) as count FROM {$this->table} {$where}";

        $stmt = $this->db->prepare($sql);
        $this->bindConditions($stmt, $conditions);
        $stmt->execute();

        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * Insert a new record
     *
     * @param array $data
     * @return int Last insert ID
     */
    public function insert($data)
    {
        // Add tenant_id if set and table supports it
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id') && !isset($data['tenant_id'])) {
            $data['tenant_id'] = $this->tenant_id;
        }

        // Add timestamps
        if ($this->hasColumn('created_at') && !isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if ($this->hasColumn('updated_at') && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = array_map(function ($col) {
            return ':' . $col;
        }, $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
        return $this->db->lastInsertId();
    }

    /**
     * Update a record
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data)
    {
        // Add updated_at timestamp
        if ($this->hasColumn('updated_at') && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $set_parts = [];
        foreach (array_keys($data) as $column) {
            $set_parts[] = "$column = :$column";
        }

        $conditions = ['id' => $id];

        // Add tenant filter if tenant_id is set
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id')) {
            $conditions['tenant_id'] = $this->tenant_id;
        }

        $where = $this->buildWhereClause($conditions);
        $sql = "UPDATE {$this->table} SET " . implode(', ', $set_parts) . " {$where}";

        $stmt = $this->db->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $this->bindConditions($stmt, $conditions);
        return $stmt->execute();
    }

    /**
     * Delete a record
     *
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $conditions = ['id' => $id];

        // Add tenant filter if tenant_id is set
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id')) {
            $conditions['tenant_id'] = $this->tenant_id;
        }

        $where = $this->buildWhereClause($conditions);
        $sql = "DELETE FROM {$this->table} {$where}";

        $stmt = $this->db->prepare($sql);
        $this->bindConditions($stmt, $conditions);
        return $stmt->execute();
    }

    /**
     * Build WHERE clause from conditions
     *
     * @param array $conditions
     * @return string
     */
    private function buildWhereClause($conditions)
    {
        // Add tenant filter if tenant_id is set
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id') && !isset($conditions['tenant_id'])) {
            $conditions['tenant_id'] = $this->tenant_id;
        }

        if (empty($conditions)) {
            return '';
        }

        $where_parts = [];
        foreach (array_keys($conditions) as $column) {
            $where_parts[] = "$column = :where_$column";
        }

        return 'WHERE ' . implode(' AND ', $where_parts);
    }

    /**
     * Bind conditions to prepared statement
     *
     * @param \PDOStatement $stmt
     * @param array $conditions
     * @return void
     */
    private function bindConditions($stmt, $conditions)
    {
        // Add tenant filter if tenant_id is set
        if ($this->tenant_id !== null && $this->hasColumn('tenant_id') && !isset($conditions['tenant_id'])) {
            $conditions['tenant_id'] = $this->tenant_id;
        }

        foreach ($conditions as $key => $value) {
            $stmt->bindValue(':where_' . $key, $value);
        }
    }

    /**
     * Check if table has a specific column
     * Override this in child models to specify available columns
     *
     * @param string $column
     * @return bool
     */
    protected function hasColumn($column)
    {
        // By default, assume common columns exist
        $common_columns = ['id', 'tenant_id', 'created_at', 'updated_at'];
        return in_array($column, $common_columns);
    }

    /**
     * Execute a custom query
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function query($sql, $params = [])
    {
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->db->rollBack();
    }
}
