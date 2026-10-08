# Scheduling Engine Architecture: A Zero-Backtrack CSP Case Study

**Subsystem:** University Course Timetabling Engine (`scheduler_qa/`)
**Document type:** Technical case study
**Status:** Validated — 0 hard-constraint violations, soft score 28.0/100

---

## 1. Executive Summary

University course timetabling — assigning every course session a
(day, time, room) slot such that no professor, room, or corequisite
relationship is double-booked — belongs to a well-known class of
**NP-complete** combinatorial problems. It is a direct generalization of
graph coloring and multi-dimensional bin packing: as the number of
courses, rooms, and professor-availability restrictions grows, the number
of candidate assignments grows combinatorially, and no known algorithm
solves the general case in polynomial time. In practice this means a
timetabling engine cannot rely on brute-force enumeration; it must prune
the search space intelligently enough that a solution — if one exists —
is found in a tractable number of steps.

This engine was tested against a deliberately adversarial synthetic
dataset: 40 courses (67 total weekly sessions), 10 rooms across 3
buildings, 15 professors, and 45 weekly timeslots, with three engineered
bottlenecks (two professors carrying 5 courses each against 13-14 blocked
timeslots, tight Lab-capacity-vs-enrollment fits, and only two
lecture halls large enough for the four biggest classes).

The result: the engine's Constraint Satisfaction Problem (CSP) solver,
using a Minimum-Remaining-Values (MRV) variable-ordering heuristic,
placed **all 67 sessions in exactly 67 search steps with zero
backtracking** — meaning every decision the algorithm made on its first
attempt remained valid for the rest of the search. This is the
best-case outcome for a backtracking search: it indicates the variable
ordering correctly identified and resolved the tightest constraints
(the overloaded professors, the scarce Lab rooms) before they could
create a dead end later in the search.

The resulting schedule passed **all six hard constraints** with zero
violations and scored **28.0 / 100** on soft (quality) constraints — a
result this document shows is close to the structural ceiling imposed by
the campus's physical room layout, not a shortcoming of the algorithm.

---

## 2. Algorithmic Approach

### 2.1 Problem formulation as a CSP

The engine (`solver.py`) models the timetabling problem as a classic
Constraint Satisfaction Problem:

- **Variables**: one per required session. A course with
  `sessions_per_week = N` contributes `N` independent variables (e.g.
  `MATH101` has 3 variables, since it meets 3 times a week). Across the
  dataset, 40 courses produced **67 variables**.
- **Domain**: for a given variable, the set of all `(timeslot, room)`
  pairs that do not yet violate a hard constraint, computed **fresh at
  each search step** against the current partial assignment (forward
  checking). A candidate is excluded from the domain the moment it would
  violate room type, capacity, professor availability, or any
  already-made booking.
- **Constraints**: the six hard rules in Section 3, plus the implicit CSP
  rule that no two variables may resolve to a state that creates a
  conflict.

### 2.2 Variable ordering: Minimum Remaining Values (MRV)

At every step, instead of assigning sessions in an arbitrary or
course-list order, the solver scans **all currently unassigned
variables**, computes each one's live domain size, and commits to the
variable with the **smallest domain first**.

This matters because the dataset's bottlenecks are not evenly
distributed. Professors `P03` and `P11` are each unavailable for
13-14 of the 45 weekly timeslots (vs. 3-6 for everyone else) while
carrying 5 courses apiece — their sessions have inherently small domains.
Left to the end of a naive search, these tightly-constrained sessions
would frequently find all their remaining options already consumed by
other, looser-constrained courses, forcing expensive backtracking or
outright failure. MRV instead forces the solver to **resolve the
tightest constraints while the most options still exist**, which is the
single biggest lever in CSP search performance for problems shaped like
this one.

### 2.3 Value ordering: soft-score-aware heuristic

Where MRV decides *which* variable to assign next, a secondary heuristic
decides *which* candidate `(timeslot, room)` to try first within that
variable's domain. Each candidate is scored on:

