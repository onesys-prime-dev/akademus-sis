<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\NotificationModel;
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

        // Classes the registrar can enroll this student into: what is
        // plotted this active term for the student's program.
        $activeYear   = (new AcademicYearModel())->activeFormatted();
        $programName  = empty($student['program_id']) ? null : DepartmentModel::programNameById((int) $student['program_id']);
        $classOptions = ($activeYear === null || $programName === null)
            ? []
            : (new EnrollmentModel())->classOptions($programName, $activeYear);

        return view('admin/students/show', [
            'pageTitle'    => $student['first_name'] . ' ' . $student['last_name'] . ' — Student Record',
            'activeAdmin'  => 'students',
            'student'      => $student,
            'statuses'     => StudentModel::STATUSES,
            'classOptions' => $classOptions,
            'activeYear'   => $activeYear,
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

    /**
     * Registrar/admin enrolls THIS student into one plotted class, optionally
     * overriding an unmet prerequisite (mandatory reason, audited). Every
     * other rule — schedule conflict, capacity, curriculum membership,
     * duplicates, the section actually being plotted — still applies.
     */
    public function enrollStudent($id)
    {
        $student = (new StudentModel())->find((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $override = $this->request->getPost('override_prerequisites') === '1';
        $rules    = ['class' => 'required', 'reason' => $override ? 'required|min_length[5]' : 'permit_empty'];

        if (! $this->validate($rules)) {
            return redirect()->to('/admin/students/' . $id)->with('errors', $this->validator->getErrors());
        }

        // "<section_id>|<subject_code>" — parsed, then re-validated against
        // the plotted schedule inside enrollOne (meetingsFor), not trusted.
        [$sectionId, $subjectCode] = array_pad(explode('|', (string) $this->request->getPost('class'), 2), 2, '');

        if (! ctype_digit($sectionId) || $subjectCode === '') {
            return redirect()->to('/admin/students/' . $id)->with('errors', ['Choose a class to enroll the student into.']);
        }

        $result = (new EnrollmentModel())->enrollOne(
            (int) $student['user_id'],
            (int) $sectionId,
            $subjectCode,
            'manual',
            $override ? ['reason' => (string) $this->request->getPost('reason'), 'by' => (int) auth()->id()] : null
        );

        if (! $result['ok']) {
            return redirect()->to('/admin/students/' . $id)->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enrollment_added',
            "You were enrolled in {$subjectCode} by the registrar.",
            'student/schedule'
        );

        return redirect()->to('/admin/students/' . $id)->with('message', "Enrolled in {$subjectCode}" . ($override ? ' (prerequisite override recorded).' : '.'));
    }

    /**
     * Registrar/admin drops one of a student's enrollments on their behalf,
     * with a mandatory reason. The enrollment must belong to THIS student
     * (the URL's student id is checked against the row, not trusted).
     */
    public function dropEnrollment($id, $enrollmentId)
    {
        if (! $this->validate(['reason' => 'required|min_length[5]'])) {
            return redirect()->to('/admin/students/' . $id)->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new EnrollmentModel())->drop(
            (int) $enrollmentId,
            (string) $this->request->getPost('reason'),
            (int) auth()->id(),
            (int) $student['user_id']
        );

        if (! $result['ok']) {
            return redirect()->to('/admin/students/' . $id)->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enrollment_dropped',
            "{$result['enrollment']['subject_code']} was dropped from your enrollment by the registrar. Reason: " . $this->request->getPost('reason'),
            'student/schedule'
        );

        return redirect()->to('/admin/students/' . $id)->with('message', "{$result['enrollment']['subject_code']} dropped.");
    }

    public function assignCurriculum($id)
    {
        $rules = [
            'curriculum_id' => 'required|integer',
            'reason'        => 'required|min_length[5]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $result = (new StudentModel())->assignCurriculum(
            (int) $id,
            (int) $this->request->getPost('curriculum_id'),
            $this->request->getPost('reason'),
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error'] ?? 'Could not assign curriculum.']);
        }

        return redirect()
            ->to('/admin/students/' . $id)
            ->with('message', 'Curriculum assignment updated.');
    }
}
