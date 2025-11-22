<?php
// FILE: /app/core/helpers.php

/**
 * Helper Functions
 * Global utility functions for the application
 * Compatible with PHP 7.0+
 */

/**
 * Escape HTML output
 *
 * @param string $string
 * @return string
 */
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get base URL
 *
 * @param string $path
 * @return string
 */
function url($path = '')
{
    $base_url = $_ENV['APP_URL'] ?? 'http://localhost';
    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

/**
 * Get asset URL
 *
 * @param string $path
 * @return string
 */
function asset($path)
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Redirect to URL
 *
 * @param string $url
 * @return void
 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Get old input value (for form repopulation)
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function old($key, $default = '')
{
    return Session::get('old_' . $key, $default);
}

/**
 * Set old input values
 *
 * @param array $data
 * @return void
 */
function setOldInput($data)
{
    foreach ($data as $key => $value) {
        Session::set('old_' . $key, $value);
    }
}

/**
 * Clear old input values
 *
 * @return void
 */
function clearOldInput()
{
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'old_') === 0) {
            Session::remove($key);
        }
    }
}

/**
 * Format currency
 *
 * @param float $amount
 * @param string $currency
 * @return string
 */
function currency($amount, $currency = 'USD')
{
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Format date
 *
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'Y-m-d H:i:s')
{
    if (empty($date)) {
        return '';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Get time ago string
 *
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

/**
 * Truncate string
 *
 * @param string $string
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncate($string, $length = 100, $suffix = '...')
{
    if (strlen($string) <= $length) {
        return $string;
    }

    return substr($string, 0, $length) . $suffix;
}

/**
 * Generate pagination links
 *
 * @param int $current_page
 * @param int $total_pages
 * @param string $base_url
 * @return string
 */
function pagination($current_page, $total_pages, $base_url)
{
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<ul class="pagination">';

    // Previous button
    if ($current_page > 1) {
        $prev_page = $current_page - 1;
        $html .= '<li><a href="' . $base_url . '?page=' . $prev_page . '">&laquo; Previous</a></li>';
    }

    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i === $current_page) ? 'class="active"' : '';
        $html .= '<li ' . $active . '><a href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
    }

    // Next button
    if ($current_page < $total_pages) {
        $next_page = $current_page + 1;
        $html .= '<li><a href="' . $base_url . '?page=' . $next_page . '">Next &raquo;</a></li>';
    }

    $html .= '</ul>';

    return $html;
}

/**
 * Debug helper
 *
 * @param mixed $data
 * @param bool $die
 * @return void
 */
function dd($data, $die = true)
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';

    if ($die) {
        die();
    }
}

/**
 * Get configuration value
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config($key, $default = null)
{
    static $config = null;

    if ($config === null) {
        $config = require dirname(dirname(__DIR__)) . '/config/app.php';
    }

    $keys = explode('.', $key);
    $value = $config;

    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }

    return $value;
}

/**
 * Check if value is empty but allow '0'
 *
 * @param mixed $value
 * @return bool
 */
function isEmpty($value)
{
    return empty($value) && $value !== '0' && $value !== 0;
}

/**
 * Generate random string
 *
 * @param int $length
 * @return string
 */
function randomString($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Slugify string
 *
 * @param string $string
 * @return string
 */
function slugify($string)
{
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}