- **Room-fit waste**: `(room.capacity - course.expected_enrollment) /
  room.capacity` — smaller is better, directly minimizing the
  "small class in an oversized room" soft penalty.
- **Corequisite proximity**: for any already-placed corequisite partner
  session, the candidate is scored by same-day time gap (smaller is
  better) and — where a same-day match exists — implicitly biased toward
  matching rooms.

Candidates are tried best-scored-first. This means that whenever the
search has more than one hard-constraint-legal option (which is most of
the time), it reaches for the one that *also* helps the soft score,
rather than an arbitrary legal option — the search finds a good schedule
on the same pass that it finds a legal one, with no separate
optimization phase required.

### 2.4 Backtracking as a safety net, not the primary mechanism

Classic backtracking — undo the last assignment and try the next
candidate when a dead end is reached — is implemented and available, with
a 400,000-step ceiling and up to 30 randomized-tie-break restart attempts.
In this run, **it was never invoked**: the MRV ordering alone was
sufficient to reach a complete, valid assignment of all 67 sessions in
67 steps (one search step per variable, no retries). This zero-backtrack
outcome is the strongest possible signal that the heuristic ordering was
well matched to the structure of this specific problem instance.

---

## 3. Constraint Resolution (Hard Limits)

Every hard constraint is enforced **structurally** — a candidate
`(timeslot, room)` is never added to a variable's domain if it would
violate one of these rules, rather than being checked for violations
after the fact. This guarantees that any assignment the solver commits to
is legal by construction; the validator's role (Section 4) is to prove
this independently, not to catch mistakes the solver might make.

| # | Constraint | Enforcement mechanism |
|---|------------|------------------------|
| 1 | **Room overlap** | A `(room_id, timeslot_id)` pair is removed from every other variable's domain the instant it is booked. |
| 2 | **Professor overlap** | A `(professor_id, timeslot_id)` pair is booked the moment any of that professor's courses claims the slot, blocking every other course taught by the same professor from that slot. |
| 3 | **Capacity** | A room is only ever a domain candidate for a course if `room.capacity >= course.expected_enrollment`; rooms are additionally pre-sorted smallest-capacity-first per course to favor tight fits. |
| 4 | **Room type** | Candidate rooms per course are pre-filtered to `room.type == course.required_room_type` (Lecture / Seminar / Lab) before any search begins. |
| 5 | **Professor availability** | Each course's domain is restricted up front to timeslots outside that course's professor's `unavailable_timeslots` list. |
| 6 | **Corequisite overlap** | A course's domain excludes any timeslot already claimed by a session of any course listed in its `corequisite_courses` — checked symmetrically, so the relationship holds regardless of which half of the pair is scheduled first. |

