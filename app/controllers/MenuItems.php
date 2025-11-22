<?php
// FILE: /app/controllers/MenuItems.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\MenuItem;
use App\Models\Category;
use App\Models\Tenant;

class MenuItems extends Controller
{
    protected function before()
    {
        $this->requireAuth();
        $this->requireRole(['tenant_admin', 'staff']);
    }

    public function indexAction()
    {
        $tenant_id = $this->getTenantId();
        $menu_item_model = new MenuItem();
        $menu_item_model->setTenantId($tenant_id);

        $page = max(1, (int)\Request::query('page', 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $items = $menu_item_model->findAll([], 'created_at DESC', $per_page, $offset);
        $total = $menu_item_model->count();
        $total_pages = ceil($total / $per_page);

        $this->renderWithLayout('menu_items/index.php', [
            'title' => 'Menu Items - SplashOrder',
            'items' => $items,
            'current_page' => $page,
            'total_pages' => $total_pages
        ]);
    }

    public function createAction()
    {
        $tenant_id = $this->getTenantId();

        // Check quota
        $tenant_model = new Tenant();
        if (!$tenant_model->canAddMenuItem($tenant_id)) {
            \Session::setFlash('error', 'You have reached your menu item limit. Please upgrade your subscription.');
            $this->redirect('/menu-items');
        }

        $category_model = new Category();
        $category_model->setTenantId($tenant_id);
        $categories = $category_model->getActiveCategories($tenant_id);

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/menu-items/create');
            }

            $data = \Request::post();
            $data['tenant_id'] = $tenant_id;
            $data['slug'] = slugify($data['name']);

            // Handle image upload
            if (\Request::hasFile('image')) {
                $upload = new \Upload(\Request::file('image'));
                $filename = $upload->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])->maxSize(5242880)->upload();
                if ($filename) {
                    $data['image'] = $filename;
                }
            }

            $menu_item_model = new MenuItem();
            $menu_item_model->setTenantId($tenant_id);

            if ($menu_item_model->insert($data)) {
                \Session::setFlash('success', 'Menu item created successfully.');
                $this->redirect('/menu-items');
            } else {
                \Session::setFlash('error', 'Failed to create menu item.');
                setOldInput($data);
            }
        }

        $this->renderWithLayout('menu_items/create.php', [
            'title' => 'Create Menu Item - SplashOrder',
            'categories' => $categories,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function editAction()
    {
        $tenant_id = $this->getTenantId();
        $item_id = $this->route_params['id'] ?? null;

        $menu_item_model = new MenuItem();
        $menu_item_model->setTenantId($tenant_id);
        $item = $menu_item_model->findById($item_id);

        if (!$item) {
            \Session::setFlash('error', 'Menu item not found.');
            $this->redirect('/menu-items');
        }

        $category_model = new Category();
        $category_model->setTenantId($tenant_id);
        $categories = $category_model->getActiveCategories($tenant_id);

        if (\Request::isPost()) {
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request.');
                $this->redirect('/menu-items/edit/' . $item_id);
            }

            $data = \Request::post();

            // Handle image upload
            if (\Request::hasFile('image')) {
                $upload = new \Upload(\Request::file('image'));
                $filename = $upload->allowedExtensions(['jpg', 'jpeg', 'png', 'webp'])->maxSize(5242880)->upload();
                if ($filename) {
                    $data['image'] = $filename;
                    // Delete old image if exists
                    if ($item['image']) {
                        \Upload::delete($item['image']);
                    }
                }
            }

            if ($menu_item_model->update($item_id, $data)) {
                \Session::setFlash('success', 'Menu item updated successfully.');
                $this->redirect('/menu-items');
            } else {
                \Session::setFlash('error', 'Failed to update menu item.');
            }
        }

        $this->renderWithLayout('menu_items/edit.php', [
            'title' => 'Edit Menu Item - SplashOrder',
            'item' => $item,
            'categories' => $categories,
            'csrf_token' => \Csrf::generate()
        ]);
    }

    public function deleteAction()
    {
        if (!\Request::isPost() || !$this->verifyCsrfToken()) {
            $this->redirect('/menu-items');
        }

        $tenant_id = $this->getTenantId();
        $item_id = $this->route_params['id'] ?? null;

        $menu_item_model = new MenuItem();
        $menu_item_model->setTenantId($tenant_id);

        if ($menu_item_model->delete($item_id)) {
            \Session::setFlash('success', 'Menu item deleted successfully.');
        } else {
            \Session::setFlash('error', 'Failed to delete menu item.');
        }

        $this->redirect('/menu-items');
    }
}
