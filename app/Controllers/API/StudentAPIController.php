<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\CodeIgniter;

class StudentAPIController extends BaseController
{
    use ResponseTrait;

    public function promote()
    {
        $key = $this->request->getHeaderLine('X-API-KEY');

        if ($key !== env('API_KEY')) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
        }

        return $this->respond([
            'success' => true,
            'message' => 'SIS API Working'
        ]);
    }
}
