<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\DepartmentModel;
use App\Models\ProgramModel;
use App\Models\RoomModel;

class AdminController extends BaseController
{
    public function index()
    {
        $db = db_connect();

        $departmentModel = new DepartmentModel();
        $programModel    = new ProgramModel();
        $roomModel       = new RoomModel();
        $ayModel         = new AcademicYearModel();

        $departments = $departmentModel->findAll();

        $chairedDeptIds = $db->table('users u')
            ->select('u.department_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.department_id IS NOT NULL')
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        $chaired    = count(array_unique(array_column($chairedDeptIds, 'department_id')));
        $unassigned = count($departments) - $chaired;

        $students = $db->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'student')
            ->where('u.deleted_at', null)
            ->countAllResults();

        $faculty = $db->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.deleted_at', null)
            ->countAllResults();

        $facultyInvited = $db->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.active', 0)
            ->where('u.deleted_at', null)
            ->countAllResults();

        $stats = [
            'departments'    => count($departments),
            'programs'       => $programModel->countAll(),
            'chaired'        => $chaired,
            'unassigned'     => $unassigned,
            'rooms'          => $roomModel->countAll(),
            'accounts'       => $db->table('users')->where('deleted_at', null)->countAllResults(),
            'students'       => $students,
            'faculty'        => $faculty,
            'faculty_invited' => $facultyInvited,
            'active_ay'      => $ayModel->where('is_active', 1)->first(),
        ];

        $stats['active_ay_formatted'] = $stats['active_ay']
            ? AcademicYearModel::format($stats['active_ay']['label'], $stats['active_ay']['semester'])
            : null;

        $setupPercent = count($departments) > 0
            ? (int) round(($chaired / count($departments)) * 100)
            : 100;

        return view('admin/dashboard', [
            'pageTitle'    => 'Admin Overview',
            'activeAdmin'  => 'dashboard',
            'stats'        => $stats,
            'setupPercent' => $setupPercent,
            'activity'     => $this->recentActivity($db),
        ]);
    }

    /**
     * Merges the most recent rows across departments/rooms/academic years/accounts
     * into a single "what changed recently" feed, using each table's own timestamps.
     */
    private function recentActivity($db): array
    {
        $items = [];

        foreach ($db->table('departments')->orderBy('created_at', 'desc')->limit(5)->get()->getResultArray() as $row) {
            $items[] = [
                'icon'    => 'account_balance',
                'title'   => 'Department Created',
                'detail'  => "{$row['code']} — {$row['name']}",
                'time'    => $row['created_at'],
            ];
        }

        foreach ($db->table('sub_departments')->orderBy('created_at', 'desc')->limit(5)->get()->getResultArray() as $row) {
            $items[] = [
                'icon'    => 'hub',
                'title'   => 'Department Added',
                'detail'  => "{$row['code']} — {$row['name']}",
                'time'    => $row['created_at'],
            ];
        }

        foreach ($db->table('rooms')->orderBy('created_at', 'desc')->limit(5)->get()->getResultArray() as $row) {
            $items[] = [
                'icon'   => 'meeting_room',
                'title'  => 'Room Added',
                'detail' => "{$row['name']} — {$row['floor']} Floor",
                'time'   => $row['created_at'],
            ];
        }

        foreach ($db->table('academic_years')->orderBy('created_at', 'desc')->limit(5)->get()->getResultArray() as $row) {
            $items[] = [
                'icon'   => 'event',
                'title'  => 'Academic Year Created',
                'detail' => AcademicYearModel::format($row['label'], $row['semester']),
                'time'   => $row['created_at'],
            ];
        }

        $accounts = $db->table('users u')
            ->select('u.username, u.created_at, g.group')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('u.deleted_at', null)
            ->orderBy('u.created_at', 'desc')
            ->limit(5)
            ->get()
            ->getResultArray();

        foreach ($accounts as $row) {
            $items[] = [
                'icon'   => 'person_add',
                'title'  => 'Account Created',
                'detail' => "{$row['username']} (" . ucfirst($row['group']) . ')',
                'time'   => $row['created_at'],
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($b['time'] ?? '', $a['time'] ?? ''));

        return array_slice($items, 0, 5);
    }
}
