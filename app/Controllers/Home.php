<?php

namespace App\Controllers;

class Home extends BaseController
{
    /**
     * The site root has no content of its own — send visitors to login.
     * Shield's LoginController already redirects an already-authenticated
     * user straight to their role's dashboard, so this is never a dead end.
     */
    public function index()
    {
        return redirect()->to('login');
    }
}
