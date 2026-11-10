# Sections Export/Import — Design

**Status:** Draft for review
**Owner request:** Chairperson Portal → Sections page needs a way to export and re-import section + roster data, primarily to roll a term's section setup forward into the next term.

## Problem

Today a chairperson who wants to recreate a term's section layout (same programs, year levels, section names, capacities) for a new term, or seed section/roster data from a spreadsheet the registrar already has, must create every section and assign every student one at a time through the UI. There is no export of current state and no bulk-import path anywhere in the codebase.

## Scope

In scope:
- Export the chairperson's own authorized sections (and their rosters) for a chosen term to a single `.xlsx` workbook.
- Import an `.xlsx` workbook of the same shape to bulk-create sections and bulk-assign students into a chosen target term.

Out of scope (confirmed with the user):
- Schedules, faculty assignments, rooms, merged offerings, or class components — sections and rosters only.
- Any cross-department behavior beyond what the chairperson is already authorized to manage.

## Decisions made with the user

| Question | Decision |
|---|---|
| What data moves | Sections (program, year level, section name, capacity, status) + roster (which students are in which section) |
| Primary use case | Rolling a term's sections forward into a new term |
| File format | Single `.xlsx` workbook, two sheets ("Sections", "Roster") |
| Student identifier on import | Student number (e.g. `SN2026001`) |
| Target term on import | Picked once on the import form; any term values in the file are ignored |
| Conflict handling | A section that already exists in the target term is skipped (not overwritten) and reported; its roster rows still get processed |

## Dependency

PHP has no built-in `.xlsx` reader/writer. This adds **`phpoffice/phpspreadsheet`** as a new Composer dependency — the standard library for this in the PHP ecosystem, actively maintained, already a transitive-safe choice (no native extensions beyond what's commonly enabled: `zip`, `gd`/`xml`, which XAMPP ships with by default). No alternative library is proposed; hand-rolling `.xlsx` via raw `ZipArchive` + XML was considered and rejected as needless maintenance burden for a solved problem.

## Data model

No schema changes. Export reads from `sections` and `users` (student roster via `users.section_id`) exactly as `ChairpersonController::sections()` already does. Import writes through the *same* model/business-logic calls the UI uses — it does not duplicate validation rules in a second code path.

## Export

**Route:** `GET chairperson/sections/export?academic_year=<term>`

**Controller:** new `ChairpersonController::exportSections()`

Behavior:
1. Resolve the chairperson's authorized sections for the given term (reuse the existing `SectionModel::forSubDepartment()` + roster-loading logic already in `sections()`).
2. Build a `PhpSpreadsheet` with two sheets:
   - **Sections**: columns `Program | Year Level | Section | Capacity | Status` — one row per section. `Capacity` blank means unlimited (matches the UI's own convention).
   - **Roster**: columns `Program | Year Level | Section | Student Number | Student Name` — one row per assigned student, blank sheet body (header only) if no one is assigned anywhere yet.
3. Stream the workbook as a download (`Sections_<dept>_<term>.xlsx`), reusing the department-name resolution already in `sections()`.

No new authorization surface: if the chairperson has no department/no authorized sections, the export is a two-sheet file with headers only, same as the page's own empty state.

## Import

**Route:** `POST chairperson/sections/import`

**Controller:** new `ChairpersonController::importSections()`

**Request:** multipart form — the uploaded file plus `academic_year` (the target term, required, must be one of `creatableSectionYears()` — the same whitelist `createSection()` already enforces).

Processing, inside one DB transaction per the existing pattern elsewhere in this controller:

1. Reject early (no rows touched) if: no file, wrong extension/mime, file unreadable as `.xlsx`, missing `academic_year`, or `academic_year` not in `creatableSectionYears()`.
2. Parse the **Sections** sheet. For each row (1-indexed, header excluded):
   - Validate exactly like `createSection()` does today: program must be in `authorizedPrograms()`, year level must be in `self::FILTERS['year_level']`, section name must match `/^[A-Za-z0-9][A-Za-z0-9 _-]{0,19}$/`, capacity must be blank or a positive integer.
   - A validation failure → row recorded as an **error** (row number + reason), nothing written for that row, processing continues to the next row.
   - If `SectionModel::forContext()` finds an existing section for (target term, program, year level, section name) → recorded as **skipped (already exists)**; its roster rows are still eligible to process below.
   - Otherwise → insert via the same fields `createSection()` inserts (`origin: 'manual'`), recorded as **created**.
3. Parse the **Roster** sheet. For each row:
   - Resolve the target section: must match a (program, year level, section name) that now exists in the target term (either just-created or pre-existing/skipped) — otherwise **error** ("section not found for this term").
   - Resolve the student by student number (`students.student_number` joined to `users`) — not found → **error**.
   - Call the same `SectionBalancer::assignStudent()` the "Add a student" control on the page calls (actor id = the importing chairperson, source `'chairperson'`), which already enforces capacity/override rules — its own `ok:false` becomes an **error** row with its message; `over_capacity:true` is still a success but flagged in the report.
4. Commit. Return a JSON result the page renders as a summary panel: counts of created/skipped/error for both sheets, plus the full list of error rows with their reasons (never just a total — the chairperson needs to know *which* rows to fix in the source file and re-upload).

No partial-success ambiguity: everything that validated gets written; everything that didn't is named individually. A second import of the same file after fixing only the bad rows is safe (already-created sections are skipped, already-assigned students are a no-op inside `assignStudent()`'s own idempotency).

## UI

On the Sections page header, next to "Create section": an "Export" button (hits the export route directly, downloads) and an "Import" button that opens a small modal/details panel — term picker + file input + submit — mirroring the existing "Create a section" collapsible panel's visual style (now the "soft & spacious" redesign). After a successful import POST, the summary renders inline in that same panel (not a separate page), and the section directory below refreshes to show the newly created sections without a full page reload (reuse the existing `reloadWithMessage` pattern already used elsewhere on this page, or a lighter in-place re-fetch — implementation detail for the plan).

## Error handling / edge cases

- Empty file / wrong shape (missing expected headers): reject whole import with one clear error, nothing written.
- Capacity column containing text, negative numbers, zero: per-row validation error, same message `createSection()` already gives.
- Duplicate section rows *within the same uploaded file*: second occurrence treated as "already exists" (skipped), since the first occurrence creates it mid-processing.
- Student already assigned to a *different* section: `SectionBalancer::assignStudent()`'s existing behavior governs this (whatever it does today for a UI-driven reassignment) — no new behavior invented here.
- File size: cap at a reasonable limit (e.g. 5 MB) at the controller/validation level to avoid a pathological upload; exact limit is an implementation detail for the plan, not a design decision.

## Testing

- Unit/feature tests mirroring the existing `ChairpersonController` test style (`tests/database/...`): export produces the expected two-sheet structure for a seeded department; import creates sections and assigns rosters correctly; import reports (not silently drops) each class of per-row error; import respects `creatableSectionYears()` and `authorizedPrograms()` the same way `createSection()`'s own tests already verify; round-trip test (export this term → import into a new term) produces an equivalent section/roster set.

## Out of scope / explicitly deferred

- CSV format (xlsx only, per decision above).
- Exporting/importing schedules, faculty, rooms, merges, or components.
- Any admin-level (cross-chairperson) export/import — this is scoped to the authenticated chairperson's own authorized departments, same as the rest of this page.
