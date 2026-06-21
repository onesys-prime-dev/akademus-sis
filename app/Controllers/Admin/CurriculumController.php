<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CurriculumModel;
use App\Models\ProgramModel;

class CurriculumController extends BaseController
{
    protected CurriculumModel $curriculumModel;
    protected ProgramModel $programModel;

    public function __construct()
    {
        $this->curriculumModel = new CurriculumModel();
        $this->programModel = new ProgramModel();
    }

    public function index() {
        $curriculum = $this->curriculumModel
            // 
            ;
    }
}
