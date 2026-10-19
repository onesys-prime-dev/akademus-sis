<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\AssessmentItemModel;
use App\Models\AcademicYearModel;
use App\Libraries\StudentDashboardSchedule;
use App\Models\OfficialReceiptModel;
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
            // Sections are year-scoped (no semester identity of their own
            // anymore) — enrollments.academic_year is the term-scoped
            // identity to filter/join on, not sections.academic_year.
            $enrolledCount = db_connect()->table('enrollments e')
                ->where('e.user_id', $userId)->where('e.status', 'enrolled')
                ->where('e.academic_year', $activeTerm)->countAllResults();
            $meetings = db_connect()->table('enrollments e')
                ->select('p.day, p.start_time, p.end_time, p.subject_code, p.subject_title, p.room, p.prof, s.section, s.program, s.year_level')
                ->join('sections s', 's.section_id = e.section_id')
                ->join('plotted_schedules p', 'p.academic_year = e.academic_year AND p.program = s.program AND p.year_level = s.year_level AND p.section = s.section AND p.subject_code = e.subject_code')
                ->where('e.user_id', $userId)->where('e.status', 'enrolled')
                ->where('e.academic_year', $activeTerm)
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

        $student       = (new StudentModel())->where('user_id', (int) auth()->id())->first();
        $studentDetail = $student !== null ? (new StudentModel())->findDetailed((int) $student['student_id']) : null;

        return view('student/profile', [
            'pageTitle'     => 'Profile',
            'activePage'    => 'profile',
            'account'       => $account,
            'section'       => $section,
            'studentDetail' => $studentDetail,
        ]);
    }

    /**
     * Uploads (replacing any existing one) the student's own profile photo —
     * the bytes the browser's crop/zoom step already squared off (see
     * profile.php's crop modal) go straight into users.photo_blob/
     * photo_mime, never a file on disk, per explicit request. Still
     * mime/extension-validated and size-capped the same way
     * uploadProof()/the old file-based version were.
     */
    public function uploadPhoto()
    {
        $userId = (int) auth()->id();

        $rules = [
            'photo' => 'uploaded[photo]|max_size[photo,2048]|ext_in[photo,jpg,jpeg,png,webp]|mime_in[photo,image/jpeg,image/png,image/webp]|is_image[photo]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/student/profile')->with('profile_error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('photo');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/student/profile')->with('profile_error', 'Upload failed. Please try again.');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'photo_blob' => file_get_contents($file->getTempName()),
            'photo_mime' => $file->getClientMimeType(),
        ]);

        return redirect()->to('/student/profile')->with('profile_message', 'Profile photo updated.');
    }

    /**
     * Removes the student's own profile photo, reverting to the letter
     * avatar.
     */
    public function removePhoto()
    {
        db_connect()->table('users')->where('id', (int) auth()->id())->update([
            'photo_blob' => null,
            'photo_mime' => null,
        ]);

        return redirect()->to('/student/profile')->with('profile_message', 'Profile photo removed.');
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

        if ($this->request->getGet('download') !== null) {
            return $this->response->download($path, null)->setFileName($proof['original_filename']);
        }

        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
        $ext   = strtolower(pathinfo($proof['stored_filename'], PATHINFO_EXTENSION));
        $mime  = $mimes[$ext] ?? 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . $proof['original_filename'] . '"')
            ->setBody(file_get_contents($path));
    }

    public function viewReceipt(int $id)
    {
        $receipt = (new OfficialReceiptModel())->find($id);

        if ($receipt === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $student  = (new StudentModel())->where('user_id', (int) auth()->id())->first();
        $isOwner  = $student !== null && (int) $receipt['student_id'] === (int) $student['student_id'];
        $isStaff  = auth()->user()->can('fees.view') || auth()->user()->can('fees.manage');

        if (! $isOwner && ! $isStaff) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = (new OfficialReceiptModel())->filePath($receipt);

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($this->request->getGet('download') !== null) {
            return $this->response->download($path, null)->setFileName($receipt['or_number'] . '.pdf');
        }

        $disposition = 'inline; filename="' . $receipt['or_number'] . '.pdf"';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition)
            ->setBody(file_get_contents($path));
    }

    public function updateProfile()
    {
        $username      = trim((string) $this->request->getPost('username'));
        $firstName     = trim((string) $this->request->getPost('first_name'));
        $middleName    = trim((string) $this->request->getPost('middle_name'));
        $lastName      = trim((string) $this->request->getPost('last_name'));
        $contactNumber = trim((string) $this->request->getPost('contact_number'));

        $dateOfBirth  = trim((string) $this->request->getPost('date_of_birth'));
        $gender       = trim((string) $this->request->getPost('gender'));
        $homeAddress  = trim((string) $this->request->getPost('home_address'));
        $city         = trim((string) $this->request->getPost('city'));
        $emName       = trim((string) $this->request->getPost('emergency_contact_name'));
        $emNumber     = trim((string) $this->request->getPost('emergency_contact_number'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        if (mb_strlen($firstName) > 100 || mb_strlen($middleName) > 100 || mb_strlen($lastName) > 100 || mb_strlen($contactNumber) > 30) {
            return redirect()->back()->with('profile_error', 'One of the fields is too long.');
        }

        if ($dateOfBirth !== '' && \DateTime::createFromFormat('Y-m-d', $dateOfBirth) === false) {
            return redirect()->back()->with('profile_error', 'Date of birth is invalid.');
        }

        if (mb_strlen($gender) > 20 || mb_strlen($homeAddress) > 255 || mb_strlen($city) > 100
            || mb_strlen($emName) > 150 || mb_strlen($emNumber) > 20) {
            return redirect()->back()->with('profile_error', 'One of the fields is too long.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        // first_name/middle_name/last_name/contact_number aren't part of
        // Shield's own User entity (its $allowedFields is limited to
        // username/status/active/last_active — anything else is silently
        // dropped on save()), so they're written directly here, the same
        // convention used everywhere else this app touches a custom users
        // column (see StudentModel::promoteFromApplicant()).
        db_connect()->table('users')->where('id', $user->id)->update([
            'first_name'     => $firstName !== '' ? $firstName : null,
            'middle_name'    => $middleName !== '' ? $middleName : null,
            'last_name'      => $lastName !== '' ? $lastName : null,
            'contact_number' => $contactNumber !== '' ? $contactNumber : null,
        ]);

        $student = (new StudentModel())->where('user_id', $user->id)->first();

        if ($student !== null) {
            (new StudentModel())->update((int) $student['student_id'], [
                'date_of_birth'            => $dateOfBirth !== '' ? $dateOfBirth : null,
                'gender'                   => $gender !== '' ? $gender : null,
                'home_address'             => $homeAddress !== '' ? $homeAddress : null,
                'city'                     => $city !== '' ? $city : null,
                'emergency_contact_name'   => $emName !== '' ? $emName : null,
                'emergency_contact_number' => $emNumber !== '' ? $emNumber : null,
            ]);
        }

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
