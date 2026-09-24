<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class RoleController extends BaseController
{
    public function index()
    {
        $roleModel = [
            [
                'role'  => 'Dean',
                'color' => '#6d28d9',
                'bg'    => '#ede9fe',
                'perms' => [
                    'Oversee a college (departments, faculty, students)',
                    'Assign / reassign department chairpersons',
                    'Add departments under their college',
                    'Edit college details',
                ],
            ],
            [
                'role'  => 'Chairperson',
                'color' => '#1d4ed8',
                'bg'    => '#e8eeff',
                'perms' => [
                    'Create / clone schedules & presets',
                    'Manage curriculum subjects',
                    'Add & manage faculty',
                    'Direct-assign & override loads',
                    'Approve swaps',
                ],
            ],
            [
                'role'  => 'Faculty',
                'color' => '#059669',
                'bg'    => '#e6f7ef',
                'perms' => [
                    'View department loads',
                    'Claim teaching loads',
                    'Request / accept swaps',
                    'View own schedule',
                ],
            ],
            [
                'role'  => 'Student',
                'color' => '#d97706',
                'bg'    => '#fff2db',
                'perms' => [
                    'View finalized schedule (read-only)',
                    'Export / print schedule',
                ],
            ],
            [
                'role'  => 'Registrar',
                'color' => '#0e7490',
                'bg'    => '#e0f2fe',
                'perms' => [
                    'Look up any student record (institution-wide)',
                    'Change a student\'s academic status and curriculum (reason required, audited)',
                    'Enroll or drop a student on their behalf, with prerequisite override (reason required, audited)',
                    'No access to colleges, rooms, accounts or other admin settings',
                ],
            ],
            [
                'role'  => 'Admin',
                'color' => '#5b21b6',
                'bg'    => '#ede9fe',
                'perms' => [
                    'Create colleges & departments, assign deans/chairpersons',
                    'Manage rooms, floors & academic years',
                    'Create & activate user accounts of any role',
                    'View institution-wide dashboard',
                ],
            ],
        ];

        return view('admin/roles/index', [
            'pageTitle'   => 'Roles',
            'activeAdmin' => 'roles',
            'roleModel'   => $roleModel,
        ]);
    }
}
