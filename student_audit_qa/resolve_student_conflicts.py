#!/usr/bin/env python3
"""
Capacity-Aware Automated Student Conflict Resolver.

Extends the read-only auditor (audit_student_schedules.py) into an
ACTIVE resolver: instead of only reporting a conflict and suggesting a
fix, this script actually reassigns the student and keeps live section
headcounts consistent as it does so.

ALGORITHM
    1. Build the master timetable and compute LIVE headcounts for every
       section directly from the manifest's student registrations (not
       from any external count — this is the single source of truth the
       resolver mutates as it works).
    2. For each student, repeatedly:
        a. Flatten their current registered_sections into a schedule.
        b. Find any conflicting section PAIR (two different sections
           sharing a (day, start_time)).
        c. For each side of the pair in turn, search the master
           timetable for an alternate section of the SAME course that
           satisfies BOTH hard constraints:
             - Time Non-Interference: the alternate's sessions overlap
               with NONE of the student's remaining schedule (everything
               except the section being moved).
             - Capacity Limit Enforcement:
                   current_enrollment(alt) + 1 <= max_capacity(alt)
        d. If a valid alternate exists, reassign the student to it and
           ATOMICALLY update headcounts (decrement the source section,
           increment the target) — both happen together, so headcounts
           are never observed in an inconsistent in-between state.
        e. If no alternate satisfies both constraints, stop trying to
           fix this student's remaining conflict and log
           `[FAIL - CAPACITY EXHAUSTED]` with a system recommendation.
       Repeats until the student has no more conflicts or a fix attempt
       fails (capped at a small iteration limit as a safety net).
    3. Writes the mutated manifest — same schema as the input, with
       students' `registered_sections` updated to reflect every
       successful reassignment — to `resolved_student_manifest.json`.

USAGE
    python resolve_student_conflicts.py student_manifest.json [output.json]
"""
from __future__ import annotations

import json
import sys
from collections import defaultdict

MAX_ITERATIONS_PER_STUDENT = 10


