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

        $data = $this->request->getJSON(true);

        $required = [
            'applicant_id',
            'first_name',
            'last_name',
            'email'
        ];

        foreach ($required as $field) {

            if (! isset($data[$field]) || trim($data[$field]) === '') {

                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        'success' => false,
                        'message' => "{$field} is required"
                    ]);
            }
        }

        return $this->respond([
            'success' => true,
            'received' => $data
        ]);
    }
}
