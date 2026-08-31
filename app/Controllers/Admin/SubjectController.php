<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\SubjectModel;

class SubjectController extends BaseController
{
    protected SubjectModel $subjectModel;
    public function __construct()
    {
        $this->subjectModel = new SubjectModel();
    }

    public function index()
    {
        $subjects = $this->subjectModel->findAll();

        return view('admin/subjects/index', [
            'subjects' => $subjects
        ]);
    }

    public function create()
    {
        return view('admin/subjects/create');
    }

    public function store()
    {
        $rules = [
            'subject_code'      => 'required|max_length[20]',
            'subject_name'      => 'required|max_length[255]',
            'units'             => 'permit_empty|decimal',
            'contact_hours'     => 'permit_empty|decimal',
            'is_laboratory'     => 'permit_empty|in_list[0,1]',
            'lecture_units'     => 'permit_empty|decimal',
            'laboratory_units'  => 'permit_empty|decimal',
            'meetings_per_week' => 'permit_empty|integer',
            'description'       => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->subjectModel->insert([
            'subject_code'      => $this->request->getPost('subject_code'),
            'subject_name'      => $this->request->getPost('subject_name'),
            'units'             => $this->request->getPost('units'),
            'contact_hours'     => $this->request->getPost('contact_hours') ?: null,
            'is_laboratory'     => $this->request->getPost('is_laboratory') ? 1 : 0,
            'lecture_units'     => $this->request->getPost('lecture_units') ?: null,
            'laboratory_units'  => $this->request->getPost('laboratory_units') ?: null,
            'meetings_per_week' => $this->request->getPost('meetings_per_week') ?: null,
            'description'       => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/subjects')
            ->with('message', 'Subject created successfully.');
    }

    public function edit($id)
    {
        $subject = $this->subjectModel->find($id);

        if (! $subject) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/subjects/edit', [
            'subject' => $subject
        ]);
    }

    public function update($id)
    {
        $rules = [
            'subject_code'      => 'required|max_length[20]',
            'subject_name'      => 'required|max_length[255]',
            'units'             => 'permit_empty|decimal',
            'contact_hours'     => 'permit_empty|decimal',
            'is_laboratory'     => 'permit_empty|in_list[0,1]',
            'lecture_units'     => 'permit_empty|decimal',
            'laboratory_units'  => 'permit_empty|decimal',
            'meetings_per_week' => 'permit_empty|integer',
            'description'       => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $subject = $this->subjectModel->find($id);

        if (! $subject) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->subjectModel->update($id, [
            'subject_code'      => $this->request->getPost('subject_code'),
            'subject_name'      => $this->request->getPost('subject_name'),
            'units'             => $this->request->getPost('units'),
            'contact_hours'     => $this->request->getPost('contact_hours') ?: null,
            'is_laboratory'     => $this->request->getPost('is_laboratory') ? 1 : 0,
            'lecture_units'     => $this->request->getPost('lecture_units') ?: null,
            'laboratory_units'  => $this->request->getPost('laboratory_units') ?: null,
            'meetings_per_week' => $this->request->getPost('meetings_per_week') ?: null,
            'description'       => $this->request->getPost('description'),
        ]);

        return redirect()
            ->to('/admin/subjects')
            ->with('message', 'Subjects updated successfully.');
    }

    // public function delete($id)
    // {
    //     $subject = $this->subjectModel->find($id);

    //     if (! $subject) {
    //         throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
    //     }

    //     $this->subjectModel->update($id, [
    //         'is_active' => 0
    //     ]);

    //     return redirect()
    //         ->to('/admin/subjects')
    //         ->with('message', 'Subjects deactivated successfully.');
    // }

    public function toggleStatus($id) {
        $subject = $this->subjectModel->find($id);

        if (! $subject) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $subject['is_active'] ? 0 : 1;

        $this->subjectModel->update($id, [ 
            'is_active' => $newStatus
        ]);

        return redirect()
            ->to('/admin/subjects')
            ->with(
                'message', 
                $newStatus
                    ? 'Subject activated.'
                    : 'Subject deactivated.'
            );
    }
}
