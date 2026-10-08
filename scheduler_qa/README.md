# Scheduler QA Kit

Test harness for stress-testing a university course-timetabling algorithm:
a synthetic dataset, a reference solver, a strict validator, and an export
pipeline for SIS ingestion / visualization.

## Files

| File                    | Role                                                                 |
|--------------------------|-----------------------------------------------------------------------|
| `dataset.json`           | The problem: rooms, professors, courses, timeslots (deliberately bottlenecked). |
| `schedule.json`          | The answer: your algorithm's placement of every course session. Not checked in — regenerated each run. |
| `solver.py`              | Reference solver (backtracking CSP) — produces a valid `schedule.json` from `dataset.json`. Swap this out for your own algorithm. |
| `validate_schedule.py`   | Strict checker — fails (exit code 1) on any hard-constraint violation, scores soft constraints 0-100. |
| `exporter.py`            | Converts a validated `schedule.json` into `master_schedule.csv` and `timetable.html`. |
| `master_schedule.csv`    | Flat, DB-ready export of the schedule (generated). |
| `timetable.html`         | Visual weekly grid of the schedule, color-coded by department (generated). |

## How to run the pipeline

Open a terminal in this folder (`scheduler_qa/`) and run each step in order.

### 1. Generate a schedule

Using the reference solver (optional — skip this if you're testing your
own algorithm instead):

```bash
python solver.py dataset.json schedule.json
```

If you're testing **your own** algorithm: have it read `dataset.json` and
write its output to `schedule.json` in this same folder, in the format
documented at the top of `validate_schedule.py` (a JSON array of
`{course_id, day, start_time, room_id}` entries — one per required
session).

### 2. Validate the schedule

```bash
python validate_schedule.py dataset.json schedule.json
```

- **Exit code 0** → all hard constraints pass (room/professor overlap,
  capacity, room type, professor availability, corequisite overlap).
  A soft score (0-100) is also printed — lower means more small-class/
  oversized-room and corequisite-spacing penalties.
- **Exit code 1** → read the printed violation list, fix your algorithm,
  and re-run steps 1-2 until it passes. Never proceed to export a
  schedule that failed validation.

### 3. Export for the SIS (only after validation passes)

```bash
python exporter.py dataset.json schedule.json
```

Produces:
- `master_schedule.csv` — import this into the SIS database.
- `timetable.html` — open directly in a browser to view the weekly grid.

## One-liner (re-run everything)

```bash
python solver.py dataset.json schedule.json && python validate_schedule.py dataset.json schedule.json && python exporter.py dataset.json schedule.json
```

This stops automatically if validation fails (`&&` short-circuits), so
`master_schedule.csv` / `timetable.html` are only ever regenerated from a
schedule that actually passed.
