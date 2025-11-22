<?php
// FILE: /app/services/ReservationService.php

namespace App\Services;

/**
 * Reservation Service
 * Handles table reservations and waitlist
 */
class ReservationService
{
    private $db;
    private $sms_service;
    private $email_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->sms_service = new SmsService();
        $this->email_service = new EmailService();
    }

    /**
     * Create reservation
     *
     * @param array $data Reservation data
     * @return int|false Reservation ID
     */
    public function createReservation($data)
    {
        // Check availability
        if (!$this->isAvailable($data['branch_id'], $data['reservation_date'], $data['reservation_time'], $data['party_size'])) {
            return false;
        }

        // Generate confirmation code
        $confirmation_code = $this->generateConfirmationCode();

        $sql = "INSERT INTO reservations
                (tenant_id, branch_id, table_id, customer_id, customer_name, customer_phone,
                 customer_email, party_size, reservation_date, reservation_time, duration_minutes,
                 special_requests, confirmation_code, status)
                VALUES (:tenant_id, :branch_id, :table_id, :customer_id, :customer_name, :customer_phone,
                        :customer_email, :party_size, :reservation_date, :reservation_time, :duration_minutes,
                        :special_requests, :confirmation_code, 'confirmed')";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':branch_id' => $data['branch_id'],
            ':table_id' => $data['table_id'] ?? null,
            ':customer_id' => $data['customer_id'] ?? null,
            ':customer_name' => $data['customer_name'],
            ':customer_phone' => $data['customer_phone'],
            ':customer_email' => $data['customer_email'] ?? null,
            ':party_size' => $data['party_size'],
            ':reservation_date' => $data['reservation_date'],
            ':reservation_time' => $data['reservation_time'],
            ':duration_minutes' => $data['duration_minutes'] ?? 120,
            ':special_requests' => $data['special_requests'] ?? null,
            ':confirmation_code' => $confirmation_code
        ]);

        if (!$result) {
            return false;
        }

        $reservation_id = $this->db->lastInsertId();

        // Update table status if table assigned
        if (isset($data['table_id'])) {
            $this->updateTableStatus($data['table_id'], 'reserved');
        }

        // Send confirmation
        $this->sendConfirmation($reservation_id);

        return $reservation_id;
    }

    /**
     * Check if time slot is available
     *
     * @param int $branch_id Branch ID
     * @param string $date Date
     * @param string $time Time
     * @param int $party_size Party size
     * @return bool
     */
    public function isAvailable($branch_id, $date, $time, $party_size)
    {
        // Check if we have tables with enough capacity
        $sql = "SELECT COUNT(*) as available_tables
                FROM restaurant_tables
                WHERE branch_id = :branch_id
                AND capacity >= :party_size
                AND status = 'available'
                AND is_active = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':branch_id' => $branch_id,
            ':party_size' => $party_size
        ]);

        $result = $stmt->fetch();
        return $result && $result['available_tables'] > 0;
    }

    /**
     * Send confirmation
     *
     * @param int $reservation_id Reservation ID
     * @return bool
     */
    private function sendConfirmation($reservation_id)
    {
        $reservation = $this->getReservation($reservation_id);
        if (!$reservation) {
            return false;
        }

        $message = "Your reservation is confirmed!\n\n";
        $message .= "Date: {$reservation['reservation_date']}\n";
        $message .= "Time: {$reservation['reservation_time']}\n";
        $message .= "Party Size: {$reservation['party_size']}\n";
        $message .= "Confirmation Code: {$reservation['confirmation_code']}";

        // Send SMS
        if ($reservation['customer_phone']) {
            $this->sms_service->send($reservation['customer_phone'], $message);
        }

        // Send Email
        if ($reservation['customer_email']) {
            $this->email_service->send(
                $reservation['customer_email'],
                'Reservation Confirmed',
                $message
            );
        }

        return true;
    }

    /**
     * Add to waitlist
     *
     * @param array $data Waitlist data
     * @return int|false Waitlist ID
     */
    public function addToWaitlist($data)
    {
        // Get current position
        $position = $this->getNextWaitlistPosition($data['branch_id']);

        $sql = "INSERT INTO waitlist
                (tenant_id, branch_id, customer_name, customer_phone, party_size, position, estimated_wait_time)
                VALUES (:tenant_id, :branch_id, :customer_name, :customer_phone, :party_size, :position, :estimated_wait)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':branch_id' => $data['branch_id'],
            ':customer_name' => $data['customer_name'],
            ':customer_phone' => $data['customer_phone'],
            ':party_size' => $data['party_size'],
            ':position' => $position,
            ':estimated_wait' => $position * 15 // 15 minutes per party
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Notify waitlist party
     *
     * @param int $waitlist_id Waitlist ID
     * @return bool
     */
    public function notifyWaitlistParty($waitlist_id)
    {
        $sql = "UPDATE waitlist
                SET status = 'notified',
                    notified_at = NOW()
                WHERE id = :waitlist_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':waitlist_id' => $waitlist_id]);

        if ($result) {
            // Send notification
            $waitlist = $this->getWaitlistEntry($waitlist_id);
            if ($waitlist) {
                $this->sms_service->send(
                    $waitlist['customer_phone'],
                    "Your table is ready! Please proceed to the host stand."
                );
            }
        }

        return $result;
    }

    /**
     * Cancel reservation
     *
     * @param int $reservation_id Reservation ID
     * @return bool
     */
    public function cancelReservation($reservation_id)
    {
        $reservation = $this->getReservation($reservation_id);

        $sql = "UPDATE reservations
                SET status = 'cancelled'
                WHERE id = :reservation_id";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':reservation_id' => $reservation_id]);

        // Free up table
        if ($result && $reservation && $reservation['table_id']) {
            $this->updateTableStatus($reservation['table_id'], 'available');
        }

        return $result;
    }

    /**
     * Get reservation by ID
     *
     * @param int $reservation_id Reservation ID
     * @return array|null
     */
    private function getReservation($reservation_id)
    {
        $sql = "SELECT * FROM reservations WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $reservation_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get waitlist entry
     *
     * @param int $waitlist_id Waitlist ID
     * @return array|null
     */
    private function getWaitlistEntry($waitlist_id)
    {
        $sql = "SELECT * FROM waitlist WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $waitlist_id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Update table status
     *
     * @param int $table_id Table ID
     * @param string $status Status
     * @return bool
     */
    private function updateTableStatus($table_id, $status)
    {
        $sql = "UPDATE restaurant_tables
                SET status = :status
                WHERE id = :table_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':table_id' => $table_id,
            ':status' => $status
        ]);
    }

    /**
     * Generate confirmation code
     *
     * @return string
     */
    private function generateConfirmationCode()
    {
        return strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    /**
     * Get next waitlist position
     *
     * @param int $branch_id Branch ID
     * @return int
     */
    private function getNextWaitlistPosition($branch_id)
    {
        $sql = "SELECT COALESCE(MAX(position), 0) + 1 as next_position
                FROM waitlist
                WHERE branch_id = :branch_id
                AND status = 'waiting'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':branch_id' => $branch_id]);
        $result = $stmt->fetch();

        return $result ? (int)$result['next_position'] : 1;
    }
}
