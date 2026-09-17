<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ProgramModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProgramController extends BaseController
{
    protected ProgramModel $programModel;
    public function __construct()
    {
        $this->programModel = new ProgramModel();
    }

    public function index()
    {
        $programs = $this->programModel->findAll();

        return view('admin/programs/index', [
            'programs'    => $programs,
            'activeAdmin' => 'programs',
        ]);
    }

    public function create()
    {
        return view('admin/programs/create', [
            'activeAdmin' => 'programs',
        ]);
    }

    public function store()
    {
        $rules = [
            'program_code' => 'required|max_length[20]',
            'program_name' => 'required|max_length[255]',
            'description'  => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->programModel->insert([
            'program_code' => $this->request->getPost('program_code'),
            'program_name' => $this->request->getPost('program_name'),
            'description'  => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/programs')
            ->with('message', 'Program created successfully.');
    }

    public function edit($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/programs/edit', [
            'program'     => $program,
            'activeAdmin' => 'programs',
        ]);
    }

    public function update($id)
    {
        $rules = [
            'program_code' => 'required|max_length[20]',
            'program_name' => 'required|max_length[100]',
            'description'  => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->programModel->update($id, [
            'program_code' => $this->request->getPost('program_code'),
            'program_name' => $this->request->getPost('program_name'),
            'description'  => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/programs')
            ->with('message', 'Programs updated successfully.');
    }

    // public function delete($id)
    // {
    //     $program = $this->programModel->find($id);

    //     if (! $program) {
    //         throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    //     }
    //     $this->programModel->update($id, [
    //         'is_active' => 0
    //     ]);

    //     return redirect()
    //         ->to('/admin/programs')
    //         ->with('message', 'Programs deactivated successfully.');
    // }

    public function toggleStatus($id)
    {
        $program = $this->programModel->find($id);

        if (! $program) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $program['is_active'] ? 0 : 1;

        $this->programModel->update($id, [
            'is_active' => $newStatus
        ]);

        return redirect()
            ->to('/admin/programs')
            ->with(
                'message',
                $newStatus
                    ? 'program activated.'
                    : 'program deactivated.'
            );
    }
}
