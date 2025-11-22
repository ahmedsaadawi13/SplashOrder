<?php
// FILE: /app/services/EmailService.php

namespace App\Services;

/**
 * Email Service
 * Handles email notifications using PHPMailer
 * For production, install PHPMailer via composer: composer require phpmailer/phpmailer
 */
class EmailService
{
    private $from_email;
    private $from_name;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $use_smtp;

    public function __construct()
    {
        $this->from_email = $_ENV['MAIL_FROM'] ?? 'noreply@splashorder.com';
        $this->from_name = $_ENV['MAIL_FROM_NAME'] ?? 'SplashOrder';
        $this->smtp_host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $this->smtp_port = $_ENV['SMTP_PORT'] ?? 587;
        $this->smtp_username = $_ENV['SMTP_USERNAME'] ?? '';
        $this->smtp_password = $_ENV['SMTP_PASSWORD'] ?? '';
        $this->use_smtp = ($_ENV['MAIL_DRIVER'] ?? 'smtp') === 'smtp';
    }

    /**
     * Send order confirmation to customer
     *
     * @param array $order Order data
     * @param array $customer Customer data
     * @return bool
     */
    public function sendOrderConfirmation($order, $customer)
    {
        $subject = "Order Confirmation - {$order['order_number']}";

        $body = $this->renderTemplate('order_confirmation', [
            'order' => $order,
            'customer' => $customer
        ]);

        return $this->send($customer['email'], $subject, $body);
    }

    /**
     * Send new order notification to restaurant
     *
     * @param array $order Order data
     * @param array $tenant Tenant data
     * @return bool
     */
    public function sendNewOrderNotification($order, $tenant)
    {
        $subject = "New Order Received - {$order['order_number']}";

        $body = $this->renderTemplate('new_order_notification', [
            'order' => $order,
            'tenant' => $tenant
        ]);

        return $this->send($tenant['email'], $subject, $body);
    }

    /**
     * Send order status update to customer
     *
     * @param array $order Order data
     * @param string $status New status
     * @return bool
     */
    public function sendOrderStatusUpdate($order, $status)
    {
        $subject = "Order Update - {$order['order_number']}";

        $body = $this->renderTemplate('order_status_update', [
            'order' => $order,
            'status' => $status,
            'status_message' => $this->getStatusMessage($status)
        ]);

        if (!empty($order['customer_email'])) {
            return $this->send($order['customer_email'], $subject, $body);
        }

        return false;
    }

    /**
     * Send invoice email
     *
     * @param array $invoice Invoice data
     * @param array $tenant Tenant data
     * @return bool
     */
    public function sendInvoice($invoice, $tenant)
    {
        $subject = "Invoice {$invoice['invoice_number']} - SplashOrder";

        $body = $this->renderTemplate('invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant
        ]);

        return $this->send($tenant['email'], $subject, $body);
    }

    /**
     * Send loyalty points notification
     *
     * @param array $customer Customer data
     * @param int $points Points earned
     * @param int $total_points Total points
     * @return bool
     */
    public function sendLoyaltyPointsNotification($customer, $points, $total_points)
    {
        if (empty($customer['email'])) {
            return false;
        }

        $subject = "You've earned {$points} loyalty points!";

        $body = $this->renderTemplate('loyalty_points', [
            'customer' => $customer,
            'points_earned' => $points,
            'total_points' => $total_points
        ]);

        return $this->send($customer['email'], $subject, $body);
    }

    /**
     * Send marketing campaign email
     *
     * @param string $email Recipient email
     * @param array $campaign Campaign data
     * @return bool
     */
    public function sendMarketingEmail($email, $campaign)
    {
        $subject = $campaign['subject'];
        $body = $campaign['body'];

        return $this->send($email, $subject, $body);
    }

    /**
     * Send email using PHPMailer or mail() function
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @return bool
     */
    private function send($to, $subject, $body)
    {
        // Log email for development/testing
        $this->logEmail($to, $subject, $body);

        // For production with PHPMailer
        if ($this->use_smtp && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body);
        }

        // Fallback to PHP mail() function
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            "From: {$this->from_name} <{$this->from_email}>",
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion()
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Send email using PHPMailer
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool
     */
    private function sendWithPHPMailer($to, $subject, $body)
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = 'tls';
            $mail->Port = $this->smtp_port;

            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Email send failed: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Render email template
     *
     * @param string $template Template name
     * @param array $data Template data
     * @return string
     */
    private function renderTemplate($template, $data)
    {
        extract($data);

        ob_start();
        $template_file = dirname(dirname(__DIR__)) . "/app/views/emails/{$template}.php";

        if (file_exists($template_file)) {
            require $template_file;
        } else {
            // Simple fallback template
            echo "<html><body>";
            echo "<h2>SplashOrder Notification</h2>";
            echo "<pre>" . print_r($data, true) . "</pre>";
            echo "</body></html>";
        }

        return ob_get_clean();
    }

    /**
     * Get status message
     *
     * @param string $status Order status
     * @return string
     */
    private function getStatusMessage($status)
    {
        $messages = [
            'new' => 'Your order has been received and is being processed.',
            'confirmed' => 'Your order has been confirmed by the restaurant.',
            'preparing' => 'Your order is being prepared in the kitchen.',
            'out_for_delivery' => 'Your order is out for delivery and will arrive soon!',
            'delivered' => 'Your order has been delivered. Enjoy your meal!',
            'canceled' => 'Your order has been canceled.'
        ];

        return $messages[$status] ?? 'Your order status has been updated.';
    }

    /**
     * Log email for development/testing
     *
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $body Body
     * @return void
     */
    private function logEmail($to, $subject, $body)
    {
        $log_dir = dirname(dirname(__DIR__)) . '/storage/logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/emails.log';
        $log_entry = date('Y-m-d H:i:s') . " | TO: {$to} | SUBJECT: {$subject}\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}
