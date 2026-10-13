<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;
use App\Models\AcademicYearModel;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\GraduationClearanceModel;
use App\Models\NotificationModel;
use App\Models\ProgramModel;
use App\Models\StudentModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * The Student Records module the SIS development roadmap flagged as
 * "does not exist yet": a registrar/admin-facing lookup of any student,
 * independent of the student's own self-service portal (StudentController).
 */
class StudentRecordController extends BaseController
{
    /**
     * Served under both /admin/students and /registrar/students. Which one is
     * decided by the URL the route group matched (each group is already
     * filtered by role, and each route by permission), never by user input.
     */
    private function isRegistrarPortal(): bool
    {
        return service('request')->getUri()->getSegment(1) === 'registrar';
    }

    private function recordUrl($id): string
    {
        return '/' . ($this->isRegistrarPortal() ? 'registrar' : 'admin') . '/students/' . $id;
    }

    /** Shell (sidebar/brand) + link prefix for the current portal. */
    private function shell(): array
    {
        return $this->isRegistrarPortal()
            ? RegistrarPortal::shell('students')
            : ['activeAdmin' => 'students', 'basePath' => 'admin'];
    }

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
            'students'      => $students,
            'programs'      => (new ProgramModel())->where('is_active', 1)->orderBy('program_name')->findAll(),
            'yearLevels'    => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'statuses'      => StudentModel::STATUSES,
            'filters'       => $filters,
            'page'          => $page,
            'totalPages'    => $totalPages,
            'totalStudents' => $totalStudents,
            'perPage'       => $perPage,
        ] + $this->shell());
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

        $openClearance = (new GraduationClearanceModel())->where('student_id', (int) $id)->where('status', 'pending')->orderBy('id', 'DESC')->first();

        return view('admin/students/show', [
            'pageTitle'     => $student['first_name'] . ' ' . $student['last_name'] . ' — Student Record',
            'student'       => $student,
            'statuses'      => StudentModel::STATUSES,
            'classOptions'  => $classOptions,
            'activeYear'    => $activeYear,
            'clearanceCase' => $openClearance,
        ] + $this->shell());
    }

    /**
     * Opens a graduation clearance case for this student — registrar-only
     * (gated by permission:clearance.manage in Routes.php), scoped under
     * /registrar even though this controller is shared with /admin.
     */
    public function initiateClearance($id)
    {
        $student = (new StudentModel())->find((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $activeYear = (new AcademicYearModel())->activeFormatted() ?? 'N/A';
        $result     = (new GraduationClearanceModel())->initiate((int) $id, $activeYear, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->to($this->recordUrl($id))->with('errors', [$result['error']]);
        }

        return redirect()->to('/registrar/clearance/' . $result['clearance_id'])->with('message', 'Clearance case opened.');
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
            ->to($this->recordUrl($id))
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

        $overridePrereq   = $this->request->getPost('override_prerequisites') === '1';
        $overrideCapacity = $this->request->getPost('override_capacity') === '1';
        $rules            = ['class' => 'required', 'reason' => ($overridePrereq || $overrideCapacity) ? 'required|min_length[5]' : 'permit_empty'];

        if (! $this->validate($rules)) {
            return redirect()->to($this->recordUrl($id))->with('errors', $this->validator->getErrors());
        }

        // "<section_id>|<subject_code>" — parsed, then re-validated against
        // the plotted schedule inside enrollOne (meetingsFor), not trusted.
        [$sectionId, $subjectCode] = array_pad(explode('|', (string) $this->request->getPost('class'), 2), 2, '');

        if (! ctype_digit($sectionId) || $subjectCode === '') {
            return redirect()->to($this->recordUrl($id))->with('errors', ['Choose a class to enroll the student into.']);
        }

        $reason     = (string) $this->request->getPost('reason');
        $actorId    = (int) auth()->id();
        $overrideArg = static fn (bool $on) => $on ? ['reason' => $reason, 'by' => $actorId] : null;

        $result = (new EnrollmentModel())->enrollOne(
            (int) $student['user_id'],
            (int) $sectionId,
            $subjectCode,
            'manual',
            $overrideArg($overridePrereq),
            auth()->user()->can('calendar.override'),
            $overrideArg($overrideCapacity),
            auth()->user()->can('finance.override')
        );

        if (! $result['ok']) {
            return redirect()->to($this->recordUrl($id))->with('errors', [$result['error']]);
        }

        if ($overridePrereq || $overrideCapacity) {
            // enrollment_logs (written inside enrollOne) is enrollment-specific;
            // audit_logs is the cross-domain compliance view
            // (admin/audit-logs) staff actually search across every kind of
            // override, so an institution-wide override also gets recorded
            // there.
            (new AuditLogModel())->record(
                'enrollment.override',
                'enrollment',
                $result['enrollment_id'],
                null,
                ['subject_code' => $subjectCode, 'override_prerequisites' => $overridePrereq, 'override_capacity' => $overrideCapacity],
                $reason,
                $actorId,
                $this->isRegistrarPortal() ? 'registrar' : 'admin'
            );
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enrollment_added',
            "You were enrolled in {$subjectCode} by the registrar.",
            'student/schedule'
        );

        return redirect()->to($this->recordUrl($id))->with('message', "Enrolled in {$subjectCode}" . ($override ? ' (prerequisite override recorded).' : '.'));
    }

    /**
     * Registrar/admin drops one of a student's enrollments on their behalf,
     * with a mandatory reason. The enrollment must belong to THIS student
     * (the URL's student id is checked against the row, not trusted).
     */
    public function dropEnrollment($id, $enrollmentId)
    {
        if (! $this->validate(['reason' => 'required|min_length[5]'])) {
            return redirect()->to($this->recordUrl($id))->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new EnrollmentModel())->drop(
            (int) $enrollmentId,
            (string) $this->request->getPost('reason'),
            (int) auth()->id(),
            (int) $student['user_id'],
            auth()->user()->can('finance.override')
        );

        if (! $result['ok']) {
            return redirect()->to($this->recordUrl($id))->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enrollment_dropped',
            "{$result['enrollment']['subject_code']} was dropped from your enrollment by the registrar. Reason: " . $this->request->getPost('reason'),
            'student/schedule'
        );

        return redirect()->to($this->recordUrl($id))->with('message', "{$result['enrollment']['subject_code']} dropped.");
    }

    /**
     * Records a payment against the student's tuition assessment for one
     * academic year. Rejected if no assessment exists yet for that year —
     * an assessment is created automatically on enrollment
     * (EnrollmentModel::recomputeTuition), so this only fails for a student
     * with no enrollments at all yet.
     */
    public function recordPayment($id)
    {
        $rules = [
            'academic_year' => 'required',
            'amount'        => 'required|decimal|greater_than[0]',
            'method'        => 'required',
            'reference_no'  => 'permit_empty|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to($this->recordUrl($id))->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $id);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new TuitionAssessmentService())->recordPayment(
            (int) $id,
            (string) $this->request->getPost('academic_year'),
            (float) $this->request->getPost('amount'),
            (string) $this->request->getPost('method'),
            $this->request->getPost('reference_no') ?: null,
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->to($this->recordUrl($id))->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'payment_recorded',
            'A payment of ' . number_format((float) $this->request->getPost('amount'), 2) . ' was recorded on your tuition account.',
            'student/tuition'
        );

        return redirect()->to($this->recordUrl($id))->with('message', 'Payment recorded.');
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
            ->to($this->recordUrl($id))
            ->with('message', 'Curriculum assignment updated.');
    }
}
