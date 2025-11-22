<?php
// FILE: /app/controllers/Categories.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;

class Categories extends Controller
{
    protected function before()
    {
        $this->requireAuth();
        $this->requireRole(['tenant_admin', 'staff']);
    }

    public function indexAction()
    {
        $tenant_id = $this->getTenantId();
        $category_model = new Category();
        $categories = $category_model->getCategoriesWithCount($tenant_id);

        $this->renderWithLayout('categories/index.php', [
            'title' => 'Categories - SplashOrder',
            'categories' => $categories
        ]);
    }

    public function createAction()
    {
        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/categories/create');
            }

            $data = \Request::post();
            $data['tenant_id'] = $this->getTenantId();
            $data['slug'] = slugify($data['name']);

            $category_model = new Category();
            $category_model->setTenantId($this->getTenantId());

            if ($category_model->insert($data)) {
                \Session::setFlash('success', 'Category created successfully.');
                $this->redirect('/categories');
            } else {
                \Session::setFlash('error', 'Failed to create category.');
                setOldInput($data);
            }
        }

        $this->renderWithLayout('categories/create.php', [
            'title' => 'Create Category - SplashOrder',
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function editAction()
    {
        $tenant_id = $this->getTenantId();
        $category_id = $this->route_params['id'] ?? null;

        $category_model = new Category();
        $category_model->setTenantId($tenant_id);
        $category = $category_model->findById($category_id);

        if (!$category) {
            \Session::setFlash('error', 'Category not found.');
            $this->redirect('/categories');
        }

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/categories/edit/' . $category_id);
            }

            $data = \Request::post();

            if ($category_model->update($category_id, $data)) {
                \Session::setFlash('success', 'Category updated successfully.');
                $this->redirect('/categories');
            } else {
                \Session::setFlash('error', 'Failed to update category.');
            }
        }

        $this->renderWithLayout('categories/edit.php', [
            'title' => 'Edit Category - SplashOrder',
            'category' => $category,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function deleteAction()
    {
        if (!\Request::isPost() || !$this->verifyCsrfToken()) {
            $this->redirect('/categories');
        }

        $tenant_id = $this->getTenantId();
        $category_id = $this->route_params['id'] ?? null;

        $category_model = new Category();
        $category_model->setTenantId($tenant_id);

        if ($category_model->delete($category_id)) {
            \Session::setFlash('success', 'Category deleted successfully.');
        } else {
            \Session::setFlash('error', 'Failed to delete category.');
        }

        $this->redirect('/categories');
    }
}
