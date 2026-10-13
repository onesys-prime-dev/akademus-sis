<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'user';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * An associative array of the available groups in the system, where the keys
     * are the group names and the values are arrays of the group info.
     *
     * Whatever value you assign as the key will be used to refer to the group
     * when using functions such as:
     *      $user->addGroup('superadmin');
     *
     * @var array<string, array<string, string>>
     *
     * @see https://codeigniter4.github.io/shield/quick_start_guide/using_authorization/#change-available-groups for more info
     */
    public array $groups = [

        /* ------------------- DEMO EXAMPLES -----------------------
        'developer' => [
            'title'       => 'Developer',
            'description' => 'Site programmers.',
        ],
        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
        'beta' => [
            'title'       => 'Beta User',
            'description' => 'Has access to beta-level features.',
        ],
        */

        // SIS 
        'superadmin' => [
            'title'       => 'Super Admin',
            'description' => 'has access to all',
        ],

        'admin' => [
            'title'       => 'Administrator',
            'description' => 'System Administrator',
        ],

        'registrar' => [
            'title'       => 'Registrar',
            'description' => 'Registrar staff',
        ],

        'faculty' => [
            'title'       => 'Faculty',
            'description' => 'Faculty members',
        ],

        'dean' => [
            'title'       => 'Dean',
            'description' => 'College deans',
        ],

        'chairperson' => [
            'title'       => 'Chairperson',
            'description' => 'Department chairpersons',
        ],

        'student' => [
            'title'       => 'Student',
            'description' => 'Student users',
        ],

        'user' => [
            'title'       => 'User',
            'description' => 'General users of the site. Often customers.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * The available permissions in the system.
     *
     * If a permission is not listed here it cannot be used.
     */
    public array $permissions = [
        'admin.access'        => 'Can access the sites admin area',
        'admin.settings'      => 'Can access the main site settings',
        'users.manage-admins' => 'Can manage other admins',
        'users.create'        => 'Can create new non-admin users',
        'users.edit'          => 'Can edit existing non-admin users',
        'users.delete'        => 'Can delete existing non-admin users',
        'students.view'       => 'Can look up any student record',
        'students.manage'     => "Can change a student's status, curriculum and enrollments (add, drop, prerequisite override)",
        'fees.view'            => 'Can view tuition rates and student fee assessments',
        'fees.manage'          => 'Can set tuition rates and record student payments',
        'grades.manage'        => 'Can submit, recommend, note, approve, or lock grades at any workflow stage',
        'grades.view'          => 'Can view grade records and approval history',
        'calendar.manage'      => 'Can set grading and registration windows on academic terms',
        'calendar.override'    => 'Can submit/enroll outside an open grading or registration window',
        'enrollment.override'  => 'Can override prerequisite or capacity checks when enrolling a student',
        'clearance.manage'     => 'Can initiate, clear, deny, and finalize graduation clearance cases',
        'documents.issue'      => 'Can generate and release official student documents (TOR, certificates)',
        // 'beta.access'        => 'Can access beta-level features',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Maps permissions to groups.
     *
     * This defines group-level permissions.
     */
    public array $matrix = [
        'superadmin' => [
            'admin.*',
            'users.*',
            'students.*',
            'fees.*',
            'grades.*',
            'calendar.*',
            'enrollment.*',
            'clearance.*',
            'documents.*',
        ],
        'admin' => [
            'admin.access',
            'users.create',
            'users.edit',
            'users.delete',
            'students.view',
            'students.manage',
            'fees.view',
            'fees.manage',
        ],
        // Institution-wide by design: a registrar looks up and corrects any
        // student's record (audited, reason required), but administers nothing else.
        'registrar' => [
            'students.view',
            'students.manage',
            'fees.view',
            'fees.manage',
            'grades.manage',
            'grades.view',
            'calendar.manage',
            'calendar.override',
            'enrollment.override',
            'clearance.manage',
            'documents.issue',
        ],
        'faculty' => [
            'grades.manage',
            'grades.view',
        ],
        'dean' => [
            'grades.manage',
            'grades.view',
        ],
        'chairperson' => [
            'grades.manage',
            'grades.view',
        ],
        'student' => [],
        'user' => [],
    ];
}
