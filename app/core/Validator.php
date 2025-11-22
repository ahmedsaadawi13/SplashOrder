<?php
// FILE: /app/core/Validator.php

/**
 * Input Validation Class
 * Validates and sanitizes user input
 * Compatible with PHP 7.0+
 */
class Validator
{
    private $data = [];
    private $errors = [];
    private $rules = [];

    /**
     * Constructor
     *
     * @param array $data Data to validate
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Set validation rules
     *
     * @param array $rules
     * @return self
     */
    public function rules($rules)
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Validate the data
     *
     * @return bool
     */
    public function validate()
    {
        foreach ($this->rules as $field => $rules_string) {
            $rules = explode('|', $rules_string);

            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Apply a single validation rule
     *
     * @param string $field
     * @param string $rule
     * @return void
     */
    private function applyRule($field, $rule)
    {
        $value = $this->data[$field] ?? null;

        // Parse rule with parameters
        $parts = explode(':', $rule);
        $rule_name = $parts[0];
        $parameter = $parts[1] ?? null;

        switch ($rule_name) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, ucfirst($field) . ' is required.');
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid email address.');
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $parameter) {
                    $this->addError($field, ucfirst($field) . " must be at least $parameter characters.");
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $parameter) {
                    $this->addError($field, ucfirst($field) . " must not exceed $parameter characters.");
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, ucfirst($field) . ' must be a number.');
                }
                break;

            case 'integer':
                if (!empty($value) && !ctype_digit(strval($value))) {
                    $this->addError($field, ucfirst($field) . ' must be an integer.');
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, ucfirst($field) . ' must be a valid URL.');
                }
                break;

            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, ucfirst($field) . ' must contain only letters.');
                }
                break;

            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, ucfirst($field) . ' must contain only letters and numbers.');
                }
                break;

            case 'in':
                $allowed = explode(',', $parameter);
                if (!empty($value) && !in_array($value, $allowed)) {
                    $this->addError($field, ucfirst($field) . ' must be one of: ' . implode(', ', $allowed) . '.');
                }
                break;

            case 'match':
                $match_field = $parameter;
                $match_value = $this->data[$match_field] ?? null;
                if ($value !== $match_value) {
                    $this->addError($field, ucfirst($field) . ' must match ' . ucfirst($match_field) . '.');
                }
                break;
        }
    }

    /**
     * Add an error
     *
     * @param string $field
     * @param string $message
     * @return void
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get all errors
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     *
     * @param string $field
     * @return array
     */
    public function getError($field)
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for a specific field
     *
     * @param string $field
     * @return string|null
     */
    public function getFirstError($field)
    {
        $errors = $this->getError($field);
        return !empty($errors) ? $errors[0] : null;
    }

    /**
     * Check if validation passed
     *
     * @return bool
     */
    public function passed()
    {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function failed()
    {
        return !$this->passed();
    }

    /**
     * Sanitize a string
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeString($value)
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email
     *
     * @param string $email
     * @return string
     */
    public static function sanitizeEmail($email)
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize integer
     *
     * @param mixed $value
     * @return int
     */
    public static function sanitizeInt($value)
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float
     *
     * @param mixed $value
     * @return float
     */
    public static function sanitizeFloat($value)
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}
