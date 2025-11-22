<?php
// FILE: /app/controllers/Reviews.php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ReviewService;

/**
 * Reviews Controller
 * Handles customer reviews management
 */
class Reviews extends Controller
{
    private $review_service;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->review_service = new ReviewService();
    }

    /**
     * Reviews list
     */
    public function index()
    {
        $tenant_id = \Auth::getTenantId();
        $status = $_GET['status'] ?? null;

        $reviews = $this->review_service->getReviews($tenant_id, $status);
        $stats = $this->review_service->getReviewStats($tenant_id);

        $this->view('reviews/index', [
            'reviews' => $reviews,
            'stats' => $stats,
            'status' => $status
        ]);
    }

    /**
     * View review details
     */
    public function view($id)
    {
        $review = $this->review_model->findById($id);

        if (!$review || $review['tenant_id'] != \Auth::getTenantId()) {
            $this->flash('error', 'Review not found');
            $this->redirect('/reviews');
            return;
        }

        $this->view('reviews/view', ['review' => $review]);
    }

    /**
     * Approve review
     */
    public function approve($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $result = $this->review_service->approveReview($id);

            if ($result) {
                $this->flash('success', 'Review approved successfully');
            } else {
                $this->flash('error', 'Failed to approve review');
            }

            $this->redirect('/reviews');
        }
    }

    /**
     * Reject review
     */
    public function reject($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $reason = $_POST['reason'] ?? null;
            $result = $this->review_service->rejectReview($id, $reason);

            if ($result) {
                $this->flash('success', 'Review rejected');
            } else {
                $this->flash('error', 'Failed to reject review');
            }

            $this->redirect('/reviews');
        }
    }

    /**
     * Add reply to review
     */
    public function reply($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $reply = $_POST['reply'];
            $user_id = \Auth::getUserId();

            $result = $this->review_service->addReply($id, $reply, $user_id);

            if ($result) {
                $this->flash('success', 'Reply added successfully');
            } else {
                $this->flash('error', 'Failed to add reply');
            }

            $this->redirect('/reviews/view/' . $id);
        }
    }

    /**
     * Delete review
     */
    public function delete($id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $result = $this->review_service->deleteReview($id);

            if ($result) {
                $this->flash('success', 'Review deleted successfully');
            } else {
                $this->flash('error', 'Failed to delete review');
            }

            $this->redirect('/reviews');
        }
    }
}
