<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class StudentController extends BaseController
{
    public function index(): string
    {
        $user = auth()->user();

        return view('pages/dashboard', [
            'pageTitle' => 'Dashboard',
            'activePage' => 'dashboard',
            'user' => $user,
        ]);
    }

    public function profile(): string
    {
        $user = auth()->user();
        
        return view('pages/profile', [
            'pageTitle' => 'Profile',
            'activePage' => 'profile',
            'user' => $user,
        ]);
    }
}

