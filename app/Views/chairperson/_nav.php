<?php

use App\Libraries\ChairpersonBadgeCounts;

/**
 * Single source of truth for the chairperson portal's sidebar. Every
 * chairperson view used to redefine this array inline, which let newer
 * items (Shared Subject Dashboard/Coverage/Requests) silently drift —
 * present on some pages, missing on others, depending on which view
 * happened to be touched when a feature was added. `require` this file
 * instead of inlining the array so the sidebar can never diverge again.
 *
 * Live badge counts (ChairpersonBadgeCounts — the same source the
 * Dashboard's "Attention Needed" panel reads) are merged in below so every
 * page shows the same "how many need me right now" numbers, not just the
 * Dashboard.
 *
 * @return array<string, array<string, array{label: string, icon: string, url: string, badge?: int}>>
 */
$badges = auth()->loggedIn() ? (new ChairpersonBadgeCounts())->forUser((int) auth()->id()) : [];

return [
    '' => [
        'overview'                => ['label' => 'Dashboard',               'icon' => 'home',              'url' => 'chairperson/overview', 'help' => [
            'title' => 'Chairperson Dashboard',
            'text'  => "A quick status check for your department: how many classes are plotted, how many swap approvals and shared-subject assignments are waiting on you, and your curriculum publishing progress.\n\nThe \"Academic Programs & Sections to Plot\" panel lists every section still missing a scheduled subject — open one to jump straight into plotting it.",
        ]],
        'schedule'                => ['label' => 'Schedule Plotting',       'icon' => 'calendar_month',     'url' => 'chairperson/dashboard', 'badge' => $badges['schedule'] ?? 0, 'help' => [
            'title' => 'Schedule Plotting',
            'text'  => "Build each section's weekly timetable: assign faculty, rooms, and meeting times to every subject it needs this term.\n\nFaculty swap requests between colleagues also land here for your approval before loads actually transfer.",
        ]],
        'curriculum'              => ['label' => 'Curriculum Builder',      'icon' => 'menu_book',          'url' => 'chairperson/curriculum', 'help' => [
            'title' => 'Curriculum Builder',
            'text'  => "Define which subjects belong to each year/term of your programs' curricula. This is what Enlistment, prerequisite checks, and Student Progress all read from — a subject missing here won't show up as something a student can take.",
        ]],
        'subject-ownership'       => ['label' => 'Subject Ownership',       'icon' => 'inventory_2',        'url' => 'chairperson/subject-ownership', 'help' => [
            'title' => 'Subject Ownership',
            'text'  => "The subjects your department owns and is responsible for scheduling — including ones other departments' students also need (see Shared Subject Dashboard). Ownership here determines who gets to plot an offering and approve shared-subject requests for a given subject.",
        ]],
        'shared-subjects'         => ['label' => 'Shared Subject Dashboard', 'icon' => 'dashboard',         'url' => 'chairperson/shared-subjects', 'badge' => $badges['shared-subjects'] ?? 0, 'help' => [
            'title' => 'Shared Subject Dashboard',
            'text'  => "For every subject your department owns: how many students need it this term (demand), whether it's actually scheduled yet (offerings), and any pending cross-department requests for it.\n\nUse this to spot subjects with unassigned demand before students get stuck without a class to enroll in.",
        ]],
        'shared-subject-coverage' => ['label' => 'Shared Subject Coverage', 'icon' => 'rule',               'url' => 'chairperson/shared-subject-coverage', 'help' => [
            'title' => 'Shared Subject Coverage',
            'text'  => "The flip side of the Shared Subject Dashboard: subjects your own students need that ANOTHER department teaches. Shows whether an existing offering covers them, and lets you request placement, ask for an extra offering, or report a schedule conflict to the owning department.",
        ]],
        'placement-requests'      => ['label' => 'Shared Subject Requests', 'icon' => 'forward_to_inbox',  'url' => 'chairperson/placement-requests', 'badge' => $badges['placement-requests'] ?? 0, 'help' => [
            'title' => 'Shared Subject Requests',
            'text'  => "Two lists: requests you've sent to other departments for subjects they own, and requests sent to you for subjects your department owns. A request never gives the requesting department write access to your offering — you decide, and fulfilling a placement is a deliberate action here, never automatic.",
        ]],
        'offerings'               => ['label' => 'Merged Classes',          'icon' => 'merge_type',         'url' => 'chairperson/offerings', 'help' => [
            'title' => 'Merged Classes',
            'text'  => "Combine two or more of your own sections that need the same subject into one shared class — one roster, one capacity, one schedule — instead of running several tiny separate sections.\n\n\"Needs Plotting\" shows subjects with demand but no offering yet; \"Mergeable Subjects\" shows sections you can combine; \"Merged Classes\" is where you manage the combined class's schedule.",
        ]],
        'sections'                => ['label' => 'Sections',                'icon' => 'groups',             'url' => 'chairperson/sections', 'help' => [
            'title' => 'Sections',
            'text'  => 'Create and manage the sections (e.g. "BSIT 1st Year — IT1") your department\'s students are grouped into for this term. A section must exist here before you can plot a schedule for it.',
        ]],
        'student-progress'        => ['label' => 'Student Progress',        'icon' => 'school',              'url' => 'chairperson/student-progress', 'help' => [
            'title' => 'Student Progress',
            'text'  => "Look up any student in your department to see their enrollment history, grades, and curriculum progress at a glance — useful for advising or checking whether someone is on track to graduate. Grant an enlistment override here to clear a student to enlist in a subject they're otherwise blocked from (e.g. an irregular/catch-up case) — audit-logged, with the student notified.",
        ]],
        'grade-approvals'         => ['label' => 'Grade Approvals',         'icon' => 'grade',              'url' => 'chairperson/grade-approvals', 'badge' => $badges['grade-approvals'] ?? 0, 'help' => [
            'title' => 'Grade Approvals',
            'text'  => "Review grades faculty have submitted for your department's classes before they move on to the dean, then the registrar. \"Recommended\" classes have already passed your review; \"Corrections\" are requests to change a grade that's already locked.",
        ]],
        'grade-change-requests'   => ['label' => 'Correction Requests',     'icon' => 'history_edu',        'url' => 'chairperson/grade-change-requests', 'badge' => $badges['grade-change-requests'] ?? 0, 'help' => [
            'title' => 'Correction Requests',
            'text'  => "Faculty requests to change a grade that's already locked and official. Your recommendation is the first of three sign-offs (chair → dean → registrar) before a correction actually applies.",
        ]],
        'enlistment-approvals'    => ['label' => 'Enlistment Approvals',    'icon' => 'playlist_add_check', 'url' => 'chairperson/enlistment-approvals', 'badge' => $badges['enlistment-approvals'] ?? 0, 'help' => [
            'title' => 'Enlistment Approvals',
            'text'  => "Students submit their enlistments here for your review before the dean, then the registrar, finalize enrollment. You can recommend an enlistment forward or return it to the student with a reason for revision.",
        ]],
        'enlistment-window'       => ['label' => 'Enlistment Window',      'icon' => 'event_available',    'url' => 'chairperson/enlistment-window', 'help' => [
            'title' => 'Enlistment Window',
            'text'  => "Open or close the period during which your department's students can pre-register for the upcoming term, and set the per-student unit cap. Outside this window, students fall back to direct self-enrollment.",
        ]],
    ],
];
