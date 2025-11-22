<?php
// FILE: /app/controllers/Delivery.php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DeliveryService;
use App\Models\DeliveryDriver;
use App\Models\DeliveryAssignment;

/**
 * Delivery Controller
 * Handles delivery management
 */
class Delivery extends Controller
{
    private $delivery_service;
    private $driver_model;
    private $assignment_model;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->delivery_service = new DeliveryService();
        $this->driver_model = new DeliveryDriver();
        $this->assignment_model = new DeliveryAssignment();
    }

    /**
     * Delivery dashboard
     */
    public function index()
    {
        $tenant_id = \Auth::getTenantId();

        $drivers = $this->driver_model->findAll(['tenant_id' => $tenant_id]);

        // Get active assignments
        $sql = "SELECT da.*, o.order_number, dd.name as driver_name
                FROM delivery_assignments da
                JOIN orders o ON da.order_id = o.id
                JOIN delivery_drivers dd ON da.driver_id = dd.id
                WHERE da.tenant_id = :tenant_id
                AND da.status IN ('assigned', 'picked_up')
                ORDER BY da.assigned_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);
        $active_assignments = $stmt->fetchAll();

        $this->view('delivery/index', [
            'drivers' => $drivers,
            'active_assignments' => $active_assignments
        ]);
    }

    /**
     * Drivers list
     */
    public function drivers()
    {
        $tenant_id = \Auth::getTenantId();

        $drivers = $this->driver_model->findAll(['tenant_id' => $tenant_id]);

        $this->view('delivery/drivers', ['drivers' => $drivers]);
    }

    /**
     * Add driver
     */
    public function addDriver()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $data = [
                'tenant_id' => \Auth::getTenantId(),
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'vehicle_type' => $_POST['vehicle_type'],
                'vehicle_number' => $_POST['vehicle_number'],
                'license_number' => $_POST['license_number'],
                'status' => 'available',
                'is_active' => 1
            ];

            $id = $this->driver_model->insert($data);

            if ($id) {
                $this->flash('success', 'Driver added successfully');
                $this->redirect('/delivery/drivers');
            } else {
                $this->flash('error', 'Failed to add driver');
            }
        }

        $this->view('delivery/add_driver');
    }

    /**
     * Assign driver to order
     */
    public function assign()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $order_id = $_POST['order_id'];
            $driver_id = $_POST['driver_id'];
            $tenant_id = \Auth::getTenantId();

            $result = $this->delivery_service->assignDriver($order_id, $driver_id, $tenant_id);

            if ($result) {
                $this->flash('success', 'Driver assigned successfully');
            } else {
                $this->flash('error', 'Failed to assign driver');
            }

            $this->redirect('/delivery');
        }
    }

    /**
     * Auto-assign driver
     */
    public function autoAssign($order_id)
    {
        $tenant_id = \Auth::getTenantId();

        $result = $this->delivery_service->autoAssignDriver($order_id, $tenant_id);

        if ($result) {
            $this->flash('success', 'Driver auto-assigned successfully');
        } else {
            $this->flash('error', 'No available drivers found');
        }

        $this->redirect('/orders/view/' . $order_id);
    }

    /**
     * Mark as picked up
     */
    public function markPickedUp($assignment_id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $result = $this->delivery_service->markPickedUp($assignment_id);

            if ($result) {
                $this->flash('success', 'Order marked as picked up');
            } else {
                $this->flash('error', 'Failed to update status');
            }

            $this->redirect('/delivery');
        }
    }

    /**
     * Mark as delivered
     */
    public function markDelivered($assignment_id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();

            $notes = $_POST['notes'] ?? null;
            $result = $this->delivery_service->markDelivered($assignment_id, $notes);

            if ($result) {
                $this->flash('success', 'Order marked as delivered');
            } else {
                $this->flash('error', 'Failed to update status');
            }

            $this->redirect('/delivery');
        }
    }

    /**
     * Driver performance
     */
    public function driverStats($driver_id)
    {
        $driver = $this->driver_model->findById($driver_id);

        if (!$driver || $driver['tenant_id'] != \Auth::getTenantId()) {
            $this->flash('error', 'Driver not found');
            $this->redirect('/delivery/drivers');
            return;
        }

        $stats = $this->delivery_service->getDriverStats($driver_id, 30);

        $this->view('delivery/driver_stats', [
            'driver' => $driver,
            'stats' => $stats
        ]);
    }
}
