# Student Schedule Conflict Auditor: Architecture & Validation

**Subsystem:** Student Schedule Conflict Auditor (`student_audit_qa/`)
**Document type:** Technical case study
**Status:** Validated — 9/10 students conflict-free, 1 deliberate edge case correctly detected and resolved

---

## 1. Executive Summary

A course timetable and a room-balancing engine can both be individually
perfect — no room double-booked, no professor double-booked, every
section correctly sized — and a student can still end up with an
impossible schedule. That happens the moment a student registers for two
sections that, independently, were each scheduled validly, but which
happen to meet at the same time. Neither the scheduling engine nor the
section-balancing engine can catch this on their own: it is purely a
property of one specific student's *personal combination* of
registrations, invisible until those registrations are actually laid
side by side.

This auditor closes that gap. It takes the master section timetable
(the output of the scheduling engine) and a roster of actual student
registrations, builds each student's individual weekly schedule, and
checks every pair of that student's sections for a shared timeslot.

Tested against 10 student profiles with varied course loads — one of
them deliberately constructed to contain a real double-booking — the
auditor correctly passed the 9 legitimately conflict-free students with
zero false positives, and correctly caught and diagnosed the one real
conflict, down to the exact day, time, and course pair involved, with a
concrete, verified alternate-section recommendation.

---

## 2. Data Model

`student_manifest.json` combines two independent data sources into one
manifest, exactly as they would arrive from two different systems in
production:

- **`sections`** — the master timetable: every section's `course_id`
  and its list of `(day, start_time)` sessions. This is the scheduling
  engine's output — already internally conflict-free (no two sections
  share a room, no professor is double-booked).
- **`students`** — each student's `registered_sections`: a flat list of
  section IDs they are enrolled in. This is pure registration data, with
  no awareness of time at all.

The auditor's entire job is to resolve the second list against the
first and ask a question neither source can answer on its own: *does
this particular combination of registrations put this student in two
places at once?*

---

## 3. Algorithm

### 3.1 Building an individual timetable

For each student, every `section_id` in their `registered_sections` is
looked up in the master timetable and expanded into its full session
list. The result is a flat list of `(section_id, course_id, day,
start_time)` tuples — the student's actual personal weekly schedule,
assembled purely from data, not from any assumption about what "should"
be conflict-free.

### 3.2 Pairwise overlap detection

Every pair of *different* sections in that flattened schedule is
compared for a shared `(day, start_time)`. Two sessions belonging to the
**same** section are never compared against each other (a section
meeting twice a week is not a conflict with itself) — only cross-section
pairs are checked, which is what correctly isolates a genuine
double-booking between two distinct courses.

This is deliberately the same category of check used throughout this
test suite's other engines (room overlap in the scheduling engine,
corequisite overlap in both the scheduling and integrated-pipeline
engines) — pairwise shared-timeslot detection over a flattened session
list — applied here at the student level instead of the room or
professor level.

### 3.3 Resolution: finding a real, verified alternate

On a detected conflict between section A and section B, the auditor
does not simply report the clash — it searches the master timetable for
another section of **A's course** (or, if that fails, of **B's
course**) whose sessions overlap with **none** of the student's
remaining schedule, including the section that would stay in place. Only
if such a section genuinely exists in the master timetable does the
auditor recommend it; if no conflict-free alternate exists anywhere in
the timetable, it says so explicitly rather than fabricating a
suggestion ("a new section may need to be opened").

This matters: the recommendation is not a canned message — it is the
result of actually checking the alternate section against the rest of
that specific student's schedule, so the fix is *provably* correct, not
just plausible-sounding.

---

## 4. Diagnostic Output Format

```
[FAIL] Student X (S05) has overlapping sections: CS101 (CS101-SEC1) vs
       MATH101 (MATH101-SEC1) on Mon at 09:00.
[RECOMMENDED FIX] Move Student X (S05) from CS101-SEC1 (Mon 09:00) to
       CS101-SEC2 (Tue 10:00) to resolve the conflict.
```

Every conflict produces exactly this pair: a `[FAIL]` line naming the
student, both courses, and the exact day/time of the clash, followed by
a `[RECOMMENDED FIX]` line naming a concrete section to move to and the
day/time it would land on instead.

For a student with multiple overlapping session instances (e.g. a pair
of sections that clash on *both* days they meet), each instance is
reported as its own `[FAIL]`/`[RECOMMENDED FIX]` pair — so the report
never collapses two distinct real clashes into a single vague warning.

---

## 5. Test Case Audit Results

10 students were audited against an 11-section master timetable (7
distinct courses). 9 were constructed to be legitimately conflict-free;
1 (Student X, `S05`) was deliberately registered into both `CS101-SEC1`
and `MATH101-SEC1`, which the master timetable happens to schedule at
the identical `Mon 09:00` / `Wed 09:00` slots.

| Student | Sections Registered | Result |
|---|---|---|
| Alice Rivera (S01) | CS101-SEC1, ENG101-SEC1, HIST101-SEC1 | **OK** |
| Ben Tolentino (S02) | CS101-SEC2, MATH101-SEC2, PHYS101-SEC1 | **OK** |
| Carla Dizon (S03) | MATH101-SEC1, BIO101-SEC1, ART101-SEC1 | **OK** |
| Dennis Ocampo (S04) | CS101-SEC2, ENG101-SEC1, CHEM101-SEC1 | **OK** |
| **Student X (S05)** | CS101-SEC1, MATH101-SEC1, PE101-SEC1 | **FAIL (2 clash instances)** |
| Faye Lacson (S06) | PHYS101-SEC1, HIST101-SEC1, ART101-SEC1 | **OK** |
| Gino Manalo (S07) | CS101-SEC1, BIO101-SEC1, PE101-SEC1 | **OK** |
| Hannah Soriano (S08) | MATH101-SEC2, CHEM101-SEC1, ENG101-SEC1 | **OK** |
| Ivan Cortez (S09) | CS101-SEC2, HIST101-SEC1, BIO101-SEC1 | **OK** |
| Jenny Villar (S10) | PHYS101-SEC1, ART101-SEC1, CHEM101-SEC1 | **OK** |

**Result: 9/10 students conflict-free, 1/10 correctly flagged.**

The flagged conflict was reported as two separate `[FAIL]` entries (the
Monday 09:00 clash and the Wednesday 09:00 clash — the two sections meet
at the same time on both days), each with its own
`[RECOMMENDED FIX]` resolving to `CS101-SEC2` (Tue 10:00) — verified by
the auditor to have zero overlap with Student X's remaining schedule
(`MATH101-SEC1` and `PE101-SEC1`) before being recommended.

The zero-false-positive result across the other 9 students confirms the
auditor's pairwise check does not over-trigger on legitimately
non-overlapping, varied course loads — it flags exactly the one real
problem in the dataset and nothing else.
