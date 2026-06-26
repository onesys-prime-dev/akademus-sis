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

    public function index()
    {
        $curriculum = $this->curriculumModel
            ->select('curriculum.*, programs.program_name')
            ->join('programs', 'programs.program_id = curriculum.program_id')
            ->findAll();

        return view('admin/curriculum/index', [
            'curriculum' => $curriculum
        ]);
    }

    public function create()
    {
        $programs = $this->programModel
            ->where('is_active', 1)
            ->findAll();

        return view('admin/curriculum/create', [
            'programs' => $programs
        ]);
    }

    public function store()
    {
        $rules = [
            'program_id'      => 'required|integer',
            'curriculum_code' => 'required|max_length[50]|is_unique[curriculum.curriculum_code]',
            'curriculum_name' => 'required|max_length[255]',
            'academic_year'   => 'required|regex_match[/^\d{4}-\d{4}$/]'
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->curriculumModel->insert([
            'program_id'      => $this->request->getPost('program_id'),
            'curriculum_code' => $this->request->getPost('curriculum_code'),
            'curriculum_name' => $this->request->getPost('curriculum_name'),
            'academic_year'   => $this->request->getPost('academic_year'),
            'description'     => $this->request->getPost('description')
        ]);

        return redirect()
            ->to('/admin/curriculum')
            ->with('message', 'Curriculum created successfully');
    }

    public function edit($id)
    {
        $curriculum = $this->curriculumModel->find($id);
        $programs = $this->programModel->where('is_active', 1)->findAll();

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/curriculum/edit', [
            'curriculum' => $curriculum,
            'programs'   => $programs
        ]);
    }

    public function update($id)
    {
        $rules = [
            'program_id'      => 'required|integer',
            'curriculum_code' => 'required|max_length[50]|is_unique[curriculum.curriculum_code,curriculum_id,{curriculum_id}]',
            'curriculum_name' => 'required|max_length[255]',
            'academic_year'   => 'required|regex_match[/^\d{4}-\d{4}$/]',
            'description'     => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $curriculum = $this->curriculumModel->find($id);

        $duplicate = $this->curriculumModel
            ->where('curriculum_code', $this->request->getPost('curriculum_code'))
            ->where('curriculum_id !=', $id)
            ->first();

        if ($duplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', [
                    'curriculum_code' => 'Curriculum code already exists.'
                ]);
        }

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->curriculumModel->update($id, [
            'program_id'      => $this->request->getPost('program_id'),
            'curriculum_code' => $this->request->getPost('curriculum_code'),
            'curriculum_name' => $this->request->getPost('curriculum_name'),
            'academic_year'   => $this->request->getPost('academic_year'),
            'description'     => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/curriculum')
            ->with('message', 'Curriculum updated successfully.');
    }

    public function toggleStatus($id)
    {
        $curriculum = $this->curriculumModel->find($id);

        if (! $curriculum) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $curriculum['is_active'] ? 0 : 1;

        $this->curriculumModel->update($id, [
            'is_active' => $newStatus
        ]);

        return redirect()
            ->to('/admin/curriculum')
            ->with(
                'message',
                $newStatus
                    ? 'curriculum is activated successfully'
                    : 'curriculum is deactivated successfully'
            );
    }
}
