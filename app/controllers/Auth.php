<?php
// FILE: /app/controllers/Auth.php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

/**
 * Auth Controller
 * Handles user authentication (login, logout, register)
 */
class Auth extends Controller
{
    /**
     * Login page
     */
    public function loginAction()
    {
        // If already logged in, redirect to dashboard
        if (\Auth::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        if (\Request::isPost()) {
            // Validate CSRF token
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request. Please try again.');
                $this->redirect('/login');
            }

            $email = \Request::post('email');
            $password = \Request::post('password');

            $validator = new \Validator(['email' => $email, 'password' => $password]);
            $validator->rules([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if (!$validator->validate()) {
                \Session::setFlash('error', 'Please provide valid email and password.');
                \Session::setFlash('errors', $validator->errors());
                setOldInput(\Request::post());
                $this->redirect('/login');
            }

            // Authenticate user
            $user_model = new User();
            $user = $user_model->authenticate($email, $password);

            if (!$user) {
                \Session::setFlash('error', 'Invalid email or password.');
                setOldInput(\Request::post());
                $this->redirect('/login');
            }

            // Login successful
            \Auth::login($user);

            // Redirect to intended page or dashboard
            $redirect_url = \Session::get('redirect_url', '/dashboard');
            \Session::remove('redirect_url');
            $this->redirect($redirect_url);
        }

        // Show login form
        $this->renderWithLayout('auth/login.php', [
            'title' => 'Login - SplashOrder',
            'csrf_token' => \Csrf::generate()
        ]);
    }

    /**
     * Register page
     */
    public function registerAction()
    {
        // If already logged in, redirect to dashboard
        if (\Auth::isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        if (\Request::isPost()) {
            // Validate CSRF token
            if (!$this->verifyCsrfToken()) {
                \Session::setFlash('error', 'Invalid request. Please try again.');
                $this->redirect('/register');
            }

            $data = \Request::post();

            $validator = new \Validator($data);
            $validator->rules([
                'name' => 'required|min:3',
                'email' => 'required|email',
                'password' => 'required|min:6',
                'password_confirm' => 'required|match:password'
            ]);

            if (!$validator->validate()) {
                \Session::setFlash('error', 'Please fix the errors below.');
                \Session::setFlash('errors', $validator->errors());
                setOldInput($data);
                $this->redirect('/register');
            }

            // Check if email already exists
            $user_model = new User();
            if ($user_model->findByEmail($data['email'])) {
                \Session::setFlash('error', 'Email already registered.');
                setOldInput($data);
                $this->redirect('/register');
            }

            // Create user
            $user_id = $user_model->createUser([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'tenant_admin', // New registrations are tenant admins
                'status' => 'active'
            ]);

            if ($user_id) {
                \Session::setFlash('success', 'Registration successful! Please login.');
                $this->redirect('/login');
            } else {
                \Session::setFlash('error', 'Registration failed. Please try again.');
                setOldInput($data);
                $this->redirect('/register');
            }
        }

        // Show register form
        $this->renderWithLayout('auth/register.php', [
            'title' => 'Register - SplashOrder',
            'csrf_token' => \Csrf::generate()
        ]);
    }

    /**
     * Logout
     */
    public function logoutAction()
    {
        \Auth::logout();
        \Session::setFlash('success', 'You have been logged out successfully.');
        $this->redirect('/login');
    }
}
