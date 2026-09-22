<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CurriculumSubjectModel;
use App\Models\CurriculumModel;
use App\Models\SubjectModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CurriculumSubjectController extends BaseController
{
    protected CurriculumSubjectModel $curriculumSubjectModel;
    protected CurriculumModel $curriculumModel;
    protected SubjectModel $subjectModel;

    public function __construct()
    {
        $this->curriculumSubjectModel = new CurriculumSubjectModel();
        $this->curriculumModel = new CurriculumModel();
        $this->subjectModel = new SubjectModel();
    }

    public function index()
    {
        $curriculumSubjects = $this->curriculumSubjectModel
            ->select('
                curriculum_subjects.*,
                curriculum.curriculum_name,
                curriculum.curriculum_code,
                curriculum.version_number,
                curriculum.status as curriculum_status,
                subjects.subject_code,
                subjects.subject_name
            ')
            ->join(
                'curriculum',
                'curriculum.curriculum_id = curriculum_subjects.curriculum_id'
            )
            ->join(
                'subjects',
                'subjects.subject_id = curriculum_subjects.subject_id'
            )
            ->findAll();

        return view(
            'admin/curriculum-subjects/index',
            [
                'curriculumSubjects' => $curriculumSubjects,
                'activeAdmin'        => 'curriculum-subjects',
            ]
        );
    }

    public function create()
    {
        // Only draft curricula can receive new subject mappings — a
        // published curriculum is an immutable snapshot (see CurriculumModel).
        $curricula = $this->curriculumModel
            ->where('is_active', 1)
            ->where('status', 'draft')
            ->findAll();

        $subjects = $this->subjectModel
            ->where('is_active', 1)
            ->findAll();

        return view(
            'admin/curriculum-subjects/create',
            [
                'curricula'   => $curricula,
                'subjects'    => $subjects,
                'activeAdmin' => 'curriculum-subjects',
            ]
        );
    }

    public function store()
    {
        $rules = [
            'curriculum_id' => 'required|integer',
            'subject_id'    => 'required|integer',
            'year_level'    => 'required|integer|greater_than[0]|less_than[5]',
            'semester'      => 'required|integer|greater_than[0]|less_than[4]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $error = $this->guardDraft((int) $this->request->getPost('curriculum_id'));
        if ($error) {
            return redirect()->back()->withInput()->with('errors', [$error]);
        }

        $exists = $this->curriculumSubjectModel
            ->where('curriculum_id', $this->request->getPost('curriculum_id'))
            ->where('subject_id', $this->request->getPost('subject_id'))
            ->first();

        if ($exists) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', [
                    'subject' => 'This subject is already assigned/exists to the selected curriculum.'
                ]);
        }

        $this->curriculumSubjectModel->insert([
            'curriculum_id' => $this->request->getPost('curriculum_id'),
            'subject_id'    => $this->request->getPost('subject_id'),
            'year_level'    => $this->request->getPost('year_level'),
            'semester'      => $this->request->getPost('semester'),
        ]);

        return redirect()
            ->to('/admin/curriculum-subjects')
            ->with('message', 'Curriculum subject assigned successfully');
    }

    public function edit($id)
    {
        $curriculumSubject = $this->curriculumSubjectModel->find($id);

        if (! $curriculumSubject) {
            throw PageNotFoundException::forPageNotFound();
        }

        $curriculum = $this->curriculumModel->find($curriculumSubject['curriculum_id']);

        if (! $this->curriculumModel->isEditable($curriculum)) {
            return redirect()
                ->to('/admin/curriculum-subjects')
                ->with('errors', ['This mapping belongs to a published curriculum and cannot be edited — create a new curriculum version instead.']);
        }

        return view('admin/curriculum-subjects/edit', [
            'curriculumSubject' => $curriculumSubject,
            'curriculum'        => $curriculum,
            'subjects'          => $this->subjectModel->where('is_active', 1)->findAll(),
            'activeAdmin'       => 'curriculum-subjects',
        ]);
    }

    public function update($id)
    {
        $curriculumSubject = $this->curriculumSubjectModel->find($id);

        if (! $curriculumSubject) {
            throw PageNotFoundException::forPageNotFound();
        }

        $error = $this->guardDraft((int) $curriculumSubject['curriculum_id']);
        if ($error) {
            return redirect()->to('/admin/curriculum-subjects')->with('errors', [$error]);
        }

        $rules = [
            'subject_id' => 'required|integer',
            'year_level' => 'required|integer|greater_than[0]|less_than[5]',
            'semester'   => 'required|integer|greater_than[0]|less_than[4]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $duplicate = $this->curriculumSubjectModel
            ->where('curriculum_id', $curriculumSubject['curriculum_id'])
            ->where('subject_id', $this->request->getPost('subject_id'))
            ->where('curriculum_subject_id !=', $id)
            ->first();

        if ($duplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['subject' => 'This subject is already assigned to this curriculum.']);
        }

        $this->curriculumSubjectModel->update($id, [
            'subject_id' => $this->request->getPost('subject_id'),
            'year_level' => $this->request->getPost('year_level'),
            'semester'   => $this->request->getPost('semester'),
        ]);

        return redirect()
            ->to('/admin/curriculum-subjects')
            ->with('message', 'Curriculum subject updated successfully');
    }

    public function toggleStatus($id)
    {
        $curriculumSubject = $this->curriculumSubjectModel->find($id);

        if (! $curriculumSubject) {
            throw PageNotFoundException::forPageNotFound();
        }

        $error = $this->guardDraft((int) $curriculumSubject['curriculum_id']);
        if ($error) {
            return redirect()->to('/admin/curriculum-subjects')->with('errors', [$error]);
        }

        $this->curriculumSubjectModel->update($id, [
            'is_active' => $curriculumSubject['is_active'] ? 0 : 1,
        ]);

        return redirect()
            ->to('/admin/curriculum-subjects')
            ->with('message', 'Curriculum subject status updated.');
    }

    public function destroy($id)
    {
        $curriculumSubject = $this->curriculumSubjectModel->find($id);

        if (! $curriculumSubject) {
            throw PageNotFoundException::forPageNotFound();
        }

        $error = $this->guardDraft((int) $curriculumSubject['curriculum_id']);
        if ($error) {
            return redirect()->to('/admin/curriculum-subjects')->with('errors', [$error]);
        }

        $this->curriculumSubjectModel->delete($id);

        return redirect()
            ->to('/admin/curriculum-subjects')
            ->with('message', 'Curriculum subject removed.');
    }

    /**
     * Returns an error message if the given curriculum is not editable
     * (i.e. not 'draft'), or null if the mutation may proceed.
     */
    private function guardDraft(int $curriculumId): ?string
    {
        $curriculum = $this->curriculumModel->find($curriculumId);

        if ($curriculum === null) {
            return 'Curriculum not found.';
        }

        if (! $this->curriculumModel->isEditable($curriculum)) {
            return 'This curriculum is published and immutable — create a new version to change its subjects.';
        }

        return null;
    }
}
