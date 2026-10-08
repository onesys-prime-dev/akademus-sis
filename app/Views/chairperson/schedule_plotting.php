<?= $this->extend('layouts/admin_console') ?>

<?php
$activeAdmin   = 'schedule';
$pageTitle     = 'Schedule Plotting';
$brandSub      = 'Chairperson Portal';
$userName      = $user['name'];
$userRoleLabel = $user['role'];
$navGroups     = require __DIR__ . '/_nav.php';
$this->setData(compact('activeAdmin', 'pageTitle', 'brandSub', 'userName', 'userRoleLabel', 'navGroups'));

// Each 30-minute row of the timetable is this tall; the JS reads the same value.
$rowHeight = 32;
$curriculumUrl = site_url('chairperson/curriculum') . '?' . http_build_query($selected);
?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pages/chairperson-plotting.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/pages/chairperson-plotting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="pl-page">

    <header class="pl-head">
        <div class="pl-head__title">
            <h1>Scheduling</h1>
            <p><?= esc($selected['academic_year']) ?></p>
        </div>

        <div class="pl-head__tools">
            <div class="save-state" id="syncStatus" data-state="saved" role="status" aria-live="polite">
                <span class="save-dot" aria-hidden="true"></span>
                <span id="syncText">All changes saved</span>
                <button type="button" class="text-btn" id="syncRetry" hidden>Retry now</button>
            </div>

            <div class="tool-group" role="group" aria-label="Scheduling tools">
                <button type="button" class="btn btn-secondary btn-sm" id="roomOverviewBtn" title="See every room, day by day">
                    <span class="material-symbols-outlined" aria-hidden="true">meeting_room</span>Rooms
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="facultyPanelBtn" title="Faculty load, availability and preferences">
                    <span class="material-symbols-outlined" aria-hidden="true">groups</span>Faculty
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="approvalsBtn" title="Swap approvals waiting for your decision">
                    <span class="material-symbols-outlined" aria-hidden="true">swap_horiz</span>Swaps<span class="count-badge zero" id="approvalBadge">0</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="changeRequestsBtn" title="Faculty change requests waiting for review">
                    <span class="material-symbols-outlined" aria-hidden="true">edit_note</span>Requests<span class="count-badge zero" id="changeRequestBadge">0</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="printScheduleBtn" title="Print this week's schedule">
                    <span class="material-symbols-outlined" aria-hidden="true">print</span>Print
                </button>
                <button type="button" class="btn btn-secondary btn-sm panel-toggle" id="panelToggle" aria-controls="plPanel" aria-expanded="false">
                    <span class="material-symbols-outlined" aria-hidden="true">right_panel_open</span>Details
                </button>
            </div>
        </div>
    </header>

    <form class="pl-context" method="get" action="<?= site_url('chairperson/dashboard') ?>" id="contextForm" data-no-dirty-tracking>
        <?php
        $contextFields = [
            ['ay_label', 'Academic year', 'plotAcademicYear', $filters['academic_year_labels'], $selected['ay_label']],
            ['semester', 'Semester', 'plotSemester', $filters['semester'], $selected['semester']],
            ['program', 'Program', 'plot-program', $filters['program'], $selected['program']],
            ['year_level', 'Year level', 'plot-year_level', $filters['year_level'], $selected['year_level']],
            ['section', 'Section', 'plot-section', $filters['section'], $selected['section']],
        ];
        ?>
        <?php foreach ($contextFields as [$name, $label, $id, $options, $current]): ?>
            <div class="pl-field">
                <label for="<?= esc($id, 'attr') ?>"><?= esc($label) ?></label>
                <select name="<?= esc($name, 'attr') ?>" class="context-select" id="<?= esc($id, 'attr') ?>">
                    <?php foreach ($options as $option): ?>
                        <?php $pendingCount = $name === 'year_level' ? ($year_levels_needing_attention[$option] ?? 0) : 0; ?>
                        <option value="<?= esc($option, 'attr') ?>" <?= $option === $current ? 'selected' : '' ?>><?= esc($option) ?><?= $pendingCount > 0 ? ' — ' . $pendingCount . ' to plot' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    </form>

    <?php if (! empty($selected['missing_term_combo'])): ?>
        <div class="pl-banner" role="alert">
            <b><?= esc($selected['missing_term_combo']) ?></b> doesn't exist yet in Admin &gt; Academic Years, so the page stayed on
            <b><?= esc($selected['academic_year']) ?></b> instead. Add that term there first if you need to plot for it.
        </div>
    <?php endif ?>

    <?php if (! $is_active_year): ?>
        <div class="pl-banner" role="status">
            <b><?= esc($selected['academic_year']) ?></b> is not the active term, so this view is read-only.
            Make it active under Admin &gt; Academic Years, or select the active term above.
        </div>
    <?php endif ?>

    <div class="pl-print-head">
        <div class="pl-print-brand"><strong>Akademus<span> / Academic scheduling</span></strong><span>WEEKLY TIMETABLE</span></div>
        <div class="pl-print-title"><h2>Class schedule</h2><strong>Section <?= esc($selected['section']) ?></strong></div>
        <p>
            <?= esc($selected['program']) ?> · <?= esc($selected['year_level']) ?> · Section <?= esc($selected['section']) ?>
            &nbsp;·&nbsp; <?= esc($selected['academic_year']) ?>
        </p>
        <div class="pl-print-meta"><span id="printScheduleSummary"></span><span id="printScheduleDate"></span></div>
    </div>

    <div class="pl-workspace" id="plottingWorkspace" data-panel="closed" style="--row-h: <?= (int) $rowHeight ?>px; --days: <?= count($week) ?>;">

        <!-- Class queue -->
        <aside class="pl-queue" aria-labelledby="queueTitle">
            <div class="queue-head">
                <div class="queue-head__row">
                    <h2 id="queueTitle">Classes</h2>
                    <button type="button" class="btn btn-ghost btn-sm filter-toggle" id="queueFilterToggle" aria-expanded="false" aria-controls="queueFilters">
                        <span class="material-symbols-outlined" aria-hidden="true">filter_list</span>Filter<span class="count-badge" id="queueFilterCount" hidden>0</span>
                    </button>
                </div>
                <p class="queue-summary" id="queueSummary" aria-live="polite"></p>
                <div class="q-progress q-progress--lg" aria-hidden="true"><i id="queueProgressFill"></i></div>

                <label class="search-field">
                    <span class="sr-only">Search classes, faculty or rooms</span>
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <input type="search" id="queueSearch" placeholder="Search class, faculty or room" autocomplete="off">
                </label>

                <div class="queue-filters" id="queueFilters" hidden>
                    <div class="filter-group">
                        <span class="filter-label" id="queueStatusLabel">Status</span>
                        <div class="chips" id="queueStatusChips" role="group" aria-labelledby="queueStatusLabel"></div>
                    </div>
                    <label class="filter-group">
                        <span class="filter-label">Type</span>
                        <select id="queueType">
                            <option value="all">Lecture and laboratory</option>
                            <option value="lecture">Lecture only</option>
                            <option value="lab">Laboratory only</option>
                        </select>
                    </label>
                    <button type="button" class="text-btn" id="queueClearFilters" hidden>Clear search and filters</button>
                </div>
            </div>

            <?php if ($preset_codes === null): ?>
                <p class="queue-note">No curriculum is set for <?= esc($selected['year_level']) ?>, so the full subject catalog is shown.</p>
            <?php endif; ?>

            <p class="queue-done" id="queueDone" hidden>All classes for this section have been fully scheduled.</p>

            <div class="queue-scroll">
                <ul class="q-list" id="queueList"></ul>
                <div class="empty-note" id="queueEmpty" hidden></div>
            </div>

            <div class="queue-foot">
                <a href="<?= esc($curriculumUrl, 'attr') ?>"><?= $preset_codes === null ? 'Create curriculum' : 'Edit curriculum' ?></a>
            </div>
        </aside>

        <!-- Timetable -->
        <section class="pl-board" aria-labelledby="boardTitle">
            <div class="board-bar">
                <div class="board-bar__main">
                    <h2 id="boardTitle">Week</h2>
                    <span class="board-summary" id="boardSummary"></span>
                    <button type="button" class="issues-btn" id="issuesBtn" hidden></button>
                </div>
                <div class="board-bar__aside">
                    <button type="button" class="btn btn-ghost btn-sm" id="aiUndo" disabled aria-label="Undo last change" title="Nothing to undo">
                        <span class="material-symbols-outlined" aria-hidden="true">undo</span>Undo
                    </button>
                </div>
            </div>

            <div class="noprof-banner" id="noProfBanner" role="status" hidden>
                <span class="noprof-banner__text" id="noProfText"></span>
                <button type="button" class="btn btn-secondary btn-sm" id="noProfAuto">Auto-assign faculty</button>
            </div>

            <div class="board-scroll" id="boardScroll">
                <div class="grid">
                    <div class="head head--corner"></div>
                    <?php foreach ($week as $day): ?>
                        <div class="head"><span class="head__long"><?= esc($day['day']) ?></span><span class="head__short"><?= esc(substr($day['day'], 0, 3)) ?></span></div>
                    <?php endforeach; ?>

                    <div class="time-col" id="timeCol"></div>
                    <?php foreach ($week as $dayIndex => $day): ?>
                        <div class="day-col" data-day="<?= $dayIndex ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="board-foot">
                <ul class="legend" aria-label="Legend">
                    <li><i class="key key--lecture"></i>Lecture</li>
                    <li><i class="key key--lab"></i>Laboratory</li>
                    <li><i class="key key--other"></i>Other sections</li>
                </ul>
            </div>
        </section>

        <!-- Details and assistant -->
        <aside class="pl-panel" id="plPanel" aria-label="Details and assistant">
            <div class="panel-tabs" role="tablist" aria-label="Side panel">
                <button type="button" class="panel-tab" role="tab" id="tab-inspector" data-tab="inspector" aria-controls="panel-inspector" aria-selected="true">
                    Details<span class="tab-count" id="tabInspectorCount" hidden>0</span>
                </button>
                <button type="button" class="panel-tab" role="tab" id="tab-assistant" data-tab="assistant" aria-controls="panel-assistant" aria-selected="false" tabindex="-1">
                    Assistant<span class="tab-count" id="assistantCount" hidden>0</span>
                </button>
                <button type="button" class="icon-btn panel-close" id="panelClose" aria-label="Close side panel">
                    <span class="material-symbols-outlined" aria-hidden="true">right_panel_close</span>
                </button>
            </div>

            <div class="panel-body" id="panel-inspector" role="tabpanel" aria-labelledby="tab-inspector">
                <div class="inspector" id="inspectorBody"></div>
            </div>

            <div class="panel-body" id="panel-assistant" role="tabpanel" aria-labelledby="tab-assistant" hidden>
                <div class="assistant">
                    <div class="assistant__bar">
                        <p class="assistant__intro">Suggested placements that respect your department's rules and everyone's availability.</p>
                        <button type="button" class="btn btn-ghost btn-sm" id="aiRulesBtn" aria-expanded="false" aria-controls="aiRules">
                            <span class="material-symbols-outlined" aria-hidden="true">tune</span>Rules
                        </button>
                    </div>

                <form class="ai-rules" id="aiRules" hidden>
                    <div class="ai-rules-title">Scheduling rules <span>for your department</span></div>

                    <label class="ai-check"><input type="checkbox" name="lunch_enabled"> Keep a lunch break free</label>
                    <div class="ai-row">
                        <label>Lunch from <select name="lunch_start"></select></label>
                        <label>to <select name="lunch_end"></select></label>
                    </div>

                    <div class="ai-row">
                        <label>Earliest class <select name="earliest_start"></select></label>
                        <label>Latest end <select name="latest_end"></select></label>
                    </div>

                    <div class="ai-row">
                        <label>Max class hours per day <select name="max_hours_day"></select></label>
                    </div>

                    <label class="ai-check"><input type="checkbox" name="allow_saturday"> Allow Saturday classes</label>

                    <div class="ai-row">
                        <label>Meeting days
                            <select name="day_pattern">
                                <option value="spaced">Spaced (Mon/Wed, Tue/Thu)</option>
                                <option value="any">Any days</option>
                            </select>
                        </label>
                    </div>

                    <div class="ai-row">
                        <label>Avoid busy periods
                            <select name="spread">
                                <option value="off">Off</option>
                                <option value="balanced">Balanced</option>
                                <option value="strong">Strong</option>
                            </select>
                        </label>
                    </div>

                    <div class="ai-rules-actions">
                        <button type="button" class="btn btn-ghost" id="aiRulesReset">Reset</button>
                        <button type="submit" class="btn btn-primary" id="aiRulesSave">Save rules</button>
                    </div>
                </form>

                    <div class="ai-head">
                        <span class="ai-status" id="aiStatus">Analyzing the schedule…</span>
                        <button type="button" class="btn btn-secondary btn-sm" id="aiFillAll" hidden>Plot everything</button>
                    </div>
                    <div id="aiSuggestions"></div>
                </div>
            </div>
        </aside>
    </div>

    <?= "\n" ?>
    <!-- Swap approvals modal -->
    <div class="modal-backdrop" id="approvalsModal" role="dialog" aria-modal="true" aria-label="Swap Approvals">
        <div class="modal">
            <h3>Swap Approvals</h3>
            <div class="sub">Faculty-agreed swaps in your department, waiting for your decision.
                Loads only transfer once you approve.</div>
            <div class="approvals-list" id="approvalsList"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="approvalsClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Change requests modal -->
    <div class="modal-backdrop" id="changeRequestsModal" role="dialog" aria-modal="true" aria-label="Change Requests">
        <div class="modal">
            <h3>Change Requests</h3>
            <div class="sub">Faculty members who can't accept an official assignment, waiting for your review.
                Deciding does not reassign the subject — replot it yourself via the grid.</div>
            <div class="approvals-list" id="changeRequestsList"></div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="changeRequestsClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Room overview modal -->
    <div class="modal-backdrop" id="overviewModal" role="dialog" aria-modal="true" aria-labelledby="overviewTitle" aria-describedby="overviewDescription">
        <div class="modal overview-modal">
            <div class="ov-head">
                <div>
                    <div class="ov-head__title-row">
                        <h3 id="overviewTitle">Room Overview</h3>
                        <span class="ov-summary-badge" id="overviewSummary"><span class="dot"></span></span>
                    </div>
                    <div class="sub" id="overviewDescription">Every class plotted in a room for a day — all programs, year levels
                        and sections in <?= esc($selected['academic_year']) ?>, live.</div>
                </div>
                <button type="button" class="expand-btn" id="overviewClose" aria-label="Close room overview">&#x2715;</button>
            </div>

            <div class="ov-body">
                <div class="ov-day-row">
                    <div>
                        <div class="ov-label">View day</div>
                        <div class="day-chips" id="overviewDays" role="group" aria-label="Select day"></div>
                    </div>
                    <div class="ov-metrics" aria-live="polite">
                        <div class="ov-metric free"><span class="ov-metric__value" id="overviewMetricFree">0</span><span class="ov-metric__label">Free rooms</span></div>
                        <div class="ov-metric"><span class="ov-metric__value" id="overviewMetricUse">0</span><span class="ov-metric__label">In use</span></div>
                        <div class="ov-metric busy"><span class="ov-metric__value" id="overviewMetricBusy">0</span><span class="ov-metric__label">Busy rooms</span></div>
                    </div>
                </div>

                <div class="ov-workspace">
                    <section class="ov-room-browser" aria-labelledby="overviewRoomsLabel">
                        <div class="ov-room-toolbar">
                            <div class="ov-room-toolbar__top">
                                <div>
                                    <div class="ov-label" id="overviewRoomsLabel">Rooms</div>
                                    <div class="ov-legend">
                                        <span><i class="lg-green"></i> Free</span>
                                        <span><i class="lg-yellow"></i> In use</span>
                                        <span><i class="lg-red"></i> Busy</span>
                                    </div>
                                </div>
                                <label class="ov-search-wrap">
                                    <span class="sr-only">Search rooms</span>
                                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                                    <input class="ov-search" type="search" id="overviewRoomSearch" placeholder="Search rooms…" autocomplete="off">
                                </label>
                            </div>
                            <div class="ov-filters" id="overviewRoomFilters" role="group" aria-label="Filter rooms">
                                <button type="button" class="ov-filter active" data-filter="all" aria-pressed="true">All rooms</button>
                                <button type="button" class="ov-filter" data-filter="free" aria-pressed="false">Free</button>
                                <button type="button" class="ov-filter" data-filter="in-use" aria-pressed="false">In use</button>
                                <button type="button" class="ov-filter" data-filter="labs" aria-pressed="false">Laboratories</button>
                            </div>
                        </div>
                        <div class="ov-room-scroll" id="overviewRoomsWrap"></div>
                    </section>

                    <aside class="ov-schedule-pane" aria-labelledby="overviewListLabel">
                        <div class="ov-schedule-head">
                            <div class="ov-label">Selected room</div>
                            <div class="ov-schedule-title" id="overviewListLabel">Schedule</div>
                            <div class="ov-schedule-meta" id="overviewListMeta"></div>
                        </div>
                        <div class="overview-list" id="overviewList"></div>
                    </aside>
                </div>
            </div>

            <div class="ov-footer">
                <span class="ov-footer__note">Updates automatically from the plotting grid.</span>
                <button type="button" class="btn btn-ghost" id="overviewCloseBottom">Close</button>
            </div>
        </div>
    </div>

    <!-- Faculty context panel modal -->
    <div class="modal-backdrop" id="facultyPanelModal" role="dialog" aria-modal="true" aria-labelledby="facultyPanelTitle" aria-describedby="facultyPanelSub">
        <div class="modal faculty-directory">
            <header class="fd-head">
                <div>
                    <div class="fd-title-row">
                        <h3 id="facultyPanelTitle">Department Faculty</h3>
                        <span class="fd-count" id="facultyPanelCount">Loading…</span>
                    </div>
                    <div class="sub" id="facultyPanelSub">Availability, subject preferences, and current teaching load.</div>
                </div>
                <button type="button" class="fd-close" id="facultyPanelClose" aria-label="Close department faculty">
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
            </header>
            <div class="fd-toolbar">
                <label class="sr-only" for="facultyPanelSearch">Search faculty by name</label>
                <div class="fd-search-wrap">
                    <span class="material-symbols-outlined" aria-hidden="true">search</span>
                    <input class="fd-search" type="search" id="facultyPanelSearch" placeholder="Search faculty by name…" autocomplete="off">
                </div>
                <div class="fd-filters" id="facultyPanelFilters" role="group" aria-label="Filter faculty setup status">
                    <button type="button" class="fd-filter active" data-filter="all" aria-pressed="true">All faculty</button>
                    <button type="button" class="fd-filter" data-filter="ready" aria-pressed="false">Setup complete</button>
                    <button type="button" class="fd-filter" data-filter="needs-setup" aria-pressed="false">Needs setup</button>
                </div>
            </div>
            <div class="fd-metrics" aria-live="polite">
                <div class="fd-metric"><strong id="facultyMetricTotal">—</strong><span>faculty</span></div>
                <div class="fd-metric is-ready"><strong id="facultyMetricAvailable">—</strong><span>with availability</span></div>
                <div class="fd-metric"><strong id="facultyMetricPreferences">—</strong><span>with preferences</span></div>
            </div>
            <div class="fd-list-wrap" id="facultyPanelResults" aria-busy="true">
                <p class="fd-summary" id="facultyPanelSummary" aria-live="polite">Loading faculty…</p>
                <div class="fd-list" id="facultyPanelList" role="list"></div>
            </div>
            <footer class="fd-footer">
                <span id="facultyLoadTerm">Teaching load · Active term</span>
                <button type="button" class="btn btn-ghost" id="facultyPanelCloseBottom">Close</button>
            </footer>
        </div>
    </div>

    <!-- Generic confirmation modal (stacks above the other modals) -->
    <div class="modal-backdrop confirm-top" id="confirmModal" role="dialog" aria-modal="true" aria-label="Confirm">
        <div class="modal confirm-modal">
            <h3 id="confirmTitle"></h3>
            <div class="sub" id="confirmMessage"></div>
            <div class="confirm-reason" id="confirmReasonWrap" hidden>
                <label for="confirmReason">Reason for override <span class="sub">(optional)</span></label>
                <textarea id="confirmReason" maxlength="250" placeholder="Add context for the audit record"></textarea>
            </div>
            <div class="actions">
                <button type="button" class="btn btn-ghost" id="confirmCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmOk">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Room and faculty picker for plotting and editing a class -->
    <div class="modal-backdrop" id="placeModal" role="dialog" aria-modal="true" aria-label="Assign room and faculty">
        <div class="modal place-modal" id="placeBody"></div>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    <div class="sr-only" id="srAnnounce" role="status" aria-live="polite"></div>

    <button type="button" class="debug-toggle" id="debugToggle" aria-expanded="false" aria-controls="debugPanel" hidden>
        Errors <span id="debugCount">0</span>
    </button>
    <div class="debug-panel" id="debugPanel" hidden>
        <div class="debug-panel__head">
            <strong>Error log</strong>
            <button type="button" class="text-btn" id="debugClear">Clear</button>
        </div>
        <div id="debugList"></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // Server state for the workspace. Everything the chairperson does after
    // load goes through the endpoints listed under `urls`.
    window.PLOTTING_BOOT = <?= json_encode([
        'rowHeight'       => $rowHeight,
        'gridStart'       => $grid_start,
        'gridEnd'         => $grid_end,
        'subjects'        => $subjects,
        'rooms'           => $rooms,
        'week'            => $week,
        'context'         => $selected,
        'others'          => array_values($other_blocks),
        'blocks'          => array_values($blocks),
        'assignments'     => array_values($assignments),
        'swapApprovals'   => array_values($swap_approvals),
        'changeRequests'  => array_values($change_requests),
        'urls'            => [
            'syncUrl'            => site_url('chairperson/schedule/sync'),
            'stateUrl'           => site_url('chairperson/schedule/state'),
            'suggestUrl'         => site_url('chairperson/schedule/suggest'),
            'rulesUrl'           => site_url('chairperson/schedule/assistant-rules'),
            'facultyPanelUrl'    => site_url('chairperson/faculty-panel'),
            'facultyOverrideUrl' => site_url('chairperson/schedule/faculty-override'),
            'swapApproveUrl'     => site_url('chairperson/swaps/approve'),
            'swapRejectUrl'      => site_url('chairperson/swaps/reject'),
            'changeApproveUrl'   => site_url('chairperson/change-requests/approve'),
            'changeRejectUrl'    => site_url('chairperson/change-requests/reject'),
        ],
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="<?= base_url('assets/js/chairperson-plotting.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/chairperson-plotting.js') ?>"></script>
<?= $this->endSection() ?>
