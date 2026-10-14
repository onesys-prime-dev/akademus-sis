<?php

/**
 * Single source of truth for the chairperson portal's sidebar. Every
 * chairperson view used to redefine this array inline, which let newer
 * items (Shared Subject Dashboard/Coverage/Requests) silently drift —
 * present on some pages, missing on others, depending on which view
 * happened to be touched when a feature was added. `require` this file
 * instead of inlining the array so the sidebar can never diverge again.
 *
 * @return array<string, array<string, array{label: string, icon: string, url: string}>>
 */
return [
    '' => [
        'overview'                => ['label' => 'Dashboard',               'icon' => 'home',              'url' => 'chairperson/overview'],
        'schedule'                => ['label' => 'Schedule Plotting',       'icon' => 'calendar_month',     'url' => 'chairperson/dashboard'],
        'curriculum'              => ['label' => 'Curriculum Builder',      'icon' => 'menu_book',          'url' => 'chairperson/curriculum'],
        'subject-ownership'       => ['label' => 'Subject Ownership',       'icon' => 'inventory_2',        'url' => 'chairperson/subject-ownership'],
        'shared-subjects'         => ['label' => 'Shared Subject Dashboard', 'icon' => 'dashboard',         'url' => 'chairperson/shared-subjects'],
        'shared-subject-coverage' => ['label' => 'Shared Subject Coverage', 'icon' => 'rule',               'url' => 'chairperson/shared-subject-coverage'],
        'placement-requests'      => ['label' => 'Shared Subject Requests', 'icon' => 'forward_to_inbox',  'url' => 'chairperson/placement-requests'],
        'offerings'               => ['label' => 'Merged Classes',          'icon' => 'merge_type',         'url' => 'chairperson/offerings'],
        'sections'                => ['label' => 'Sections',                'icon' => 'groups',             'url' => 'chairperson/sections'],
        'student-progress'        => ['label' => 'Student Progress',        'icon' => 'school',              'url' => 'chairperson/student-progress'],
        'grade-approvals'         => ['label' => 'Grade Approvals',         'icon' => 'grade',              'url' => 'chairperson/grade-approvals'],
        'grade-change-requests'   => ['label' => 'Correction Requests',     'icon' => 'history_edu',        'url' => 'chairperson/grade-change-requests'],
        'enlistment-approvals'    => ['label' => 'Enlistment Approvals',    'icon' => 'playlist_add_check', 'url' => 'chairperson/enlistment-approvals'],
        'enlistment-window'       => ['label' => 'Enlistment Window',      'icon' => 'event_available',    'url' => 'chairperson/enlistment-window'],
    ],
];
