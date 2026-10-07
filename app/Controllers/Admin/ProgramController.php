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
            'return'      => (string) $this->request->getGet('return'),
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
            ->to($this->returnUrl())
            ->with('message', 'Programs updated successfully.');
    }

    /**
     * This program editor is reachable both from the standalone Programs
     * list and from a department's detail page (Admin\SubDepartmentController
     * — the "Programs" panel on a department shows the same edit/toggle
     * actions). A `return` field (carried through edit()'s view and
     * toggleStatus()'s form) sends the admin back to whichever one they came
     * from instead of always bouncing to the generic list.
     */
    private function returnUrl(): string
    {
        $return = (string) $this->request->getPost('return');

        // Only ever redirect back into this same app — a bare hidden field
        // reflected straight into redirect()->to() would otherwise be an
        // open-redirect if someone crafted the POST themselves.
        if ($return !== '' && str_starts_with($return, site_url())) {
            return $return;
        }

        return '/admin/programs';
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
            ->to($this->returnUrl())
            ->with(
                'message',
                $newStatus
                    ? 'program activated.'
                    : 'program deactivated.'
            );
    }
}
