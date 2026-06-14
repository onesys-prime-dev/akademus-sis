<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;

class Ping extends BaseController
{
    use ResponseTrait;
    public function getIndex(): ResponseInterface
    {
        return $this->respond(['status' => 'ok'], 200);
    }
}
