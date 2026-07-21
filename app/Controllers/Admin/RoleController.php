<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class RoleController extends BaseController
{
    public function index()
    {
        $roleModel = [
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
                'role'  => 'Admin',
                'color' => '#5b21b6',
                'bg'    => '#ede9fe',
                'perms' => [
                    'Create departments & assign chairpersons',
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
