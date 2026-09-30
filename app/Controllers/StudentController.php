<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;

class StudentController extends BaseController
{
    public function index(): string
    {
        $account = auth()->user();
        $section = $this->mySection();

        $enrolledCount = 0;
        $meetingCount  = 0;

        if ($section !== null) {
            $enrolledCount = db_connect()->table('enrollments')
                ->where('user_id', (int) auth()->id())
                ->where('section_id', $section['section_id'])
                ->where('status', 'enrolled')
                ->countAllResults();

            $meetingCount = db_connect()->table('plotted_schedules')
                ->where('academic_year', $section['academic_year'])
                ->where('program', $section['program'])
                ->where('year_level', $section['year_level'])
                ->where('section', $section['section'])
                ->countAllResults();
        }

        return view('student/dashboard', [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'account'    => $account,
            'section'    => $section,
            'stats'      => [
                'enrolled_subjects' => $enrolledCount,
                'weekly_meetings'   => $meetingCount,
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

        if ($student !== null) {
            $assessments = (new TuitionAssessmentModel())->historyFor((int) $student['student_id']);
            $payments    = (new StudentPaymentModel())->historyFor((int) $student['student_id']);
        }

        return view('student/tuition', [
            'pageTitle'   => 'Tuition & Fees',
            'activePage'  => 'tuition',
            'account'     => $account,
            'assessments' => $assessments,
            'payments'    => $payments,
        ]);
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
