<?php

namespace App\Controllers;

use App\Libraries\RegistrarPortal;
use App\Models\StudentModel;

class RegistrarController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $byStatus = array_fill_keys(StudentModel::STATUSES, 0);
        foreach ($db->table('students')->select('student_status, COUNT(*) AS n')->groupBy('student_status')->get()->getResultArray() as $row) {
            $byStatus[$row['student_status']] = (int) $row['n'];
        }

        $stats = [
            'students'           => array_sum($byStatus),
            'by_status'          => $byStatus,
            'active_enrollments' => $db->table('enrollments')->where('status', 'active')->countAllResults(),
            // Active students with no program or no curriculum version cannot be
            // enrolled or graded against a curriculum — the registrar's to-do list.
            'no_curriculum'      => $db->table('students')
                ->where('student_status', 'active')
                ->groupStart()->where('curriculum_id', null)->orWhere('program_id', null)->groupEnd()
                ->countAllResults(),
        ];

        $statusChanges = $db->table('student_status_logs l')
            ->select('l.from_status, l.to_status, l.reason, l.created_at, s.student_id, s.student_number, u.first_name, u.last_name')
            ->join('students s', 's.student_id = l.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->orderBy('l.created_at', 'DESC')
            ->limit(6)
            ->get()->getResultArray();

        $enrollmentChanges = $db->table('enrollment_logs el')
            ->select('el.from_status, el.to_status, el.reason, el.created_at, e.subject_code, s.student_id, s.student_number, u.first_name, u.last_name')
            ->join('enrollments e', 'e.enrollment_id = el.enrollment_id')
            ->join('students s', 's.user_id = e.user_id')
            ->join('users u', 'u.id = e.user_id')
            ->orderBy('el.created_at', 'DESC')
            ->limit(6)
            ->get()->getResultArray();

        return view('registrar/dashboard', [
            'pageTitle'         => 'Registrar Dashboard',
            'stats'             => $stats,
            'statusChanges'     => $statusChanges,
            'enrollmentChanges' => $enrollmentChanges,
        ] + RegistrarPortal::shell('dashboard'));
    }
}
