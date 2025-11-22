<?php
// FILE: /app/controllers/Coupons.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Coupon;

class Coupons extends Controller
{
    protected function before()
    {
        $this->requireAuth();
        $this->requireRole(['tenant_admin']);
    }

    public function indexAction()
    {
        $tenant_id = $this->getTenantId();
        $coupon_model = new Coupon();
        $coupon_model->setTenantId($tenant_id);
        $coupons = $coupon_model->findAll([], 'created_at DESC');

        $this->renderWithLayout('coupons/index.php', [
            'title' => 'Coupons - SplashOrder',
            'coupons' => $coupons
        ]);
    }

    public function createAction()
    {
        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/coupons/create');
            }

            $data = \Request::post();
            $data['tenant_id'] = $this->getTenantId();
            $data['code'] = strtoupper($data['code']);

            $coupon_model = new Coupon();
            $coupon_model->setTenantId($this->getTenantId());

            if ($coupon_model->insert($data)) {
                \Session::setFlash('success', 'Coupon created successfully.');
                $this->redirect('/coupons');
            } else {
                \Session::setFlash('error', 'Failed to create coupon.');
                setOldInput($data);
            }
        }

        $this->renderWithLayout('coupons/create.php', [
            'title' => 'Create Coupon - SplashOrder',
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function editAction()
    {
        $tenant_id = $this->getTenantId();
        $coupon_id = $this->route_params['id'] ?? null;

        $coupon_model = new Coupon();
        $coupon_model->setTenantId($tenant_id);
        $coupon = $coupon_model->findById($coupon_id);

        if (!$coupon) {
            \Session::setFlash('error', 'Coupon not found.');
            $this->redirect('/coupons');
        }

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/coupons/edit/' . $coupon_id);
            }

            $data = \Request::post();

            if ($coupon_model->update($coupon_id, $data)) {
                \Session::setFlash('success', 'Coupon updated successfully.');
                $this->redirect('/coupons');
            } else {
                \Session::setFlash('error', 'Failed to update coupon.');
            }
        }

        $this->renderWithLayout('coupons/edit.php', [
            'title' => 'Edit Coupon - SplashOrder',
            'coupon' => $coupon,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function deleteAction()
    {
        if (!\Request::isPost() || !$this->verifyCsrfToken()) {
            $this->redirect('/coupons');
        }

        $tenant_id = $this->getTenantId();
        $coupon_id = $this->route_params['id'] ?? null;

        $coupon_model = new Coupon();
        $coupon_model->setTenantId($tenant_id);

        if ($coupon_model->delete($coupon_id)) {
            \Session::setFlash('success', 'Coupon deleted successfully.');
        } else {
            \Session::setFlash('error', 'Failed to delete coupon.');
        }

        $this->redirect('/coupons');
    }
}
