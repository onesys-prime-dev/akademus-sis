<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CurriculumSubjectModel;
use App\Models\CurriculumModel;
use App\Models\ProgramModel;
use App\Models\Subject;
use App\Models\SubjectModel;

class CurriculumSubjectController extends BaseController {
    protected CurriculumSubjectModel $curriculumSubjectModel;
    protected CurriculumModel $curriculumModel;
    protected SubjectModel $subjectModel;

    public function __construct()
    {
        $this->curriculumSubjectModel = new CurriculumSubjectModel();
        $this->curriculumModel = new CurriculumModel();
        $this->subjectModel = new SubjectModel();
    }

    public function index() {
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

    public function create() {
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

    public function store() {

    }

    public function edit($id) {

    }

    public function update($id) {

    }

    public function toggleStatus($id) {

    }

    public function destroy() {

    }
}

?>