def load_json(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def build_timetable(sections: list[dict]) -> dict[str, dict]:
    return {s["section_id"]: s for s in sections}


def compute_headcounts(students: list[dict], timetable: dict[str, dict]) -> dict[str, int]:
    counts = defaultdict(int)
    for student in students:
        for section_id in student["registered_sections"]:
            if section_id in timetable:
                counts[section_id] += 1
    return counts


def flatten_schedule(section_ids: list[str], timetable: dict[str, dict]) -> list[dict]:
    schedule = []
    for section_id in section_ids:
        section = timetable.get(section_id)
        if section is None:
            continue
        for session in section["sessions"]:
            schedule.append({
                "section_id": section_id,
                "course_id": section["course_id"],
                "day": session["day"],
                "start_time": session["start_time"],
            })
    return schedule


def find_conflicting_pair(schedule: list[dict]) -> tuple[str, str] | None:
    for i in range(len(schedule)):
        for j in range(i + 1, len(schedule)):
            a, b = schedule[i], schedule[j]
            if a["section_id"] == b["section_id"]:
                continue
            if a["day"] == b["day"] and a["start_time"] == b["start_time"]:
                return tuple(sorted((a["section_id"], b["section_id"])))
    return None


def find_valid_alternate(
    course_id: str,
    exclude_section_id: str,
    rest_of_schedule: list[dict],
    timetable: dict[str, dict],
    headcounts: dict[str, int],
) -> dict | None:
    """Returns the first alternate section of `course_id` (excluding
    `exclude_section_id`) that satisfies BOTH hard constraints: time
    non-interference against `rest_of_schedule`, and capacity headroom
    (current + 1 <= max_capacity)."""
    rest_slots = {(s["day"], s["start_time"]) for s in rest_of_schedule}

    for section_id, section in timetable.items():
        if section["course_id"] != course_id or section_id == exclude_section_id:
            continue

        section_slots = {(s["day"], s["start_time"]) for s in section["sessions"]}
        time_ok = not (section_slots & rest_slots)
        capacity_ok = headcounts[section_id] + 1 <= section["max_capacity"]

        if time_ok and capacity_ok:
            return section
    return None


def resolve_student(
    student: dict,
    timetable: dict[str, dict],
    headcounts: dict[str, int],
) -> tuple[str, list[str]]:
    """Returns (status, log_lines) where status is one of:
    'ok' (no conflict to begin with), 'resolved', 'capacity_exhausted'."""
    sid = student["student_id"]
    name = student["name"]
    label = f"{name} ({sid})"
    registered = list(student["registered_sections"])
    log: list[str] = []
    had_conflict = False

    for _ in range(MAX_ITERATIONS_PER_STUDENT):
        schedule = flatten_schedule(registered, timetable)
        pair = find_conflicting_pair(schedule)
        if pair is None:
            break

        had_conflict = True
        sec_a_id, sec_b_id = pair
        rest = [s for s in schedule if s["section_id"] not in (sec_a_id, sec_b_id)]

        log.append(f"[FAIL] {label} has overlapping sections: {sec_a_id} vs {sec_b_id}.")

        resolved_this_round = False
        for moving_id, keep_id in ((sec_a_id, sec_b_id), (sec_b_id, sec_a_id)):
            moving_section = timetable[moving_id]
            keep_sessions = [s for s in schedule if s["section_id"] == keep_id]
            rest_plus_keep = rest + keep_sessions

            alt = find_valid_alternate(moving_section["course_id"], moving_id, rest_plus_keep, timetable, headcounts)
            if alt:
                alt_id = alt["section_id"]
                registered[registered.index(moving_id)] = alt_id
                headcounts[moving_id] -= 1
                headcounts[alt_id] += 1
                log.append(
                    f"[RESOLVED] Reassigned {label} from {moving_id} to {alt_id}. "
                    f"Headcounts updated: {moving_id} -> {headcounts[moving_id]}, "
                    f"{alt_id} -> {headcounts[alt_id]}/{alt['max_capacity']}."
                )
                resolved_this_round = True
                break

        if not resolved_this_round:
            course_a = timetable[sec_a_id]["course_id"]
            course_b = timetable[sec_b_id]["course_id"]
            log.append(f"[FAIL - CAPACITY EXHAUSTED] No alternate section for {sec_a_id} or {sec_b_id} "
                       f"satisfies both time non-interference and capacity headroom for {label}.")
            log.append(f"[RECOMMENDED FIX] Open additional section for course {course_a} or {course_b}, "
                       f"or increase room capacity for an existing alternate section.")
            student["registered_sections"] = registered
            return "capacity_exhausted", log

    student["registered_sections"] = registered
    if not had_conflict:
        return "ok", log
    return "resolved", log


def main() -> int:
    if len(sys.argv) not in (2, 3):
        print("Usage: python resolve_student_conflicts.py student_manifest.json [resolved_student_manifest.json]", file=sys.stderr)
        return 1

    in_path = sys.argv[1]
    out_path = sys.argv[2] if len(sys.argv) == 3 else "resolved_student_manifest.json"

    try:
        manifest = load_json(in_path)
    except (OSError, json.JSONDecodeError) as e:
        print(f"Failed to load input: {e}", file=sys.stderr)
        return 1

    timetable = build_timetable(manifest["sections"])
    headcounts_before = compute_headcounts(manifest["students"], timetable)
    headcounts = dict(headcounts_before)

    print("=" * 78)
    print("CAPACITY-AWARE AUTOMATED STUDENT CONFLICT RESOLVER")
    print("=" * 78)

    status_by_student: dict[str, str] = {}
    before_sections: dict[str, list[str]] = {}

    for student in manifest["students"]:
        before_sections[student["student_id"]] = list(student["registered_sections"])
        print()
        status, log = resolve_student(student, timetable, headcounts)
        status_by_student[student["student_id"]] = status
        if status == "ok":
            print(f"[OK] {student['name']} ({student['student_id']}): no conflicts, nothing to resolve.")
        else:
            for line in log:
                print(line)

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2)

    # ---------------------------------------------- Pre-fix vs post-fix table
    print()
    print("=" * 78)
    print("PRE-FIX vs POST-FIX COMPARISON")
    print("=" * 78)
    print(f"{'Student':<24}{'Status':<20}{'Before':<42}{'After'}")
    for student in manifest["students"]:
        sid = student["student_id"]
        label = f"{student['name']} ({sid})"
        status = status_by_student[sid]
        before = ", ".join(before_sections[sid])
        after = ", ".join(student["registered_sections"])
        print(f"{label:<24}{status:<20}{before:<42}{after}")

    print()
    print("Section headcounts — before -> after:")
    for section_id in timetable:
        before_count = headcounts_before[section_id]
        after_count = headcounts[section_id]
        cap = timetable[section_id]["max_capacity"]
        flag = "" if after_count <= cap else "  *** OVER CAPACITY ***"
        print(f"  {section_id:<15} {before_count} -> {after_count} / {cap}{flag}")

    resolved_count = sum(1 for s in status_by_student.values() if s == "resolved")
    exhausted_count = sum(1 for s in status_by_student.values() if s == "capacity_exhausted")
    print()
    print(f"Resolved: {resolved_count}   Capacity-exhausted (unresolved): {exhausted_count}")
    print(f"Wrote resolved manifest to {out_path}.")
    print("=" * 78)

    return 0 if exhausted_count == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
