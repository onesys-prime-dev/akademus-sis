<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class FacultyController extends BaseController
{
    public function index()
    {
        return 'faculty dashboard';
    }
}
