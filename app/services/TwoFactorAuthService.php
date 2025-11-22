<?php
// FILE: /app/services/TwoFactorAuthService.php

namespace App\Services;

/**
 * Two-Factor Authentication Service
 * Handles 2FA setup and verification
 */
class TwoFactorAuthService
{
    private $db;
    private $sms_service;

    // OTP configuration
    const OTP_LENGTH = 6;
    const OTP_EXPIRY_MINUTES = 10;
    const MAX_ATTEMPTS = 3;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->sms_service = new SmsService();
    }

    /**
     * Enable 2FA for user
     *
     * @param int $user_id User ID
     * @param string $method Method (sms, email, app)
     * @param string $value Phone or email
     * @return bool
     */
    public function enable2FA($user_id, $method, $value)
    {
        // Check if 2FA already enabled
        if ($this->is2FAEnabled($user_id)) {
            return false;
        }

        // Generate secret for app-based 2FA
        $secret = null;
        if ($method === 'app') {
            $secret = $this->generateSecret();
        }

        $sql = "INSERT INTO two_factor_auth
                (user_id, method, phone, email, secret, is_enabled, enabled_at)
                VALUES (:user_id, :method, :phone, :email, :secret, 1, NOW())";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':method' => $method,
            ':phone' => ($method === 'sms') ? $value : null,
            ':email' => ($method === 'email') ? $value : null,
            ':secret' => $secret
        ]);
    }

    /**
     * Disable 2FA for user
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function disable2FA($user_id)
    {
        $sql = "UPDATE two_factor_auth
                SET is_enabled = 0
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $user_id]);
    }

    /**
     * Check if 2FA is enabled for user
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function is2FAEnabled($user_id)
    {
        $sql = "SELECT is_enabled FROM two_factor_auth
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();

        return $result && $result['is_enabled'] == 1;
    }

    /**
     * Send OTP code
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function sendOTP($user_id)
    {
        // Get 2FA settings
        $settings = $this->get2FASettings($user_id);
        if (!$settings || !$settings['is_enabled']) {
            return false;
        }

        // Generate OTP
        $otp = $this->generateOTP();
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . self::OTP_EXPIRY_MINUTES . ' minutes'));

        // Save OTP
        $sql = "INSERT INTO two_factor_otp
                (user_id, code, expires_at, attempts)
                VALUES (:user_id, :code, :expires_at, 0)
                ON DUPLICATE KEY UPDATE
                    code = :code2,
                    expires_at = :expires_at2,
                    attempts = 0,
                    verified = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':code' => password_hash($otp, PASSWORD_DEFAULT),
            ':code2' => password_hash($otp, PASSWORD_DEFAULT),
            ':expires_at' => $expires_at,
            ':expires_at2' => $expires_at
        ]);

        // Send OTP based on method
        if ($settings['method'] === 'sms' && $settings['phone']) {
            return $this->sms_service->sendOTP($settings['phone'], $otp);
        } elseif ($settings['method'] === 'email' && $settings['email']) {
            $email_service = new EmailService();
            return $email_service->send(
                $settings['email'],
                'Your Verification Code',
                "Your verification code is: <strong>$otp</strong><br><br>This code will expire in " . self::OTP_EXPIRY_MINUTES . " minutes."
            );
        }

        return false;
    }

    /**
     * Verify OTP code
     *
     * @param int $user_id User ID
     * @param string $code Code to verify
     * @return bool
     */
    public function verifyOTP($user_id, $code)
    {
        // Get OTP record
        $sql = "SELECT * FROM two_factor_otp
                WHERE user_id = :user_id
                AND verified = 0
                ORDER BY created_at DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $otp_record = $stmt->fetch();

        if (!$otp_record) {
            return false;
        }

        // Check if expired
        if (strtotime($otp_record['expires_at']) < time()) {
            return false;
        }

        // Check attempts
        if ($otp_record['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        // Verify code
        if (!password_verify($code, $otp_record['code'])) {
            // Increment attempts
            $this->incrementOTPAttempts($user_id);
            return false;
        }

        // Mark as verified
        $sql = "UPDATE two_factor_otp
                SET verified = 1,
                    verified_at = NOW()
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        // Update last verified
        $sql = "UPDATE two_factor_auth
                SET last_verified_at = NOW()
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return true;
    }

    /**
     * Verify app-based 2FA code
     *
     * @param int $user_id User ID
     * @param string $code 6-digit code
     * @return bool
     */
    public function verifyAppCode($user_id, $code)
    {
        $settings = $this->get2FASettings($user_id);
        if (!$settings || $settings['method'] !== 'app') {
            return false;
        }

        // Simple TOTP implementation (in production, use Google Authenticator library)
        // For now, just validate format
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // TODO: Implement proper TOTP verification using secret
        // For demo purposes, accept any 6-digit code
        $valid = strlen($code) === 6 && ctype_digit($code);

        if ($valid) {
            $sql = "UPDATE two_factor_auth
                    SET last_verified_at = NOW()
                    WHERE user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $user_id]);
        }

        return $valid;
    }

    /**
     * Get backup codes
     *
     * @param int $user_id User ID
     * @return array
     */
    public function getBackupCodes($user_id)
    {
        $sql = "SELECT backup_codes FROM two_factor_auth
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch();

        if (!$result || !$result['backup_codes']) {
            return [];
        }

        return json_decode($result['backup_codes'], true) ?: [];
    }

    /**
     * Generate backup codes
     *
     * @param int $user_id User ID
     * @param int $count Number of codes
     * @return array
     */
    public function generateBackupCodes($user_id, $count = 10)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateBackupCode();
        }

        // Hash codes before storing
        $hashed_codes = array_map(function($code) {
            return password_hash($code, PASSWORD_DEFAULT);
        }, $codes);

        $sql = "UPDATE two_factor_auth
                SET backup_codes = :codes
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':codes' => json_encode($hashed_codes)
        ]);

        return $codes;
    }

    /**
     * Verify backup code
     *
     * @param int $user_id User ID
     * @param string $code Backup code
     * @return bool
     */
    public function verifyBackupCode($user_id, $code)
    {
        $hashed_codes = $this->getBackupCodes($user_id);
        if (empty($hashed_codes)) {
            return false;
        }

        foreach ($hashed_codes as $index => $hashed_code) {
            if (password_verify($code, $hashed_code)) {
                // Remove used code
                unset($hashed_codes[$index]);
                $hashed_codes = array_values($hashed_codes);

                $sql = "UPDATE two_factor_auth
                        SET backup_codes = :codes,
                            last_verified_at = NOW()
                        WHERE user_id = :user_id";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':codes' => json_encode($hashed_codes)
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Get 2FA QR code for app setup
     *
     * @param int $user_id User ID
     * @param string $app_name App name
     * @return string|null QR code URL
     */
    public function getQRCode($user_id, $app_name = 'SplashOrder')
    {
        $settings = $this->get2FASettings($user_id);
        if (!$settings || !$settings['secret']) {
            return null;
        }

        // Get user email
        $user = $this->getUser($user_id);
        if (!$user) {
            return null;
        }

        // Generate TOTP URL
        $url = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($app_name),
            urlencode($user['email']),
            $settings['secret'],
            urlencode($app_name)
        );

        // In production, generate actual QR code image
        // For now, return the URL
        return $url;
    }

    /**
     * Get 2FA settings for user
     *
     * @param int $user_id User ID
     * @return array|null
     */
    private function get2FASettings($user_id)
    {
        $sql = "SELECT * FROM two_factor_auth
                WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Generate OTP code
     *
     * @return string
     */
    private function generateOTP()
    {
        return str_pad(random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Generate secret for app-based 2FA
     *
     * @return string
     */
    private function generateSecret()
    {
        // Base32 encoding for TOTP
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }

    /**
     * Generate backup code
     *
     * @return string
     */
    private function generateBackupCode()
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Increment OTP attempts
     *
     * @param int $user_id User ID
     * @return bool
     */
    private function incrementOTPAttempts($user_id)
    {
        $sql = "UPDATE two_factor_otp
                SET attempts = attempts + 1
                WHERE user_id = :user_id
                AND verified = 0";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':user_id' => $user_id]);
    }

    /**
     * Get user
     *
     * @param int $user_id User ID
     * @return array|null
     */
    private function getUser($user_id)
    {
        $sql = "SELECT * FROM users WHERE id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetch() ?: null;
    }
}
