<?php
// FILE: /app/controllers/Branches.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Branch;
use App\Models\Tenant;

/**
 * Branches Controller
 * Manage restaurant branches
 */
class Branches extends Controller
{
    protected function before()
    {
        $this->requireAuth();
        $this->requireRole(['tenant_admin', 'staff']);
    }

    public function indexAction()
    {
        $tenant_id = $this->getTenantId();
        $branch_model = new Branch();
        $branch_model->setTenantId($tenant_id);
        $branches = $branch_model->findAll();

        $this->renderWithLayout('branches/index.php', [
            'title' => 'Branches - SplashOrder',
            'branches' => $branches
        ]);
    }

    public function createAction()
    {
        $tenant_id = $this->getTenantId();

        // Check quota
        $tenant_model = new Tenant();
        if (!$tenant_model->canAddBranch($tenant_id)) {
            \Session::setFlash('error', 'You have reached your branch limit. Please upgrade your subscription.');
            $this->redirect('/branches');
        }

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/branches/create');
            }

            $data = \Request::post();
            $data['tenant_id'] = $tenant_id;
            $data['slug'] = slugify($data['name']);

            $branch_model = new Branch();
            $branch_model->setTenantId($tenant_id);

            if ($branch_model->insert($data)) {
                \Session::setFlash('success', 'Branch created successfully.');
                $this->redirect('/branches');
            } else {
                \Session::setFlash('error', 'Failed to create branch.');
                setOldInput($data);
            }
        }

        $this->renderWithLayout('branches/create.php', [
            'title' => 'Create Branch - SplashOrder',
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function editAction()
    {
        $tenant_id = $this->getTenantId();
        $branch_id = $this->route_params['id'] ?? null;

        $branch_model = new Branch();
        $branch_model->setTenantId($tenant_id);
        $branch = $branch_model->findById($branch_id);

        if (!$branch) {
            \Session::setFlash('error', 'Branch not found.');
            $this->redirect('/branches');
        }

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/branches/edit/' . $branch_id);
            }

            $data = \Request::post();

            if ($branch_model->update($branch_id, $data)) {
                \Session::setFlash('success', 'Branch updated successfully.');
                $this->redirect('/branches');
            } else {
                \Session::setFlash('error', 'Failed to update branch.');
                setOldInput($data);
            }
        }

        $this->renderWithLayout('branches/edit.php', [
            'title' => 'Edit Branch - SplashOrder',
            'branch' => $branch,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function deleteAction()
    {
        $tenant_id = $this->getTenantId();
        $branch_id = $this->route_params['id'] ?? null;

        if (!\Request::isPost()) {
            $this->redirect('/branches');
        }

        if (!$this->verifyCsrfToken()) {
            \Session::setFlash('error', 'Invalid request.');
            $this->redirect('/branches');
        }

        $branch_model = new Branch();
        $branch_model->setTenantId($tenant_id);

        if ($branch_model->delete($branch_id)) {
            \Session::setFlash('success', 'Branch deleted successfully.');
        } else {
            \Session::setFlash('error', 'Failed to delete branch.');
        }

        $this->redirect('/branches');
    }
}