Independent verification via `validate_schedule.py` (a separate,
independently-written checker that re-derives every fact from
`dataset.json` rather than trusting the solver's internal state)
confirmed **zero violations across all six categories** on the final
schedule.

---

## 4. Structural Optimization (Soft Score Analysis)

The validated schedule scored **28.0 / 100** on soft constraints. This
section breaks down exactly where those 72 points were lost, and shows
that the loss is a property of the campus's room layout, not a defect in
the search.

### 4.1 What was NOT penalized

- **Underfilled rooms**: zero penalty. The room-fit-waste value-ordering
  heuristic (Section 2.3) consistently chose the smallest sufficient
  room for every course, so no small class ended up stranded in an
  oversized hall.
- **Corequisite time gaps**: zero penalty. Every one of the 10
  corequisite relationships in the dataset achieved the *minimum
  possible* same-day gap between its paired sessions — the soft-score
  value ordering drove every pairing to its best achievable proximity in
  time.

### 4.2 What WAS penalized: corequisite building splits

All 72 lost points came from a single penalty category:

```
-8.00  Corequisite ('CS101', 'CS101L')     split across buildings
-8.00  Corequisite ('CS102', 'CS102L')     split across buildings
-8.00  Corequisite ('CS210', 'CS210L')     split across buildings
-8.00  Corequisite ('CS301', 'CS301L')     split across buildings
-8.00  Corequisite ('CS350', 'CS350L')     split across buildings
-8.00  Corequisite ('ENGR101', 'ENGR101L') split across buildings
-8.00  Corequisite ('ENGR210', 'ENGR210L') split across buildings
-8.00  Corequisite ('PHYS101', 'PHYS101L') split across buildings
-8.00  Corequisite ('PHYS102', 'PHYS102L') split across buildings
                                            9 x -8.00 = -72.00
```

**Why this is a physical constraint, not an algorithmic one:** every
Lecture+Lab corequisite pair in the dataset pairs a Lecture-type session
with a Lab-type session. Cross-referencing `dataset.json`'s room list by
building:

| Building | Room types present |
|----------|---------------------|
| North Hall | Lecture only (`LEC-A`, `LEC-B`, `LEC-C`) |
| South Wing | Lecture only (`LEC-D`, `LEC-E`), Seminar (`SEM-A`) |
| East Annex | Seminar (`SEM-B`), **Lab only** (`LAB-A`, `LAB-B`, `LAB-C`) |

No building on this campus contains both a Lecture-type room and a
Lab-type room. Consequently, **every possible placement of a
Lecture+Lab corequisite pair is, by construction, split across
buildings** — there is no `(timeslot, room)` assignment in the entire
solution space that could avoid this penalty for these 9 pairs. The
solver's 28.0/100 score is therefore not a gap between the algorithm and
an achievable optimum; the achievable ceiling under this room layout is
exactly `100 - (9 x 8.00) = 28.0`, and the solver reached it precisely,
losing zero further points to any other avoidable soft penalty.

This distinction matters operationally: improving this score in a real
deployment requires a **facilities decision** (locating a Lab in a
building that also has Lecture capacity, or vice versa), not a
**scheduling algorithm** change.

---

## 5. Frontend UI Integration

The validated, exported schedule is rendered for human review in
`scheduler_qa/timetable.html`, generated by `exporter.py`. Key design
choices:

- **Grouped by room, not professor.** With 10 rooms vs. 15 professors, a
  room-column grid fits a normal screen width without horizontal
  scrolling, and "is this room free at this time" is the more common
  lookup than a per-professor roster.
- **Sticky header row.** Room column headers (room ID, name, building,
  capacity) remain pinned at the top of the viewport while scrolling
  through the Mon-Fri / 08:00-17:00 grid, so the room being viewed is
  never ambiguous on a long scroll.
- **Department color coding.** Each scheduled cell is tinted and
  left-bordered by its course's department color (Computer Science,
  Mathematics, Physics, Engineering, Business each get a distinct hue),
  with a legend above the grid — making cross-department room contention
  visually scannable at a glance, without reading every cell.
- **Capacity metrics in-line.** Each room column header shows the room's
  capacity directly beneath its name, so a reviewer can sanity-check
  "is this class in a sensibly-sized room" without cross-referencing
  `dataset.json`.

This HTML view, together with the companion `master_schedule.csv`
(a flat, chronologically-sorted export with Course/Professor/Room/Building
columns), forms the two SIS-facing deliverables of the pipeline: one for
human visual review, one for direct database ingestion.

---

## Appendix: Key Metrics at a Glance

| Metric | Value |
|--------|-------|
| Courses | 40 |
| Total weekly sessions | 67 |
| Rooms | 10 (3 buildings) |
| Professors | 15 |
| Weekly timeslots | 45 |
| Search steps to solution | 67 |
| Backtracks required | 0 |
| Hard-constraint violations | 0 / 6 categories |
| Soft score achieved | 28.0 / 100 |
| Soft score ceiling (this room layout) | 28.0 / 100 |
| Underfill penalty | 0.00 |
| Corequisite time-gap penalty | 0.00 |
| Corequisite building-split penalty | -72.00 (structural, 9 pairs x -8.00) |
