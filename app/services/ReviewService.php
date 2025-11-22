<?php
// FILE: /app/services/ReviewService.php

namespace App\Services;

use App\Models\Review;

/**
 * Review Service
 * Handles customer reviews and ratings
 */
class ReviewService
{
    private $review_model;
    private $db;

    public function __construct()
    {
        $this->review_model = new Review();
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Create review
     *
     * @param array $data Review data
     * @return int|false Review ID
     */
    public function createReview($data)
    {
        // Validate customer can review this order
        if (!$this->canReview($data['customer_id'], $data['order_id'])) {
            return false;
        }

        // Check if already reviewed
        if ($this->hasReviewed($data['customer_id'], $data['order_id'])) {
            return false;
        }

        // Create review
        $review_id = $this->review_model->insert([
            'tenant_id' => $data['tenant_id'],
            'customer_id' => $data['customer_id'],
            'order_id' => $data['order_id'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'status' => 'pending'
        ]);

        if ($review_id) {
            // Update order average rating
            $this->updateTenantRating($data['tenant_id']);

            // Award loyalty points for review
            if (isset($data['award_points']) && $data['award_points']) {
                $loyalty_service = new LoyaltyService();
                $loyalty_service->awardPoints(
                    $data['customer_id'],
                    $data['tenant_id'],
                    5, // 5 points for review
                    null
                );
            }
        }

        return $review_id;
    }

    /**
     * Update review
     *
     * @param int $review_id Review ID
     * @param array $data Update data
     * @return bool
     */
    public function updateReview($review_id, $data)
    {
        $allowed_fields = ['rating', 'comment'];
        $update_data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($update_data)) {
            return false;
        }

        $result = $this->review_model->update($review_id, $update_data);

        if ($result) {
            $review = $this->review_model->findById($review_id);
            $this->updateTenantRating($review['tenant_id']);
        }

        return $result;
    }

    /**
     * Approve review
     *
     * @param int $review_id Review ID
     * @return bool
     */
    public function approveReview($review_id)
    {
        $result = $this->review_model->update($review_id, [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s')
        ]);

        if ($result) {
            $review = $this->review_model->findById($review_id);
            $this->updateTenantRating($review['tenant_id']);
        }

        return $result;
    }

    /**
     * Reject review
     *
     * @param int $review_id Review ID
     * @param string|null $reason Rejection reason
     * @return bool
     */
    public function rejectReview($review_id, $reason = null)
    {
        return $this->review_model->update($review_id, [
            'status' => 'rejected',
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Delete review
     *
     * @param int $review_id Review ID
     * @return bool
     */
    public function deleteReview($review_id)
    {
        $review = $this->review_model->findById($review_id);
        $result = $this->review_model->delete($review_id);

        if ($result && $review) {
            $this->updateTenantRating($review['tenant_id']);
        }

        return $result;
    }

    /**
     * Add reply to review
     *
     * @param int $review_id Review ID
     * @param string $reply Reply text
     * @param int $user_id User ID
     * @return bool
     */
    public function addReply($review_id, $reply, $user_id)
    {
        return $this->review_model->update($review_id, [
            'reply' => $reply,
            'reply_by' => $user_id,
            'replied_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get reviews for tenant
     *
     * @param int $tenant_id Tenant ID
     * @param string|null $status Status filter
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function getReviews($tenant_id, $status = null, $limit = 20, $offset = 0)
    {
        $sql = "SELECT r.*, c.name as customer_name, o.order_number
                FROM reviews r
                JOIN customers c ON r.customer_id = c.id
                JOIN orders o ON r.order_id = o.id
                WHERE r.tenant_id = :tenant_id";

        $params = [':tenant_id' => $tenant_id];

        if ($status) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY r.created_at DESC
                 LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get review statistics
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getReviewStats($tenant_id)
    {
        $sql = "SELECT
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reviews
                FROM reviews
                WHERE tenant_id = :tenant_id
                AND status = 'approved'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetch();
    }

    /**
     * Get top rated items
     *
     * @param int $tenant_id Tenant ID
     * @param int $limit Limit
     * @return array
     */
    public function getTopRatedItems($tenant_id, $limit = 10)
    {
        $sql = "SELECT
                oi.menu_item_id,
                oi.item_name,
                AVG(r.rating) as avg_rating,
                COUNT(r.id) as review_count
                FROM reviews r
                JOIN orders o ON r.order_id = o.id
                JOIN order_items oi ON o.id = oi.order_id
                WHERE r.tenant_id = :tenant_id
                AND r.status = 'approved'
                GROUP BY oi.menu_item_id, oi.item_name
                HAVING review_count >= 3
                ORDER BY avg_rating DESC, review_count DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get customer reviews
     *
     * @param int $customer_id Customer ID
     * @param int $limit Limit
     * @return array
     */
    public function getCustomerReviews($customer_id, $limit = 20)
    {
        $sql = "SELECT r.*, o.order_number
                FROM reviews r
                JOIN orders o ON r.order_id = o.id
                WHERE r.customer_id = :customer_id
                ORDER BY r.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':customer_id', $customer_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Check if customer can review order
     *
     * @param int $customer_id Customer ID
     * @param int $order_id Order ID
     * @return bool
     */
    private function canReview($customer_id, $order_id)
    {
        // Check if order exists and belongs to customer
        $sql = "SELECT id, status FROM orders
                WHERE id = :order_id
                AND customer_id = :customer_id
                AND status IN ('delivered', 'completed')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $order_id,
            ':customer_id' => $customer_id
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Check if customer has already reviewed order
     *
     * @param int $customer_id Customer ID
     * @param int $order_id Order ID
     * @return bool
     */
    private function hasReviewed($customer_id, $order_id)
    {
        $sql = "SELECT id FROM reviews
                WHERE customer_id = :customer_id
                AND order_id = :order_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customer_id,
            ':order_id' => $order_id
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Update tenant average rating
     *
     * @param int $tenant_id Tenant ID
     * @return bool
     */
    private function updateTenantRating($tenant_id)
    {
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
                FROM reviews
                WHERE tenant_id = :tenant_id
                AND status = 'approved'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $result = $stmt->fetch();

        if ($result) {
            $sql = "UPDATE tenants
                    SET average_rating = :avg_rating,
                        total_reviews = :review_count
                    WHERE id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':tenant_id' => $tenant_id,
                ':avg_rating' => round($result['avg_rating'], 2),
                ':review_count' => $result['review_count']
            ]);
        }

        return false;
    }

    /**
     * Get recent reviews for public display
     *
     * @param int $tenant_id Tenant ID
     * @param int $limit Limit
     * @return array
     */
    public function getPublicReviews($tenant_id, $limit = 10)
    {
        $sql = "SELECT r.rating, r.comment, r.created_at, c.name as customer_name
                FROM reviews r
                JOIN customers c ON r.customer_id = c.id
                WHERE r.tenant_id = :tenant_id
                AND r.status = 'approved'
                AND r.rating >= 4
                ORDER BY r.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenant_id);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
