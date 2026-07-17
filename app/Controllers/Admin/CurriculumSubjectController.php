<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CurriculumSubjectModel;
use App\Models\CurriculumModel;
use App\Models\SubjectModel;

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
                'curriculumSubjects' => $curriculumSubjects
            ]
        );
    }

    public function create()
    {
        $curricula = $this->curriculumModel
            ->where('is_active', 1)
            ->findAll();

        $subjects = $this->subjectModel
            ->where('is_active', 1)
            ->findAll();

        return view(
            'admin/curriculum-subjects/create',
            [
                'curricula' => $curricula,
                'subjects'  => $subjects
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

    public function edit($id) {}

    public function update($id) {}

    public function toggleStatus($id) {}

    public function destroy() {}
}
