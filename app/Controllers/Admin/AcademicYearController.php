<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;

class AcademicYearController extends BaseController
{
    protected AcademicYearModel $ayModel;

    public function __construct()
    {
        $this->ayModel = new AcademicYearModel();
    }

    public function index()
    {
        $years = $this->ayModel->orderBy('label', 'desc')->orderBy('semester', 'asc')->findAll();

        return view('admin/academic_years/index', [
            'pageTitle'   => 'Academic Years',
            'activeAdmin' => 'academic-years',
            'years'       => $years,
        ]);
    }

    public function store()
    {
        $rules = [
            'label'    => 'required|max_length[20]',
            'semester' => 'required|in_list[1st,2nd,Summer]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->ayModel->insert([
            'label'    => $this->request->getPost('label'),
            'semester' => $this->request->getPost('semester'),
        ]);

        return redirect()
            ->to('/admin/academic-years')
            ->with('message', 'Academic year added successfully.');
    }

    public function setActive($id)
    {
        $ay = $this->ayModel->find($id);

        if (! $ay) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->ayModel->setActive($id);

        return redirect()
            ->to('/admin/academic-years')
            ->with('message', "{$ay['label']} ({$ay['semester']}) set as active term.");
    }
}
