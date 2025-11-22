<?php
// FILE: /app/core/Upload.php

/**
 * File Upload Handler Class
 * Handles file uploads with validation and security
 * Compatible with PHP 7.0+
 */
class Upload
{
    private $file;
    private $errors = [];
    private $allowed_extensions = [];
    private $max_size = 5242880; // 5MB default
    private $upload_path = '';

    /**
     * Constructor
     *
     * @param array $file File from $_FILES
     */
    public function __construct($file)
    {
        $this->file = $file;
        $this->upload_path = dirname(dirname(__DIR__)) . '/storage/uploads/';
    }

    /**
     * Set allowed file extensions
     *
     * @param array $extensions
     * @return self
     */
    public function allowedExtensions($extensions)
    {
        $this->allowed_extensions = $extensions;
        return $this;
    }

    /**
     * Set maximum file size in bytes
     *
     * @param int $size
     * @return self
     */
    public function maxSize($size)
    {
        $this->max_size = $size;
        return $this;
    }

    /**
     * Set upload path
     *
     * @param string $path
     * @return self
     */
    public function path($path)
    {
        $this->upload_path = $path;
        return $this;
    }

    /**
     * Validate the file
     *
     * @return bool
     */
    public function validate()
    {
        // Check if file was uploaded
        if (!isset($this->file['error']) || $this->file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'File upload failed.';
            return false;
        }

        // Check file size
        if ($this->file['size'] > $this->max_size) {
            $max_mb = $this->max_size / 1048576;
            $this->errors[] = "File size must not exceed {$max_mb}MB.";
        }

        // Check file extension
        if (!empty($this->allowed_extensions)) {
            $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowed_extensions)) {
                $this->errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $this->allowed_extensions);
            }
        }

        // Validate image files
        if ($this->isImage()) {
            $image_info = getimagesize($this->file['tmp_name']);
            if ($image_info === false) {
                $this->errors[] = 'Invalid image file.';
            }
        }

        return empty($this->errors);
    }

    /**
     * Upload the file
     *
     * @param string|null $filename Custom filename (optional)
     * @return string|false Uploaded filename or false on failure
     */
    public function upload($filename = null)
    {
        if (!$this->validate()) {
            return false;
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($this->upload_path)) {
            mkdir($this->upload_path, 0755, true);
        }

        // Generate unique filename if not provided
        if ($filename === null) {
            $extension = pathinfo($this->file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
        }

        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);

        // Full upload path
        $destination = $this->upload_path . $filename;

        // Move uploaded file
        if (move_uploaded_file($this->file['tmp_name'], $destination)) {
            return $filename;
        } else {
            $this->errors[] = 'Failed to move uploaded file.';
            return false;
        }
    }

    /**
     * Sanitize filename to prevent directory traversal
     *
     * @param string $filename
     * @return string
     */
    private function sanitizeFilename($filename)
    {
        // Remove any path information
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Check if file is an image
     *
     * @return bool
     */
    private function isImage()
    {
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($this->file['name'], PATHINFO_EXTENSION));
        return in_array($extension, $image_extensions);
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get first error
     *
     * @return string|null
     */
    public function getFirstError()
    {
        return !empty($this->errors) ? $this->errors[0] : null;
    }

    /**
     * Delete a file
     *
     * @param string $filename
     * @return bool
     */
    public static function delete($filename)
    {
        $path = dirname(dirname(__DIR__)) . '/storage/uploads/' . $filename;

        if (file_exists($path)) {
            return unlink($path);
        }

        return false;
    }
}
