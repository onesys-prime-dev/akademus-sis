<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ComponentTypeModel;
use App\Models\SubjectComponentTemplateModel;
use App\Models\SubjectModel;
use App\Models\SubjectPrerequisiteModel;

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
            'subjects'    => $subjects,
            'activeAdmin' => 'subjects',
        ]);
    }

    public function create()
    {
        return view('admin/subjects/create', [
            'activeAdmin' => 'subjects',
        ]);
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

        $prerequisites = new SubjectPrerequisiteModel();

        return view('admin/subjects/edit', [
            'subject'          => $subject,
            'prerequisites'    => $prerequisites->forSubject((int) $id),
            'candidates'       => $prerequisites->candidatesFor((int) $id),
            'activeAdmin'      => 'subjects',
            'componentTemplates' => (new SubjectComponentTemplateModel())->forSubject((int) $id, false),
            'componentTypes'     => (new ComponentTypeModel())->where('is_active', 1)->orderBy('name')->findAll(),
        ]);
    }

    /**
     * Step 9: add one component-split rule (e.g. "Laboratory, required,
     * 3h/week") to a subject's catalog entry. Instantiated into real
     * offering_components rows only when a NEW offering is later created
     * for this subject — editing a template never mutates offerings that
     * were already instantiated from it.
     */
    public function addComponentTemplate($id)
    {
        if (! $this->validate([
            'component_type_id' => 'required|is_natural_no_zero',
            'assignment_rule'   => 'required|in_list[shared,one_of]',
            'is_required'       => 'permit_empty|in_list[0,1]',
            'required_hours'    => 'permit_empty|decimal',
            'default_capacity'  => 'permit_empty|integer',
        ])) {
            return redirect()->to('/admin/subjects/edit/' . $id)->with('errors', $this->validator->getErrors());
        }

        (new SubjectComponentTemplateModel())->insert([
            'subject_id'          => (int) $id,
            'component_type_id'   => (int) $this->request->getPost('component_type_id'),
            'name'                => $this->request->getPost('name') ?: null,
            'is_required'         => $this->request->getPost('is_required') ? 1 : 0,
            'required_hours'      => $this->request->getPost('required_hours') ?: null,
            'default_capacity'    => $this->request->getPost('default_capacity') ?: null,
            'preferred_room_type' => $this->request->getPost('preferred_room_type') ?: null,
            'assignment_rule'     => $this->request->getPost('assignment_rule'),
        ]);

        return redirect()->to('/admin/subjects/edit/' . $id)->with('message', 'Component template added. It will apply to offerings created from now on.');
    }

    public function deactivateComponentTemplate($id, $templateId)
    {
        $result = (new SubjectComponentTemplateModel())->deactivate((int) $templateId);

        return redirect()
            ->to('/admin/subjects/edit/' . $id)
            ->with($result['ok'] ? 'message' : 'errors', $result['ok'] ? 'Component template deactivated.' : [$result['error']]);
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

    public function addPrerequisite($id)
    {
        if (! $this->validate(['prerequisite_subject_id' => 'required|is_natural_no_zero'])) {
            return redirect()->to('/admin/subjects/edit/' . $id)->with('errors', ['Choose a subject to add as a prerequisite.']);
        }

        $result = (new SubjectPrerequisiteModel())->addPrerequisite((int) $id, (int) $this->request->getPost('prerequisite_subject_id'));

        if (! $result['ok']) {
            return redirect()->to('/admin/subjects/edit/' . $id)->with('errors', [$result['error']]);
        }

        return redirect()->to('/admin/subjects/edit/' . $id)->with('message', 'Prerequisite added.');
    }

    public function removePrerequisite($id, $prerequisiteId)
    {
        $removed = (new SubjectPrerequisiteModel())->removePrerequisite((int) $id, (int) $prerequisiteId);

        return redirect()
            ->to('/admin/subjects/edit/' . $id)
            ->with($removed ? 'message' : 'errors', $removed ? 'Prerequisite removed.' : ['That prerequisite was not set.']);
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
