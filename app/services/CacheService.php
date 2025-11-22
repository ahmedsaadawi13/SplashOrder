<?php
// FILE: /app/services/CacheService.php

namespace App\Services;

/**
 * Cache Service
 * Provides caching layer with Redis support and fallback to file-based cache
 */
class CacheService
{
    private $redis = null;
    private $enabled = false;
    private $use_redis = false;
    private $cache_dir;

    // Default TTL (seconds)
    const DEFAULT_TTL = 3600; // 1 hour

    public function __construct()
    {
        $this->cache_dir = __DIR__ . '/../../storage/cache';

        // Create cache directory if it doesn't exist
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }

        // Try to connect to Redis
        if (extension_loaded('redis')) {
            try {
                $this->redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: 'localhost';
                $port = getenv('REDIS_PORT') ?: 6379;

                if ($this->redis->connect($host, $port, 2)) {
                    $this->use_redis = true;
                    $this->enabled = true;

                    // Set key prefix
                    $this->redis->setOption(\Redis::OPT_PREFIX, 'splashorder:');
                }
            } catch (\Exception $e) {
                // Redis not available, will use file cache
                $this->use_redis = false;
                $this->enabled = true;
            }
        } else {
            // Redis extension not loaded, use file cache
            $this->enabled = true;
        }
    }

    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!$this->enabled) {
            return $default;
        }

        if ($this->use_redis) {
            $value = $this->redis->get($key);
            return $value !== false ? $this->unserialize($value) : $default;
        } else {
            return $this->getFromFile($key, $default);
        }
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl TTL in seconds
     * @return bool
     */
    public function set($key, $value, $ttl = self::DEFAULT_TTL)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->setex($key, $ttl, $this->serialize($value));
        } else {
            return $this->setToFile($key, $value, $ttl);
        }
    }

    /**
     * Check if key exists
     *
     * @param string $key Cache key
     * @return bool
     */
    public function has($key)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->exists($key) > 0;
        } else {
            return $this->get($key) !== null;
        }
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @return bool
     */
    public function delete($key)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->del($key) > 0;
        } else {
            return $this->deleteFromFile($key);
        }
    }

    /**
     * Clear all cache
     *
     * @return bool
     */
    public function clear()
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->flushDB();
        } else {
            return $this->clearFileCache();
        }
    }

    /**
     * Get or set cached value
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value if not cached
     * @param int $ttl TTL in seconds
     * @return mixed
     */
    public function remember($key, $callback, $ttl = self::DEFAULT_TTL)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Increment value
     *
     * @param string $key Cache key
     * @param int $value Increment by
     * @return int|false New value or false
     */
    public function increment($key, $value = 1)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->incrBy($key, $value);
        } else {
            $current = (int)$this->get($key, 0);
            $new_value = $current + $value;
            $this->set($key, $new_value);
            return $new_value;
        }
    }

    /**
     * Decrement value
     *
     * @param string $key Cache key
     * @param int $value Decrement by
     * @return int|false New value or false
     */
    public function decrement($key, $value = 1)
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->use_redis) {
            return $this->redis->decrBy($key, $value);
        } else {
            $current = (int)$this->get($key, 0);
            $new_value = $current - $value;
            $this->set($key, $new_value);
            return $new_value;
        }
    }

    /**
     * Get multiple values
     *
     * @param array $keys Cache keys
     * @return array
     */
    public function getMultiple($keys)
    {
        if (!$this->enabled) {
            return array_fill_keys($keys, null);
        }

        if ($this->use_redis) {
            $values = $this->redis->mGet($keys);
            $result = [];
            foreach ($keys as $index => $key) {
                $result[$key] = $values[$index] !== false ? $this->unserialize($values[$index]) : null;
            }
            return $result;
        } else {
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->get($key);
            }
            return $result;
        }
    }

    /**
     * Set multiple values
     *
     * @param array $values Key-value pairs
     * @param int $ttl TTL in seconds
     * @return bool
     */
    public function setMultiple($values, $ttl = self::DEFAULT_TTL)
    {
        if (!$this->enabled) {
            return false;
        }

        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * Add tags to cache key
     *
     * @param string $key Cache key
     * @param array $tags Tags
     * @return bool
     */
    public function tag($key, $tags)
    {
        if (!$this->enabled || !$this->use_redis) {
            return false;
        }

        foreach ($tags as $tag) {
            $this->redis->sAdd("tag:$tag", $key);
        }

        return true;
    }

    /**
     * Clear cache by tag
     *
     * @param string $tag Tag name
     * @return int Number of keys deleted
     */
    public function clearTag($tag)
    {
        if (!$this->enabled || !$this->use_redis) {
            return 0;
        }

        $keys = $this->redis->sMembers("tag:$tag");
        $deleted = 0;

        foreach ($keys as $key) {
            if ($this->redis->del($key) > 0) {
                $deleted++;
            }
        }

        $this->redis->del("tag:$tag");

        return $deleted;
    }

    /**
     * Get from file cache
     *
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed
     */
    private function getFromFile($key, $default = null)
    {
        $file = $this->getCacheFilePath($key);

        if (!file_exists($file)) {
            return $default;
        }

        $data = @unserialize(file_get_contents($file));

        if (!$data || !isset($data['expires_at'], $data['value'])) {
            return $default;
        }

        // Check expiration
        if ($data['expires_at'] < time()) {
            $this->deleteFromFile($key);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Set to file cache
     *
     * @param string $key Cache key
     * @param mixed $value Value
     * @param int $ttl TTL in seconds
     * @return bool
     */
    private function setToFile($key, $value, $ttl)
    {
        $file = $this->getCacheFilePath($key);
        $data = [
            'expires_at' => time() + $ttl,
            'value' => $value
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    /**
     * Delete from file cache
     *
     * @param string $key Cache key
     * @return bool
     */
    private function deleteFromFile($key)
    {
        $file = $this->getCacheFilePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Clear file cache
     *
     * @return bool
     */
    private function clearFileCache()
    {
        $files = glob($this->cache_dir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Get cache file path
     *
     * @param string $key Cache key
     * @return string
     */
    private function getCacheFilePath($key)
    {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }

    /**
     * Serialize value
     *
     * @param mixed $value Value to serialize
     * @return string
     */
    private function serialize($value)
    {
        return serialize($value);
    }

    /**
     * Unserialize value
     *
     * @param string $value Serialized value
     * @return mixed
     */
    private function unserialize($value)
    {
        return unserialize($value);
    }

    /**
     * Check if using Redis
     *
     * @return bool
     */
    public function isUsingRedis()
    {
        return $this->use_redis;
    }

    /**
     * Get cache stats
     *
     * @return array
     */
    public function getStats()
    {
        if ($this->use_redis) {
            return [
                'type' => 'redis',
                'info' => $this->redis->info()
            ];
        } else {
            $files = glob($this->cache_dir . '/*');
            return [
                'type' => 'file',
                'count' => count($files),
                'size' => array_sum(array_map('filesize', $files))
            ];
        }
    }
}
