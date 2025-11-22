<?php
// FILE: /app/controllers/Home.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Tenant;

/**
 * Home Controller
 * Public homepage
 */
class Home extends Controller
{
    /**
     * Homepage
     */
    public function indexAction()
    {
        $tenant_model = new Tenant();
        $tenants = $tenant_model->getActiveTenants();

        $this->renderWithLayout('home/index.php', [
            'title' => 'Welcome to SplashOrder',
            'tenants' => $tenants
        ]);
    }
}
