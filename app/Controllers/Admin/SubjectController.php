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
        $subjects = db_connect()->table('subjects s')
            ->select('s.*, sd.name AS owner_dept_name')
            ->join('sub_departments sd', 'sd.sub_dept_id = s.sub_department_id', 'left')
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();

        return view('admin/subjects/index', [
            'subjects'       => $subjects,
            'subDepartments' => $this->subDepartments(),
            'activeAdmin'    => 'subjects',
        ]);
    }

    public function create()
    {
        return view('admin/subjects/create', [
            'subDepartments' => $this->subDepartments(),
            'activeAdmin'    => 'subjects',
        ]);
    }

    /** Every department, for the owner picker — alphabetical, id => name. */
    private function subDepartments(): array
    {
        return array_column(
            db_connect()->table('sub_departments')->select('sub_dept_id, name')->orderBy('name')->get()->getResultArray(),
            'name',
            'sub_dept_id'
        );
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
            'sub_department_id' => $this->request->getPost('sub_department_id') ?: null,
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
            'subDepartments'   => $this->subDepartments(),
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
            'sub_department_id' => $this->request->getPost('sub_department_id') ?: null,
        ]);

        return redirect()
            ->to('/admin/subjects')
            ->with('message', 'Subjects updated successfully.');
    }

    /**
     * Quick owner reassignment straight from the list — same field the
     * Chairperson Subject Ownership page reads/writes
     * (subjects.sub_department_id), so a change here is visible there
     * immediately, and a subject a chairperson added shows up here the same
     * way (both sides read the one `subjects` table — nothing to sync).
     */
    public function updateOwner($id)
    {
        $subject = $this->subjectModel->find($id);

        if (! $subject) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->subjectModel->update($id, [
            'sub_department_id' => $this->request->getPost('sub_department_id') ?: null,
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true]);
        }

        return redirect()
            ->to('/admin/subjects')
            ->with('message', "{$subject['subject_code']} owner updated.");
    }

    /**
     * Permanently removes every selected subject that isn't currently in
     * use. "In use" = plotted anywhere, actively assigned in a curriculum,
     * or ever enrolled — plotted_schedules/enrollments/faculty_assignments
     * key off subject_code with no FK, so nothing at the DB level would
     * stop an in-use subject from being deleted; this check is the only
     * thing that does. Everything else referencing subject_id (curriculum
     * mappings, component templates, substitutions, placement requests)
     * cascades automatically.
     */
    public function bulkDestroy()
    {
        $ids = array_filter(array_map('intval', (array) $this->request->getPost('ids')));

        if ($ids === []) {
            return redirect()->to('/admin/subjects')->with('errors', ['Select at least one subject to remove.']);
        }

        $subjects = $this->subjectModel->whereIn('subject_id', $ids)->findAll();
        $db       = db_connect();

        $deleted = [];
        $skipped = [];

        foreach ($subjects as $subject) {
            $inUse = $db->table('plotted_schedules')->where('subject_code', $subject['subject_code'])->countAllResults() > 0
                || $db->table('curriculum_subjects')->where('subject_id', $subject['subject_id'])->where('is_active', 1)->countAllResults() > 0
                || $db->table('enrollments')->where('subject_code', $subject['subject_code'])->countAllResults() > 0;

            if ($inUse) {
                $skipped[] = $subject;

                continue;
            }

            $this->subjectModel->delete($subject['subject_id']);
            $deleted[] = $subject;
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'ok'      => true,
                'deleted' => array_map(static fn (array $s) => (int) $s['subject_id'], $deleted),
                'skipped' => array_map(static fn (array $s) => ['id' => (int) $s['subject_id'], 'code' => $s['subject_code']], $skipped),
            ]);
        }

        $deletedCodes = array_column($deleted, 'subject_code');
        $skippedCodes = array_column($skipped, 'subject_code');
        $message = $deletedCodes === [] ? '' : count($deletedCodes) . ' subject(s) deleted: ' . implode(', ', $deletedCodes) . '. ';
        $message .= $skippedCodes === [] ? '' : count($skippedCodes) . ' skipped (still in use — plotted, curriculum-assigned, or enrolled): ' . implode(', ', $skippedCodes) . '.';

        return redirect()->to('/admin/subjects')->with($deleted !== [] ? 'message' : 'errors', $deleted !== [] ? trim($message) : [trim($message)]);
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

    public function toggleStatus($id) {
        $subject = $this->subjectModel->find($id);

        if (! $subject) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $newStatus = $subject['is_active'] ? 0 : 1;

        $this->subjectModel->update($id, [
            'is_active' => $newStatus
        ]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['ok' => true, 'is_active' => (bool) $newStatus]);
        }

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
