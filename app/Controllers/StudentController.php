<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AssessmentItemModel;
use App\Models\AcademicYearModel;
use App\Libraries\StudentDashboardSchedule;
use App\Models\PaymentProofModel;
use App\Models\PaymentScheduleModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class StudentController extends BaseController
{
    public function index(): string
    {
        $account = auth()->user();
        $section = $this->mySection();

        $userId = (int) auth()->id();
        $activeTerm = (new AcademicYearModel())->activeFormatted();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Manila'));
        $enrolledCount = 0;
        $meetings = $publishedGrades = $paymentSchedule = [];
        $assessment = null;
        if ($activeTerm !== null) {
            $enrolledCount = db_connect()->table('enrollments e')
                ->join('sections s', 's.section_id = e.section_id')
                ->where('e.user_id', $userId)->where('e.status', 'enrolled')
                ->where('s.academic_year', $activeTerm)->countAllResults();
            $meetings = db_connect()->table('enrollments e')
                ->select('p.day, p.start_time, p.end_time, p.subject_code, p.subject_title, p.room, p.prof, s.section, s.program, s.year_level')
                ->join('sections s', 's.section_id = e.section_id')
                ->join('plotted_schedules p', 'p.academic_year = s.academic_year AND p.program = s.program AND p.year_level = s.year_level AND p.section = s.section AND p.subject_code = e.subject_code')
                ->where('e.user_id', $userId)->where('e.status', 'enrolled')
                ->where('s.academic_year', $activeTerm)
                ->orderBy('p.day')->orderBy('p.start_time')->get()->getResultArray();
            // Only official grades may be shown to students.
            $publishedGrades = db_connect()->table('grades g')
                ->select('g.subject_code, g.grade_value, g.remarks, g.is_passing, g.locked_at')
                ->join('enrollments e', 'e.enrollment_id = g.enrollment_id')
                ->where('e.user_id', $userId)->where('e.status', 'enrolled')
                ->where('g.user_id', $userId)->where('g.academic_year', $activeTerm)
                ->where('g.status', 'locked')->orderBy('g.locked_at', 'DESC')->get()->getResultArray();
            $student = (new StudentModel())->where('user_id', $userId)->first();
            if ($student !== null) {
                $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], $activeTerm);
                if ($assessment !== null && $assessment['status'] !== 'unassessed') {
                    $paymentSchedule = (new PaymentScheduleModel())->withBalances((int) $assessment['assessment_id'], (float) $assessment['amount_paid']);
                }
            }
        }
        $week = StudentDashboardSchedule::summarize($meetings, $now);
        $unpaidInstallments = array_values(array_filter($paymentSchedule, static fn ($row) => (float) $row['balance'] > 0));
        usort($unpaidInstallments, static fn ($a, $b) => strcmp($a['due_date'] ?: '9999-12-31', $b['due_date'] ?: '9999-12-31'));

        return view('student/dashboard', [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'account'    => $account,
            'section'    => $section,
            'activeTerm' => $activeTerm,
            'now' => $now,
            'week' => $week,
            'assessment' => $assessment,
            'nextPayment' => $unpaidInstallments[0] ?? null,
            'publishedGrades' => $publishedGrades,
            'stats'      => [
                'enrolled_subjects' => $enrolledCount,
                'weekly_meetings'   => count($week['meetings']),
            ],
        ]);
    }

    public function profile(): string
    {
        $account = auth()->user();
        $section = $this->mySection();

        return view('student/profile', [
            'pageTitle'  => 'Profile',
            'activePage' => 'profile',
            'account'    => $account,
            'section'    => $section,
        ]);
    }

    public function tuition(): string
    {
        $account = auth()->user();
        $student = (new StudentModel())->where('user_id', (int) auth()->id())->first();

        $assessments = [];
        $payments    = [];
        $itemsByCategory = [];
        $schedule    = [];
        $proofs      = [];

        if ($student !== null) {
            $assessments = (new TuitionAssessmentModel())->historyFor((int) $student['student_id']);
            $payments    = (new StudentPaymentModel())->historyFor((int) $student['student_id']);
            $proofs      = (new PaymentProofModel())->forStudent((int) $student['student_id']);

            if ($assessments !== []) {
                $current = $assessments[0];
                $itemsByCategory = (new AssessmentItemModel())->forAssessment((int) $current['assessment_id']);
                $schedule = (new PaymentScheduleModel())->withBalances((int) $current['assessment_id'], (float) $current['amount_paid']);
            }
        }

        return view('student/tuition', [
            'pageTitle'       => 'Tuition & Fees',
            'activePage'      => 'tuition',
            'account'         => $account,
            'assessments'     => $assessments,
            'itemsByCategory' => $itemsByCategory,
            'schedule'        => $schedule,
            'payments'        => $payments,
            'proofs'          => $proofs,
        ]);
    }

    /**
     * Student uploads proof of payment for their current assessment. File is
     * stored under a randomized name (OWASP File Upload Cheat Sheet — never
     * the original filename), mime+extension validated, size capped; only
     * ever served back through viewProof() below, which checks ownership.
     */
    public function uploadProof()
    {
        $student = (new StudentModel())->where('user_id', (int) auth()->id())->first();

        if ($student === null) {
            return redirect()->to('/student/tuition')->with('errors', ['No student record found.']);
        }

        $assessment = (new TuitionAssessmentModel())->historyFor((int) $student['student_id'])[0] ?? null;

        if ($assessment === null) {
            return redirect()->to('/student/tuition')->with('errors', ['No assessment exists yet to upload a proof against.']);
        }

        $rules = [
            'proof'    => 'uploaded[proof]|max_size[proof,5120]|ext_in[proof,jpg,jpeg,png,pdf]|mime_in[proof,image/jpeg,image/png,application/pdf]',
            'remarks'  => 'required|max_length[500]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/student/tuition')->with('errors', $this->validator->getErrors());
        }

        $file = $this->request->getFile('proof');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/student/tuition')->with('errors', ['Upload failed. Please try again.']);
        }

        $studentId  = (int) $student['student_id'];
        $targetDir  = WRITEPATH . 'uploads/payment_proofs/' . $studentId;
        $storedName = $file->getRandomName();

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $file->move($targetDir, $storedName);

        $scheduleId = $this->request->getPost('schedule_id');

        (new PaymentProofModel())->insert([
            'student_id'         => $studentId,
            'assessment_id'      => (int) $assessment['assessment_id'],
            'schedule_id'        => $scheduleId !== '' && $scheduleId !== null ? (int) $scheduleId : null,
            'stored_filename'    => $storedName,
            'original_filename'  => $file->getClientName(),
            'remarks'            => (string) $this->request->getPost('remarks'),
        ]);

        return redirect()->to('/student/tuition')->with('message', 'Proof of payment submitted for review.');
    }

    /**
     * Streams back an uploaded proof — the student who owns it, or any
     * staff member with fees.view/fees.manage (the payment-proofs review
     * queue links here). Never anyone else's.
     */
    public function viewProof(int $id)
    {
        $proof = (new PaymentProofModel())->find($id);

        if ($proof === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $student  = (new StudentModel())->where('user_id', (int) auth()->id())->first();
        $isOwner  = $student !== null && (int) $proof['student_id'] === (int) $student['student_id'];
        $isStaff  = auth()->user()->can('fees.view') || auth()->user()->can('fees.manage');

        if (! $isOwner && ! $isStaff) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . 'uploads/payment_proofs/' . $proof['student_id'] . '/' . $proof['stored_filename'];

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($path, null)->setFileName($proof['original_filename']);
    }

    public function updateProfile()
    {
        $username = trim((string) $this->request->getPost('username'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        return redirect()->to('/student/profile')->with('profile_message', 'Profile updated.');
    }

    public function updatePassword()
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            return redirect()->back()->with('profile_error', 'All password fields are required.');
        }

        if ($new !== $confirm) {
            return redirect()->back()->with('profile_error', 'New password and confirmation do not match.');
        }

        $account = auth()->user();
        $result  = auth()->check(['email' => $account->getEmail(), 'password' => $current]);

        if (! $result->isOK()) {
            return redirect()->back()->with('profile_error', 'Current password is incorrect.');
        }

        if (mb_strlen($new) < 8) {
            return redirect()->back()->with('profile_error', 'New password must be at least 8 characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->password = $new;
        $users->save($user);

        return redirect()->to('/student/profile')->with('profile_message', 'Password changed.');
    }

    private function mySection(): ?array
    {
        $me = db_connect()->table('users')->select('section_id')->where('id', (int) auth()->id())->get()->getRowArray();

        if (empty($me['section_id'])) {
            return null;
        }

        return (new SectionModel())->find((int) $me['section_id']);
    }
}
