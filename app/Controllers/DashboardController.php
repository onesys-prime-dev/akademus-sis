<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class DashboardController extends BaseController
{
    public function index()
    {
        if (! auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $user = auth()->user();

        if ($user->inGroup('superadmin')) {
            return redirect()->to('/admin/dashboard');
        }
        
        if ($user->inGroup('admin')) {
            return redirect()->to('/admin/dashboard');
        }

        if ($user->inGroup('registrar')) {
            return redirect()->to('/registrar/dashboard');
        }

        if ($user->inGroup('faculty')) {
            return redirect()->to('/faculty/dashboard');
        }

        if ($user->inGroup('student')) {
            return redirect()->to('/student/dashboard');
        }

        return redirect()->to('/');
    }
}
