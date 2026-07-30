<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\SectionModel;

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

    private function mySection(): ?array
    {
        $me = db_connect()->table('users')->select('section_id')->where('id', (int) auth()->id())->get()->getRowArray();

        if (empty($me['section_id'])) {
            return null;
        }

        return (new SectionModel())->find((int) $me['section_id']);
    }
}
