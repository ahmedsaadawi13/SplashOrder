<?php
// FILE: /app/services/SmsService.php

namespace App\Services;

/**
 * SMS Service
 * Handles SMS notifications using Twilio
 * For production, install Twilio SDK: composer require twilio/sdk
 */
class SmsService
{
    private $twilio_sid;
    private $twilio_token;
    private $twilio_from;
    private $enabled;

    public function __construct()
    {
        $this->twilio_sid = $_ENV['TWILIO_SID'] ?? '';
        $this->twilio_token = $_ENV['TWILIO_TOKEN'] ?? '';
        $this->twilio_from = $_ENV['TWILIO_FROM'] ?? '';
        $this->enabled = !empty($this->twilio_sid) && !empty($this->twilio_token);
    }

    /**
     * Send order confirmation SMS
     *
     * @param string $phone Customer phone
     * @param string $order_number Order number
     * @return bool
     */
    public function sendOrderConfirmation($phone, $order_number)
    {
        $message = "Your order {$order_number} has been confirmed! We'll notify you when it's ready.";
        return $this->send($phone, $message);
    }

    /**
     * Send order status update SMS
     *
     * @param string $phone Customer phone
     * @param string $order_number Order number
     * @param string $status New status
     * @return bool
     */
    public function sendOrderStatusUpdate($phone, $order_number, $status)
    {
        $messages = [
            'confirmed' => "Order {$order_number} confirmed! Your food is being prepared.",
            'preparing' => "Order {$order_number} is being prepared in our kitchen!",
            'out_for_delivery' => "Order {$order_number} is out for delivery! It'll arrive soon.",
            'delivered' => "Order {$order_number} has been delivered. Enjoy your meal!",
            'ready_for_pickup' => "Order {$order_number} is ready for pickup!"
        ];

        $message = $messages[$status] ?? "Order {$order_number} status updated to: {$status}";
        return $this->send($phone, $message);
    }

    /**
     * Send delivery driver assigned notification
     *
     * @param string $phone Customer phone
     * @param string $order_number Order number
     * @param string $driver_name Driver name
     * @return bool
     */
    public function sendDriverAssigned($phone, $order_number, $driver_name)
    {
        $message = "{$driver_name} is delivering your order {$order_number}. Track your delivery in real-time!";
        return $this->send($phone, $message);
    }

    /**
     * Send promotional SMS
     *
     * @param string $phone Customer phone
     * @param string $message Promotional message
     * @return bool
     */
    public function sendPromotional($phone, $message)
    {
        $message .= " Reply STOP to unsubscribe.";
        return $this->send($phone, $message);
    }

    /**
     * Send OTP for verification
     *
     * @param string $phone Phone number
     * @param string $otp OTP code
     * @return bool
     */
    public function sendOTP($phone, $otp)
    {
        $message = "Your SplashOrder verification code is: {$otp}. Valid for 10 minutes.";
        return $this->send($phone, $message);
    }

    /**
     * Send loyalty points notification
     *
     * @param string $phone Customer phone
     * @param int $points Points earned
     * @param int $total Total points
     * @return bool
     */
    public function sendLoyaltyPoints($phone, $points, $total)
    {
        $message = "You've earned {$points} loyalty points! Your total: {$total} points. Use them on your next order!";
        return $this->send($phone, $message);
    }

    /**
     * Send SMS using Twilio
     *
     * @param string $to Recipient phone number
     * @param string $message SMS message
     * @return bool
     */
    private function send($to, $message)
    {
        // Log SMS for development/testing
        $this->logSms($to, $message);

        if (!$this->enabled) {
            error_log("SMS not sent (Twilio not configured): {$to} - {$message}");
            return false;
        }

        // Production: Use Twilio SDK
        if (class_exists('Twilio\\Rest\\Client')) {
            return $this->sendWithTwilio($to, $message);
        }

        // Fallback: Log only
        error_log("SMS would be sent to {$to}: {$message}");
        return true;
    }

    /**
     * Send SMS using Twilio SDK
     *
     * @param string $to Recipient phone
     * @param string $message Message
     * @return bool
     */
    private function sendWithTwilio($to, $message)
    {
        try {
            $client = new \Twilio\Rest\Client($this->twilio_sid, $this->twilio_token);

            $client->messages->create(
                $to,
                [
                    'from' => $this->twilio_from,
                    'body' => $message
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Twilio SMS failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log SMS for development/testing
     *
     * @param string $to Recipient
     * @param string $message Message
     * @return void
     */
    private function logSms($to, $message)
    {
        $log_dir = dirname(dirname(__DIR__)) . '/storage/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/sms.log';
        $log_entry = date('Y-m-d H:i:s') . " | TO: {$to} | MESSAGE: {$message}\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number
     * @return bool
     */
    public function isValidPhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if phone number is valid (10-15 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }

    /**
     * Format phone number for Twilio (E.164 format)
     *
     * @param string $phone Phone number
     * @param string $country_code Country code (default: +1 for US)
     * @return string
     */
    public function formatPhone($phone, $country_code = '+1')
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if not present
        if (!str_starts_with($phone, '+')) {
            $phone = $country_code . $phone;
        }

        return $phone;
    }
}
