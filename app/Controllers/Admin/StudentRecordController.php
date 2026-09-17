<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ProgramModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The Student Records module the SIS development roadmap flagged as
 * "does not exist yet": a registrar/admin-facing lookup of any student,
 * independent of the student's own self-service portal (StudentController).
 */
class StudentRecordController extends BaseController
{
    public function index()
    {
        $studentModel = new StudentModel();

        $filters = [
            'program_id' => $this->request->getGet('program_id'),
            'year_level' => $this->request->getGet('year_level'),
            'status'     => $this->request->getGet('status'),
            'q'          => trim((string) $this->request->getGet('q')),
        ];

        $allStudents = $studentModel->listFiltered($filters);

        $perPage      = 20;
        $totalStudents = count($allStudents);
        $totalPages   = max(1, (int) ceil($totalStudents / $perPage));
        $page         = (int) ($this->request->getGet('page') ?: 1);
        $page         = max(1, min($page, $totalPages));
        $students     = array_slice($allStudents, ($page - 1) * $perPage, $perPage);

        return view('admin/students/index', [
            'pageTitle'     => 'Student Records',
            'activeAdmin'   => 'students',
            'students'      => $students,
            'programs'      => (new ProgramModel())->where('is_active', 1)->orderBy('program_name')->findAll(),
            'yearLevels'    => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'statuses'      => StudentModel::STATUSES,
            'filters'       => $filters,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'totalStudents' => $totalStudents,
            'perPage'       => $perPage,
        ]);
    }

    public function show($id)
    {
        $studentModel = new StudentModel();
        $student      = $studentModel->findDetailed((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('admin/students/show', [
            'pageTitle'   => $student['first_name'] . ' ' . $student['last_name'] . ' — Student Record',
            'activeAdmin' => 'students',
            'student'     => $student,
            'statuses'    => StudentModel::STATUSES,
        ]);
    }

    public function changeStatus($id)
    {
        $rules = [
            'student_status' => 'required|in_list[' . implode(',', StudentModel::STATUSES) . ']',
            'reason'         => 'required|min_length[5]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $result = (new StudentModel())->changeStatus(
            (int) $id,
            $this->request->getPost('student_status'),
            $this->request->getPost('reason'),
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error'] ?? 'Could not update status.']);
        }

        return redirect()
            ->to('/admin/students/' . $id)
            ->with('message', 'Student status updated.');
    }
}
